# Zanders Related Product Slider Plugin (`ZandersRelatedProductSlider`)

## Overview
The `ZandersRelatedProductSlider` plugin provides a responsive related and alternative products carousel specifically tailored for Zanders Sporting Goods dealer workflows and Product Detail Pages (PDP).

## Features
- **Intelligent Product Matching**:
  - Automatically fetches active storefront products from the same category / category tree.
  - Prioritizes in-stock products with fallback to featured storefront products if a category has few items.
  - Excludes the current product and its parent / variant siblings.
- **Interactive Controls**:
  - **HIDE OUT OF STOCK ITEMS** toggle button filtering unavailable products in real-time.
  - **Prev / Next Navigation Controls** for smooth scrolling across the carousel track.
  - Touch / drag scroll support on mobile and desktop.
- **Rich Dealer Card UI**:
  - Product Image & Title
  - Item Number & UPC
  - Available stock quantity badge
  - Dealer Price & MSRP
  - Direct "ADD TO CART" form
  - Wishlist (heart icon) & Compare (scale icon) actions
- **Configurable in Shopware Administration**:
  - Enable / disable slider
  - Slider title (default: `ALTERNATIVE PRODUCTS`)
  - Maximum product limit
  - Toggle visibility of "Hide Out of Stock Items"
  - Optional autoplay & transition speed

## Installation & Activation
```bash
php bin/console plugin:refresh
php bin/console plugin:install --activate ZandersRelatedProductSlider
php bin/console cache:clear
```

## Template Usage
In any PDP or CMS template:
```twig
{% set relatedProducts = page.extensions.zandersRelatedProducts ? page.extensions.zandersRelatedProducts.products : [] %}
{% if relatedProducts|length > 0 %}
    {% sw_include '@ZandersRelatedProductSlider/storefront/component/product/related-product-slider.html.twig' with {
        products: relatedProducts,
        title: page.extensions.zandersRelatedProducts.title|default('ALTERNATIVE PRODUCTS'),
        config: page.extensions.zandersRelatedProducts.config|default(null)
    } %}
{% endif %}
```

