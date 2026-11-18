<?php declare(strict_types=1);

namespace ZandersProductGrid\Subscriber;

use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Page\Product\ProductPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads up to 12 alternative products from the same category tree on the
 * Product Detail Page and attaches them as `zandersAlternativeProducts`.
 */
class AlternativeProductsSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_KEY = 'zandersAlternativeProducts';

    public function __construct(
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductPageLoadedEvent::class => 'onProductPageLoaded',
        ];
    }

    public function onProductPageLoaded(ProductPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $product = $page->getProduct();
        $context = $event->getSalesChannelContext();

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
        $criteria->setLimit(12);
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new ProductAvailableFilter(
            $context->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
            new EqualsAnyFilter('id', $excludeIds),
        ]));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        if ($categoryIds !== []) {
            $criteria->addFilter(new EqualsAnyFilter('categories.id', $categoryIds));
        }

        $result = $this->productRepository->search($criteria, $context);

        // Fallback: if category had no other items, pull recent active storefront products
        if ($result->getTotal() === 0 && $categoryIds !== []) {
            $fallbackCriteria = new Criteria();
            $fallbackCriteria->setLimit(8);
            $fallbackCriteria->addAssociation('cover.media');
            $fallbackCriteria->addAssociation('manufacturer');
            $fallbackCriteria->addFilter(new ProductAvailableFilter(
                $context->getSalesChannelId(),
                ProductVisibilityDefinition::VISIBILITY_ALL
            ));
            $fallbackCriteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('id', $excludeIds),
            ]));
            $fallbackCriteria->addFilter(new EqualsFilter('parentId', null));

            $result = $this->productRepository->search($fallbackCriteria, $context);
        }

        $page->addExtension(self::EXTENSION_KEY, new ArrayStruct([
            'products' => $result->getEntities(),
        ]));
    }
}
