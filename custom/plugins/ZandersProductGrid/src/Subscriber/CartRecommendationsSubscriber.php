<?php declare(strict_types=1);

namespace ZandersProductGrid\Subscriber;

use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\NotFilter;
use Shopware\Core\Framework\Struct\ArrayStruct;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Storefront\Page\Checkout\Cart\CheckoutCartPageLoadedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Loads up to 8 recommended products for the "YOU MIGHT ALSO LIKE:" section
 * on the Shopping Cart page, excluding products already in the cart.
 */
class CartRecommendationsSubscriber implements EventSubscriberInterface
{
    public const EXTENSION_KEY = 'zandersCartRecommendations';

    public function __construct(
        private readonly SalesChannelRepository $productRepository,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutCartPageLoadedEvent::class => 'onCartPageLoaded',
        ];
    }

    public function onCartPageLoaded(CheckoutCartPageLoadedEvent $event): void
    {
        $page = $event->getPage();
        $context = $event->getSalesChannelContext();

        $excludeIds = [];
        foreach ($page->getCart()->getLineItems() as $lineItem) {
            if ($lineItem->getType() === LineItem::PRODUCT_LINE_ITEM_TYPE && $lineItem->getReferencedId()) {
                $excludeIds[] = $lineItem->getReferencedId();
            }
        }

        $criteria = new Criteria();
        $criteria->setLimit(8);
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('manufacturer');
        $criteria->addFilter(new ProductAvailableFilter(
            $context->getSalesChannelId(),
            ProductVisibilityDefinition::VISIBILITY_ALL
        ));
        $criteria->addFilter(new EqualsFilter('parentId', null));

        if ($excludeIds !== []) {
            $criteria->addFilter(new NotFilter(NotFilter::CONNECTION_AND, [
                new EqualsAnyFilter('id', array_values(array_unique($excludeIds))),
            ]));
        }

        $result = $this->productRepository->search($criteria, $context);

        $page->addExtension(self::EXTENSION_KEY, new ArrayStruct([
            'products' => $result->getEntities(),
        ]));
    }
}

