# Zanders Right Sidebar (`zanders-sidebar-col--right`) Architecture & Implementation Guide

## 1. Overview

The `zanders-sidebar-col--right` component provides the 3rd column in the Zanders 3-column shop and search layout (matching `shop2.gzanders.com`). It delivers high-conversion dealer utilities:
- **Guest State**: Quick Dealer Login Box and Recently Viewed Products.
- **Logged In State**: Dynamic Compare Products widget, Quick Actions (*View My Open Order*, *View Wishlist Items*), and Recently Viewed Products.
- **Admin Control**: Shop managers can enable or disable the right sidebar directly from the Shopware Admin Theme Manager. When disabled, the product listing automatically expands to fill the available space.
- **100% Dynamic**: Eliminates all hardcoded static demo products, syncing live with `localStorage`, asynchronous AJAX endpoints, and Shopware's event bus.

---

## 2. Admin Configuration: Enable / Disable Right Sidebar

### 2.1 Theme Configuration (`theme.json`)
The switch option is configured in `Resources/theme.json` under the custom `zandersSettings` tab and `zandersLayout` block:

```json
"zanders-sidebar-right-show": {
  "type": "switch",
  "tab": "zandersSettings",
  "block": "zandersLayout",
  "order": 300,
  "value": true,
  "editable": true,
  "label": {
    "en-GB": "Show Right Sidebar",
    "de-DE": "Rechte Seitenleiste anzeigen"
  },
  "helpText": {
    "en-GB": "Enable or disable the right sidebar (Login, Compare, Recently Viewed) on shop and search pages.",
    "de-DE": "Rechte Seitenleiste (Login, Produktvergleich, Zuletzt angesehen) auf Shop- und Suchseiten aktivieren oder deaktivieren."
  }
}
```

### 2.2 Template Integration
In both [cms-section-sidebar.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/section/cms-section-sidebar.html.twig) and [search/index.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/page/search/index.html.twig):

```twig
{% set showRightSidebar = theme_config('zanders-sidebar-right-show') is same as(false) ? false : true %}

{# Center Column dynamically adjusts column span #}
<div class="cms-section-sidebar-main-content zanders-main-col col-12 {% if showRightSidebar %}col-lg-6 col-xl-7{% else %}col-lg-9 col-xl-10{% endif %}">
    ...
</div>

{# Right Column conditionally rendered #}
{% if showRightSidebar %}
    <div class="zanders-sidebar-col zanders-sidebar-col--right col-12 col-lg-3 col-xl-3">
        {% sw_include '@Storefront/storefront/component/listing/sidebar-right.html.twig' %}
    </div>
{% endif %}
```

When disabled:
- The right column is omitted from the DOM.
- The center column switches to `col-lg-9 col-xl-10`.
- In SCSS, `.zanders-main-col` has `flex: 1 1 0; max-width: none;`, meaning it smoothly expands to occupy 100% of the remaining container width after the 280px left filter panel.

---

## 3. Dynamic Data Architecture & Flows

### 3.1 Dynamic Compare Products Flow
The compare box integrates with the `FroshProductCompare` plugin and its localStorage registry:

```mermaid
sequenceDiagram
    autonumber
    actor User as Dealer / Customer
    participant Page as Product Card / PDP
    participant Store as localStorage ('compare-widget-added-products')
    participant Emitter as document.$emitter
    participant Sidebar as sidebar-right.html.twig
    participant Backend as POST /compare/offcanvas

    User->>Page: Clicks scale/compare icon
    Page->>Store: Saves product ID array ['0193498a...', ...]
    Page->>Emitter: Publishes 'changedProductCompare' / 'afterAddProductCompare'
    Emitter->>Sidebar: Triggers renderCompareProducts()
    Sidebar->>Store: Reads productIds
    alt Meta Cache exists
        Sidebar->>Sidebar: Instantly renders titles and URLs from cache
    else Missing Product Details
        Sidebar->>Backend: Sends POST /compare/offcanvas with productIds[]
        Backend-->>Sidebar: Returns offcanvas HTML list
        Sidebar->>Sidebar: Parses titles and URLs, caches in 'zanders-compare-meta-cache'
        Sidebar->>Sidebar: Re-renders compare items list
    end
    User->>Sidebar: Clicks [x] on an item
    Sidebar->>Store: Updates productIds (removes clicked ID)
    Sidebar->>Emitter: Publishes 'afterRemoveProductCompare'
    Sidebar->>Sidebar: Re-renders list
    User->>Sidebar: Clicks 'CLEAR ALL'
    Sidebar->>Store: Removes key
    Sidebar->>Emitter: Publishes 'changedProductCompare' (empty)
    Sidebar->>Sidebar: Shows 'You have no items to compare.'
```

### 3.2 Dynamic Recently Viewed Flow
Recently viewed tracking works seamlessly across the entire store:

1. **PDP Automatic Tracking**:
   - In [product-detail.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/page/content/product-detail.html.twig), on page load, `trackRecentlyViewed()` captures `{ id, name, url: window.location.pathname }`.
   - The item is unshifted to the top of the `zanders-recently-viewed-products` JSON array in `localStorage` (deduplicated, max 8 items).
2. **Catalog / Search Click Tracking**:
   - In [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig), a delegated click listener captures clicks on product links and adds the clicked product to `localStorage` immediately.
3. **Dynamic Sidebar Rendering**:
   - `renderRecentlyViewed()` reads `zanders-recently-viewed-products`.
   - If empty: displays `<div class="zanders-recent-empty-msg">You have no recently viewed items.</div>`.
   - If populated: displays up to 5 items with uppercase titles and direct links.
   - Updates both the guest view and logged-in view.
4. **Cross-Tab Synchronization**:
   - Subscribes to `window.addEventListener('storage', ...)`. When a product is viewed in another tab, the right sidebar updates instantaneously.

### 3.3 Cache-Safe Guest vs. Logged-In Flow
Because category and listing pages in Shopware 6 are aggressively cached by the HTTP cache (reverse proxy/Varnish/Fastly/Symfony cache), server-side Twig logic cannot reliably detect customer login states without busting cache tags.

To overcome this, `sidebar-right.html.twig` uses a client-side detection strategy:
- Inspects client indicators: `window.customerLoggedInState`, `.top-bar-logout`, `a[href*="account/logout"]`, `.header-wishlist`, `.account-title`.
- If logged in: displays `.js-sidebar-logged-in-view` and hides `.js-sidebar-guest-view`.
- If guest: displays `.js-sidebar-guest-view` (Login box) and hides `.js-sidebar-logged-in-view`.
- Monitored by `MutationObserver` to handle dynamic asynchronous header re-renders.

---

## 4. Functions Reference

All functions are implemented in vanilla JavaScript (ES6/ES5 compatible) with zero external library overhead:

| Function Name | Location | Description |
| :--- | :--- | :--- |
| `escapeHtml(str)` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Sanitizes strings to prevent XSS injection before injecting dynamic HTML. |
| `checkCustomerLoggedIn()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Client-side check determining if user is logged in, bypassing HTTP cache. |
| `applySidebarVisibility()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Toggles `.d-none` and inline style between guest and logged-in sidebar wrappers. |
| `renderCompareProducts()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Reads compare product IDs, queries `/compare/offcanvas` for names/links, updates cache, and renders list items or empty state. |
| `getCompareMetaCache()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Retrieves cached `{ productId: { name, url } }` dictionary from `localStorage`. |
| `saveCompareMetaCache(cache)` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Persists comparison metadata dictionary to prevent redundant network calls. |
| `initCompareActions()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Binds click handlers for `[x]` remove item and `CLEAR ALL` buttons; dispatches Frosh events. |
| `renderRecentlyViewed()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Reads `zanders-recently-viewed-products`, constructs links, and renders to all active sidebar recent boxes. |
| `initRecentlyViewedLinkTracking()` | [sidebar-right.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/component/listing/sidebar-right.html.twig) | Global delegated click handler tracking clicks on catalog product cards into Recently Viewed. |
| `trackRecentlyViewed()` | [product-detail.html.twig](file:///var/www/html/zander-poc/custom/apps/ZandersDesignTheme/Resources/views/storefront/page/content/product-detail.html.twig) | Captures currently viewed product on PDP page load into `zanders-recently-viewed-products`. |

---

## 5. Storage Keys & Schemas

### 5.1 Compare Products
- **Key**: `compare-widget-added-products` (Managed by FroshProductCompare & Zanders right sidebar)
- **Format**: Array of UUID strings:
  ```json
  ["0193498a44b3701297d025170d10c0aa", "0193498b5e2d713f9f9b6e82c208c11b"]
  ```
- **Metadata Cache Key**: `zanders-compare-meta-cache`
- **Format**:
  ```json
  {
    "0193498a44b3701297d025170d10c0aa": {
      "name": "WINCHESTER WILDCAT 22LR 40GR LEAD-RN 1255FPS 50RD 100BX/CS",
      "url": "/Catalog/Firearms/Rifles/Winchester-Wildcat-22LR/..."
    }
  }
  ```

### 5.2 Recently Viewed Products
- **Key**: `zanders-recently-viewed-products`
- **Format**: Array of product objects:
  ```json
  [
    {
      "id": "0193498a44b3701297d025170d10c0aa",
      "name": "WINCHESTER WILDCAT 22LR 40GR LEAD-RN 1255FPS 50RD 100BX/CS",
      "url": "/Catalog/Firearms/Rifles/Winchester-Wildcat-22LR/..."
    }
  ]
  ```

---

## 6. Verification Checklist

1. **Theme Compilation**:
   ```bash
   DATABASE_URL="mysql://root:Admin%40123@127.0.0.1:3306/zander-poc" php bin/console theme:compile
   DATABASE_URL="mysql://root:Admin%40123@127.0.0.1:3306/zander-poc" php bin/console cache:clear
   ```
2. **Admin Verification**:
   - Navigate to **Shopware Admin > Content > Themes > ZandersDesignTheme**.
   - Check the **Zanders Settings** tab > **Layout & Container** section.
   - Switch **Show Right Sidebar** OFF and save. Visit the catalog page (`/Catalog/Firearms`) — verify the right sidebar is absent and the product listing fills the container.
   - Switch **Show Right Sidebar** ON and save. Verify the right sidebar reappears.
3. **Dynamic Data Verification**:
   - View a product PDP. Return to the catalog page: verify the product appears in **RECENTLY VIEWED**.
   - Click the compare scale icon on 1 or 2 products. Verify they appear in **COMPARE PRODUCTS** with live title and link.
   - Click `&times;` on a compare item: verify it disappears from the sidebar and updates the compare badge.
   - Click **CLEAR ALL**: verify the list clears and shows `"You have no items to compare."`.

