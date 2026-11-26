<?php declare(strict_types=1);

namespace CustomBackorder\Core\Checkout;

use CustomBackorder\Service\BackorderCalculator;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartValidatorInterface;
use Shopware\Core\Checkout\Cart\Error\ErrorCollection;
use Shopware\Core\Checkout\Cart\Error\GenericCartError;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BackorderCartValidator implements CartValidatorInterface
{
    public function validate(Cart $cart, ErrorCollection $errors, SalesChannelContext $context): void
    {
        foreach ($cart->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $payload = $lineItem->getPayloadValue('customBackorder');
            if (!\is_array($payload)) {
                continue;
            }

            if ((bool) ($payload['allowed'] ?? true)) {
                continue;
            }

            $reason = $payload['errorReason'] ?? null;
            $label = $lineItem->getLabel() ?? $lineItem->getId();

            if ($reason === BackorderCalculator::REASON_LIMIT_EXCEEDED) {
                $errors->add(new GenericCartError(
                    'custom-backorder-limit-' . $lineItem->getId(),
                    'customBackorder.error.limitExceeded',
                    ['%product%' => $label],
                    20,
                    false,
                    false,
                    true
                ));
            } elseif ($reason === BackorderCalculator::REASON_MIXED_NOT_ALLOWED) {
                $errors->add(new GenericCartError(
                    'custom-backorder-mixed-' . $lineItem->getId(),
                    'customBackorder.error.mixedNotAllowed',
                    ['%product%' => $label],
                    20,
                    false,
                    false,
                    true
                ));
            }
        }
    }
}

