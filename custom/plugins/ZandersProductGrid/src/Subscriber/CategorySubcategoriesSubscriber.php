<?php declare(strict_types=1);

namespace ZandersProductGrid\Subscriber;

use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Storefront\Page\Navigation\NavigationPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Attaches `zandersSubcategories` to NavigationPage so category & subcategory
 * pages can render a polished Zanders subcategory strip / sibling switcher
 * with live product counts directly above the shop listing toolbar.
 */
class CategorySubcategoriesSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_KEY = 'zandersSubcategories';

    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            NavigationPageLoadedEvent::class => 'onNavigationPageLoaded',
        ];
    }

    public function onNavigationPageLoaded(NavigationPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $navigationId = $page->getNavigationId();
        $salesChannelContext = $event->getSalesChannelContext();
        $rootId = $salesChannelContext->getSalesChannel()->getNavigationCategoryId();

        // Skip homepage / root category
        if (!$navigationId || $navigationId === $rootId) {
            return;
        }

        $languageId = Uuid::fromHexToBytes($salesChannelContext->getLanguageId());
        $fallbackLanguageId = Uuid::fromHexToBytes(Defaults::LANGUAGE_SYSTEM);
        $salesChannelId = Uuid::fromHexToBytes($salesChannelContext->getSalesChannelId());
        $liveVersionId = Uuid::fromHexToBytes(Defaults::LIVE_VERSION);

        $currentRow = $this->connection->fetchAssociative(
            'SELECT c.id, c.parent_id, c.level,
                    COALESCE(ct_cur.name, ct_fb.name) AS name
             FROM category c
             LEFT JOIN category_translation ct_cur
                 ON ct_cur.category_id = c.id AND ct_cur.category_version_id = c.version_id AND ct_cur.language_id = :langId
             LEFT JOIN category_translation ct_fb
                 ON ct_fb.category_id = c.id AND ct_fb.category_version_id = c.version_id AND ct_fb.language_id = :fbLangId
             WHERE c.id = :catId AND c.version_id = :verId',
            [
                'langId' => $languageId,
                'fbLangId' => $fallbackLanguageId,
                'catId' => Uuid::fromHexToBytes($navigationId),
                'verId' => $liveVersionId,
            ]
        );

        if (!$currentRow) {
            return;
        }

        // First try direct children of the current category
        $children = $this->fetchChildrenWithCounts(
            $currentRow['id'],
            $languageId,
            $fallbackLanguageId,
            $salesChannelId,
            $liveVersionId
        );

        $mode = 'children';
        $parentIdHex = $navigationId;
        $parentName = (string) ($currentRow['name'] ?? '');
        $items = $children;

        // If leaf subcategory (level >= 4 and no children), load sibling subcategories under its parent
        if ($items === [] && (int) $currentRow['level'] >= 4 && !empty($currentRow['parent_id'])) {
            $parentRow = $this->connection->fetchAssociative(
                'SELECT c.id, COALESCE(ct_cur.name, ct_fb.name) AS name
                 FROM category c
                 LEFT JOIN category_translation ct_cur
                     ON ct_cur.category_id = c.id AND ct_cur.category_version_id = c.version_id AND ct_cur.language_id = :langId
                 LEFT JOIN category_translation ct_fb
                     ON ct_fb.category_id = c.id AND ct_fb.category_version_id = c.version_id AND ct_fb.language_id = :fbLangId
                 WHERE c.id = :parentId AND c.version_id = :verId',
                [
                    'langId' => $languageId,
                    'fbLangId' => $fallbackLanguageId,
                    'parentId' => $currentRow['parent_id'],
                    'verId' => $liveVersionId,
                ]
            );

            $siblings = $this->fetchChildrenWithCounts(
                $currentRow['parent_id'],
                $languageId,
                $fallbackLanguageId,
                $salesChannelId,
                $liveVersionId
            );

            if ($siblings !== [] && $parentRow) {
                $mode = 'siblings';
                $parentIdHex = Uuid::fromBytesToHex($parentRow['id']);
                $parentName = (string) ($parentRow['name'] ?? '');
                $items = $siblings;
            }
        }

        if ($items === []) {
            return;
        }

        $formatted = [];
        foreach ($items as $row) {
            $idHex = Uuid::fromBytesToHex($row['id']);
            $formatted[] = [
                'id' => $idHex,
                'name' => (string) $row['name'],
                'count' => (int) $row['product_count'],
                'isActive' => ($idHex === $navigationId),
            ];
        }

        $page->addExtension(self::EXTENSION_KEY, new ArrayStruct([
            'mode' => $mode,
            'parentId' => $parentIdHex,
            'parentName' => $parentName,
            'currentName' => (string) ($currentRow['name'] ?? ''),
            'items' => $formatted,
        ]));
    }

    /**
     * Fetches active visible child categories along with their active storefront product count.
     *
     * @return list<array<string, mixed>>
     */
    private function fetchChildrenWithCounts(
        string $parentIdBytes,
        string $languageId,
        string $fallbackLanguageId,
        string $salesChannelId,
        string $liveVersionId
    ): array {
        return $this->connection->fetchAllAssociative(
            'SELECT c.id,
                    COALESCE(ct_cur.name, ct_fb.name) AS name,
                    COUNT(DISTINCT p.id) AS product_count
             FROM category c
             LEFT JOIN category_translation ct_cur
                 ON ct_cur.category_id = c.id AND ct_cur.category_version_id = c.version_id AND ct_cur.language_id = :langId
             LEFT JOIN category_translation ct_fb
                 ON ct_fb.category_id = c.id AND ct_fb.category_version_id = c.version_id AND ct_fb.language_id = :fbLangId
             LEFT JOIN product_category_tree pct
                 ON pct.category_id = c.id AND pct.category_version_id = c.version_id
             LEFT JOIN product p
                 ON p.id = pct.product_id AND p.version_id = :verId AND p.active = 1 AND p.parent_id IS NULL
             LEFT JOIN product_visibility pv
                 ON pv.product_id = p.id AND pv.product_version_id = p.version_id AND pv.sales_channel_id = :scId
             WHERE c.parent_id = :parentId
               AND c.version_id = :verId
               AND c.active = 1
               AND c.visible = 1
               AND c.type = :catType
             GROUP BY c.id, name
             HAVING name IS NOT NULL AND name != ""
             ORDER BY name ASC',
            [
                'langId' => $languageId,
                'fbLangId' => $fallbackLanguageId,
                'parentId' => $parentIdBytes,
                'verId' => $liveVersionId,
                'scId' => $salesChannelId,
                'catType' => 'page',
            ]
        );
    }
}

