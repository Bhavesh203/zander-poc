<?php declare(strict_types=1);

namespace CustomBackorder\Core\Checkout;

use CustomBackorder\CustomBackorder;
use CustomBackorder\Service\BackorderCalculator;
use CustomBackorder\Service\BackorderManager;
use Shopware\Core\Checkout\Cart\Cart;
use Shopware\Core\Checkout\Cart\CartBehavior;
use Shopware\Core\Checkout\Cart\CartProcessorInterface;
use Shopware\Core\Checkout\Cart\LineItem\CartDataCollection;
use Shopware\Core\Checkout\Cart\LineItem\LineItem;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\System\SalesChannel\SalesChannelContext;

class BackorderCartProcessor implements CartProcessorInterface
{
    public function __construct(
        private readonly BackorderCalculator $calculator,
        private readonly BackorderManager $backorderManager,
    ) {
    }

    public function process(
        CartDataCollection $data,
        Cart $original,
        Cart $toCalculate,
        SalesChannelContext $context,
        CartBehavior $behavior
    ): void {
        foreach ($toCalculate->getLineItems()->filterFlatByType(LineItem::PRODUCT_LINE_ITEM_TYPE) as $lineItem) {
            $productId = $lineItem->getReferencedId();
            if (!$productId) {
                continue;
            }

            /** @var ProductEntity|null $product */
            $product = $data->get('product-' . $productId);
            if (!$product instanceof ProductEntity) {
                continue;
            }

            $customFields = $product->getTranslation('customFields') ?? $product->getCustomFields() ?? [];
            $backorderEnabled = (bool) ($customFields[CustomBackorder::FIELD_ENABLED] ?? false);
            $allowMixed = \array_key_exists(CustomBackorder::FIELD_ALLOW_MIXED, $customFields)
                ? (bool) $customFields[CustomBackorder::FIELD_ALLOW_MIXED]
                : true;
            $limitRaw = $customFields[CustomBackorder::FIELD_LIMIT] ?? null;
            $limit = ($limitRaw !== null && $limitRaw !== '') ? (int) $limitRaw : null;
            $message = $customFields[CustomBackorder::FIELD_MESSAGE] ?? null;
            $expectedRestockAt = $customFields[CustomBackorder::FIELD_EXPECTED_RESTOCK] ?? null;

            $physicalStock = (int) $product->getStock();
            $reserved = $this->backorderManager->getReservedAllocatedQuantity($productId);
            $openBackorders = $this->backorderManager->getOpenBackorderQuantity($productId);

            $result = $this->calculator->calculate(
                physicalStock: $physicalStock,
                reservedForBackorders: $reserved,
                requestedQty: $lineItem->getQuantity(),
                backorderEnabled: $backorderEnabled,
                allowMixed: $allowMixed,
                backorderLimit: $limit,
                openBackorderQtyForProduct: $openBackorders
            );

            $lineItem->setPayloadValue('customBackorder', [
                'enabled' => $backorderEnabled,
                'allowed' => $result->allowed,
                'errorReason' => $result->errorReason,
                'quantityPhysical' => $result->physicalQty,
                'quantityBackordered' => $result->backorderQty,
                'isBackordered' => $result->isBackordered(),
                'message' => $message,
                'expectedRestockAt' => $expectedRestockAt,
            ]);
        }
    }
}

