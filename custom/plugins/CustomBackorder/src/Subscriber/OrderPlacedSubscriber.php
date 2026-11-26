<?php declare(strict_types=1);

namespace CustomBackorder\Subscriber;

use CustomBackorder\Service\BackorderManager;
use Shopware\Core\Checkout\Cart\Event\CheckoutOrderPlacedEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderPlacedSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BackorderManager $backorderManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            CheckoutOrderPlacedEvent::class => 'onOrderPlaced',
        ];
    }

    public function onOrderPlaced(CheckoutOrderPlacedEvent $event): void
    {
        $this->backorderManager->createForOrder($event->getOrder(), $event->getContext());
    }
}

