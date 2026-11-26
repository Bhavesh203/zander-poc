<?php declare(strict_types=1);

namespace CustomBackorder\Subscriber;

use CustomBackorder\Service\BackorderAllocationService;
use Shopware\Core\Content\Product\ProductEvents;
use Shopware\Core\Framework\DataAbstractionLayer\Event\EntityWrittenEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class StockUpdatedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BackorderAllocationService $allocationService,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            ProductEvents::PRODUCT_WRITTEN_EVENT => 'onProductWritten',
        ];
    }

    public function onProductWritten(EntityWrittenEvent $event): void
    {
        foreach ($event->getWriteResults() as $writeResult) {
            $payload = $writeResult->getPayload();
            if (!\array_key_exists('stock', $payload)) {
                continue;
            }

            $productId = $writeResult->getPrimaryKey();
            if (\is_string($productId) && $productId !== '') {
                $this->allocationService->allocateForProduct($productId);
            }
        }
    }
}

