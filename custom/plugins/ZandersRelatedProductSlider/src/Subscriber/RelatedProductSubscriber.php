<?php declare(strict_types=1);

namespace ZandersRelatedProductSlider\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Content\Product\SalesChannel\Review\AbstractProductReviewLoader;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SystemConfig\SystemConfigService;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class RelatedProductSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_KEY = 'zandersRelatedProducts';
    public const LEGACY_EXTENSION_KEY = 'zandersAlternativeProducts';

    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductReviewLoader $productReviewLoader,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => ['onProductPageLoaded', 10],
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $context = $event->getSalesChannelContext();
        $salesChannelId = $context->getSalesChannelId();

        if ($this->systemConfigService->getBool('core.listing.showReview', $salesChannelId)) {
            try {
                $reviews = $this->productReviewLoader->load(
                    $event->getRequest(),
                    $context,
                    $product->getId(),
                    $product->getParentId()
                );
                $page->addExtension('zandersProductReviews', $reviews);
            } catch (\Throwable) {
                // Reviews disabled or unavailable for this product
            }
        }

        $isActive = $this->systemConfigService->getBool('ZandersRelatedProductSlider.config.active', $salesChannelId);
        if (!$isActive) {
            return;
        }

        $title = $this->systemConfigService->getString('ZandersRelatedProductSlider.config.title', $salesChannelId) ?: 'RELATED PRODUCTS';
        $limit = $this->systemConfigService->getInt('ZandersRelatedProductSlider.config.limit', $salesChannelId);
        if ($limit <= 0) {
            $limit = 12;
        }
        $showStockToggle = $this->systemConfigService->get('ZandersRelatedProductSlider.config.showStockToggle', $salesChannelId) ?? true;
        $autoplay = $this->systemConfigService->getBool('ZandersRelatedProductSlider.config.autoplay', $salesChannelId);
        $autoplayTimeout = $this->systemConfigService->getInt('ZandersRelatedProductSlider.config.autoplayTimeout', $salesChannelId) ?: 4000;
        $speed = $this->systemConfigService->getInt('ZandersRelatedProductSlider.config.speed', $salesChannelId) ?: 350;

        $excludeIds = [$product->getId()];
        if ($product->getParentId()) {
            $excludeIds[] = $product->getParentId();
        }

        $categoryIds = [];
        $seoCategory = $product->getSeoCategory();
        if ($seoCategory !== null) {
            $categoryIds[] = $seoCategory->getId();
        }
        $directCategoryIds = $product->getCategoryIds();
        if (\is_array($directCategoryIds) && $directCategoryIds !== []) {
            $categoryIds = array_values(array_unique(array_merge($categoryIds, $directCategoryIds)));
        }

        $criteria = new Criteria();
        $criteria->setLimit($limit);
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('prices');
        $criteria->addFilter(new ProductAvailableFilter(
            $salesChannelId,
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsAnyFilter('id', $excludeIds),
        ]));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        if ($categoryIds !== []) {
            $criteria->addFilter(new EqualsAnyFilter('categories.id', $categoryIds));
        }

        // Prioritize in-stock products first, then recently updated
        $criteria->addSorting(new FieldSorting('availableStock', FieldSorting::DESCENDING));
        $criteria->addSorting(new FieldSorting('updatedAt', FieldSorting::DESCENDING));

        $result = $this->productRepository->search($criteria, $context);

        // Fallback: If category has no other products, fetch top active storefront products
        if ($result->getTotal() === 0) {
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->setLimit(min(8, $limit));
            $fallbackCriteria->addAssociation('cover.media');
            $fallbackCriteria->addAssociation('manufacturer');
            $fallbackCriteria->addAssociation('prices');
            $fallbackCriteria->addFilter(new ProductAvailableFilter(
                $salesChannelId,
                ProductVisibilityDefinition::VISIBILITY_ALL
            ));
            $fallbackCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('id', $excludeIds),
            ]));
            $fallbackCriteria->addFilter(new EqualsFilter('parentId', null));
            $fallbackCriteria->addSorting(new FieldSorting('availableStock', FieldSorting::DESCENDING));

            $result = $this->productRepository->search($fallbackCriteria, $context);
        }

        $extensionData = new ArrayStruct([
            'products' => $result->getEntities(),
            'title' => $title,
            'config' => [
                'active' => $isActive,
                'title' => $title,
                'limit' => $limit,
                'showStockToggle' => (bool) $showStockToggle,
                'autoplay' => $autoplay,
                'autoplayTimeout' => $autoplayTimeout,
                'speed' => $speed,
            ],
        ]);

        $page->addExtension(self::EXTENSION_KEY, $extensionData);
        // Also keep legacy extension key so existing references continue to work seamlessly
        $page->addExtension(self::LEGACY_EXTENSION_KEY, new ArrayStruct([
            'products' => $result->getEntities(),
            'title' => $title,
        ]));
    }
}

