<?php declare(strict_types=1);

namespace CustomBackorder\Service;

use CustomBackorder\Core\Content\Backorder\BackorderDefinition;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Context;
use Shopware\Core\Framework\Uuid\Uuid;

class BackorderManager
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Returns total open/unfulfilled backordered units for a product.
     */
    public function getOpenBackorderQuantity(string $productId): int
    {
        $qty = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(GREATEST(0, quantity_backordered - quantity_fulfilled)), 0)
             FROM `custom_backorder`
             WHERE `product_id` = :productId
               AND `status` IN (:statuses)',
            [
                'productId' => Uuid::fromHexToBytes($productId),
                'statuses' => [
                    BackorderDefinition::STATUS_OPEN,
                    BackorderDefinition::STATUS_PARTIALLY_ALLOCATED,
                    BackorderDefinition::STATUS_ALLOCATED,
                ],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
            ]
        );

        return (int) $qty;
    }

    /**
     * Returns total units currently allocated (reserved) but not yet fulfilled for a product.
     */
    public function getReservedAllocatedQuantity(string $productId): int
    {
        $qty = $this->connection->fetchOne(
            'SELECT COALESCE(SUM(GREATEST(0, quantity_allocated - quantity_fulfilled)), 0)
             FROM `custom_backorder`
             WHERE `product_id` = :productId
               AND `status` IN (:statuses)',
            [
                'productId' => Uuid::fromHexToBytes($productId),
                'statuses' => [
                    BackorderDefinition::STATUS_PARTIALLY_ALLOCATED,
                    BackorderDefinition::STATUS_ALLOCATED,
                ],
            ],
            [
                'statuses' => ArrayParameterType::STRING,
            ]
        );

        return (int) $qty;
    }

    /**
     * Persists backorder records idempotently for an order's line items.
     */
    public function createForOrder(OrderEntity $order, Context $context): void
    {
        $lineItems = $order->getLineItems();
        if ($lineItems === null || $lineItems->count() === 0) {
            return;
        }

        $orderIdBytes = Uuid::fromHexToBytes($order->getId());
        $orderVersionBytes = Uuid::fromHexToBytes($order->getVersionId() ?? Defaults::LIVE_VERSION);

        $this->connection->transactional(function () use ($lineItems, $orderIdBytes, $orderVersionBytes): void {
            $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            /** @var OrderLineItemEntity $lineItem */
            foreach ($lineItems as $lineItem) {
                if ($lineItem->getType() !== 'product' || !$lineItem->getProductId()) {
                    continue;
                }

                $payload = $lineItem->getPayload() ?? [];
                $backorderData = $payload['customBackorder'] ?? null;
                if (!\is_array($backorderData)) {
                    continue;
                }

                $backorderQty = (int) ($backorderData['quantityBackordered'] ?? 0);
                if ($backorderQty <= 0) {
                    continue;
                }

                $physicalQty = (int) ($backorderData['quantityPhysical'] ?? 0);
                $expectedRestockAt = $backorderData['expectedRestockAt'] ?? null;
                $restockFormatted = null;
                if (\is_string($expectedRestockAt) && $expectedRestockAt !== '') {
                    try {
                        $restockFormatted = (new \DateTimeImmutable($expectedRestockAt))->format(Defaults::STORAGE_DATE_TIME_FORMAT);
                    } catch (\Throwable) {
                        $restockFormatted = null;
                    }
                }

                $lineItemIdBytes = Uuid::fromHexToBytes($lineItem->getId());
                $lineItemVersionBytes = Uuid::fromHexToBytes($lineItem->getVersionId() ?? Defaults::LIVE_VERSION);
                $productIdBytes = Uuid::fromHexToBytes($lineItem->getProductId());
                $productVersionBytes = Uuid::fromHexToBytes($lineItem->getProductVersionId() ?? Defaults::LIVE_VERSION);

                // Idempotent upsert keyed by unique (order_line_item_id, order_line_item_version_id)
                $this->connection->executeStatement(
                    'INSERT INTO `custom_backorder` (
                        `id`, `order_id`, `order_version_id`,
                        `order_line_item_id`, `order_line_item_version_id`,
                        `product_id`, `product_version_id`,
                        `quantity_ordered`, `quantity_physical`, `quantity_backordered`,
                        `quantity_allocated`, `quantity_fulfilled`, `status`,
                        `expected_restock_at`, `created_at`
                    ) VALUES (
                        :id, :orderId, :orderVersionId,
                        :lineItemId, :lineItemVersionId,
                        :productId, :productVersionId,
                        :qtyOrdered, :qtyPhysical, :qtyBackordered,
                        0, 0, :status,
                        :expectedRestockAt, :createdAt
                    ) ON DUPLICATE KEY UPDATE
                        `quantity_ordered` = VALUES(`quantity_ordered`),
                        `quantity_physical` = VALUES(`quantity_physical`),
                        `quantity_backordered` = VALUES(`quantity_backordered`),
                        `expected_restock_at` = VALUES(`expected_restock_at`),
                        `updated_at` = :updatedAt',
                    [
                        'id' => Uuid::randomBytes(),
                        'orderId' => $orderIdBytes,
                        'orderVersionId' => $orderVersionBytes,
                        'lineItemId' => $lineItemIdBytes,
                        'lineItemVersionId' => $lineItemVersionBytes,
                        'productId' => $productIdBytes,
                        'productVersionId' => $productVersionBytes,
                        'qtyOrdered' => $lineItem->getQuantity(),
                        'qtyPhysical' => $physicalQty,
                        'qtyBackordered' => $backorderQty,
                        'status' => BackorderDefinition::STATUS_OPEN,
                        'expectedRestockAt' => $restockFormatted,
                        'createdAt' => $now,
                        'updatedAt' => $now,
                    ]
                );
            }
        });
    }

    /**
     * Cancels all unfulfilled backorder records when an order is cancelled.
     */
    public function cancelForOrder(string $orderId): void
    {
        $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

        $this->connection->executeStatement(
            'UPDATE `custom_backorder`
             SET `status` = :cancelledStatus,
                 `quantity_allocated` = `quantity_fulfilled`,
                 `updated_at` = :updatedAt
             WHERE `order_id` = :orderId
               AND `status` NOT IN (:finalStatuses)',
            [
                'cancelledStatus' => BackorderDefinition::STATUS_CANCELLED,
                'updatedAt' => $now,
                'orderId' => Uuid::fromHexToBytes($orderId),
                'finalStatuses' => [
                    BackorderDefinition::STATUS_FULFILLED,
                    BackorderDefinition::STATUS_CANCELLED,
                ],
            ],
            [
                'finalStatuses' => ArrayParameterType::STRING,
            ]
        );
    }
}

