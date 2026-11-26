<?php declare(strict_types=1);

namespace CustomBackorder\Subscriber;

use CustomBackorder\Service\BackorderManager;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\System\StateMachine\Event\StateMachineTransitionEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class OrderCancelledSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly BackorderManager $backorderManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            StateMachineTransitionEvent::class => 'onOrderStateTransition',
        ];
    }

    public function onOrderStateTransition(StateMachineTransitionEvent $event): void
    {
        if ($event->getEntityName() !== OrderDefinition::ENTITY_NAME) {
            return;
        }

        if ($event->getToPlace()->getTechnicalName() !== 'cancelled') {
            return;
        }

        $this->backorderManager->cancelForOrder($event->getEntityId());
    }
}

