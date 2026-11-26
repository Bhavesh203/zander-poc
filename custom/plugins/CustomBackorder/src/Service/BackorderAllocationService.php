<?php declare(strict_types=1);

namespace CustomBackorder\Service;

use CustomBackorder\Core\Content\Backorder\BackorderDefinition;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Shopware\Core\Defaults;
use Shopware\Core\Framework\Uuid\Uuid;

class BackorderAllocationService
{
    public function __construct(
        private readonly Connection $connection,
    ) {
    }

    /**
     * Allocates incoming physical stock oldest-first to open/partially-allocated backorders
     * inside a transaction with row-level locking to prevent overselling.
     */
    public function allocateForProduct(string $productId): int
    {
        $productIdBytes = Uuid::fromHexToBytes($productId);
        $totalAllocatedNow = 0;

        $this->connection->transactional(function () use ($productIdBytes, &$totalAllocatedNow): void {
            $productStock = (int) $this->connection->fetchOne(
                'SELECT COALESCE(`stock`, 0) FROM `product` WHERE `id` = :id AND `version_id` = :versionId FOR UPDATE',
                [
                    'id' => $productIdBytes,
                    'versionId' => Uuid::fromHexToBytes(Defaults::LIVE_VERSION),
                ]
            );

            if ($productStock <= 0) {
                return;
            }

            $alreadyReserved = (int) $this->connection->fetchOne(
                'SELECT COALESCE(SUM(GREATEST(0, `quantity_allocated` - `quantity_fulfilled`)), 0)
                 FROM `custom_backorder`
                 WHERE `product_id` = :productId
                   AND `status` IN (:statuses)',
                [
                    'productId' => $productIdBytes,
                    'statuses' => [
                        BackorderDefinition::STATUS_PARTIALLY_ALLOCATED,
                        BackorderDefinition::STATUS_ALLOCATED,
                    ],
                ],
                [
                    'statuses' => ArrayParameterType::STRING,
                ]
            );

            $allocatable = max(0, $productStock - $alreadyReserved);
            if ($allocatable <= 0) {
                return;
            }

            $rows = $this->connection->fetchAllAssociative(
                'SELECT `id`, `quantity_backordered`, `quantity_allocated`
                 FROM `custom_backorder`
                 WHERE `product_id` = :productId
                   AND `status` IN (:statuses)
                 ORDER BY `created_at` ASC
                 FOR UPDATE',
                [
                    'productId' => $productIdBytes,
                    'statuses' => [
                        BackorderDefinition::STATUS_OPEN,
                        BackorderDefinition::STATUS_PARTIALLY_ALLOCATED,
                    ],
                ],
                [
                    'statuses' => ArrayParameterType::STRING,
                ]
            );

            $now = (new \DateTimeImmutable())->format(Defaults::STORAGE_DATE_TIME_FORMAT);

            foreach ($rows as $row) {
                if ($allocatable <= 0) {
                    break;
                }

                $needed = max(0, (int) $row['quantity_backordered'] - (int) $row['quantity_allocated']);
                if ($needed <= 0) {
                    continue;
                }

                $grant = min($needed, $allocatable);
                $newAllocated = (int) $row['quantity_allocated'] + $grant;
                $newStatus = $newAllocated >= (int) $row['quantity_backordered']
                    ? BackorderDefinition::STATUS_ALLOCATED
                    : BackorderDefinition::STATUS_PARTIALLY_ALLOCATED;

                $this->connection->executeStatement(
                    'UPDATE `custom_backorder`
                     SET `quantity_allocated` = :allocated,
                         `status` = :status,
                         `updated_at` = :updatedAt
                     WHERE `id` = :id',
                    [
                        'allocated' => $newAllocated,
                        'status' => $newStatus,
                        'updatedAt' => $now,
                        'id' => $row['id'],
                    ]
                );

                $allocatable -= $grant;
                $totalAllocatedNow += $grant;
            }
        });

        return $totalAllocatedNow;
    }
}

