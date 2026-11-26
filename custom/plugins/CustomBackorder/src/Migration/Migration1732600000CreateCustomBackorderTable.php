<?php declare(strict_types=1);

namespace CustomBackorder\Migration;

use Doctrine\DBAL\Connection;
use Shopware\Core\Framework\Migration\MigrationStep;

class Migration1732600000CreateCustomBackorderTable extends MigrationStep
{
    public function getCreationTimestamp(): int
    {
        return 1732600000;
    }

    public function update(Connection $connection): void
    {
        $connection->executeStatement('
            CREATE TABLE IF NOT EXISTS `custom_backorder` (
                `id` BINARY(16) NOT NULL,
                `order_id` BINARY(16) NOT NULL,
                `order_version_id` BINARY(16) NOT NULL,
                `order_line_item_id` BINARY(16) NOT NULL,
                `order_line_item_version_id` BINARY(16) NOT NULL,
                `product_id` BINARY(16) NOT NULL,
                `product_version_id` BINARY(16) NOT NULL,
                `quantity_ordered` INT NOT NULL DEFAULT 0,
                `quantity_physical` INT NOT NULL DEFAULT 0,
                `quantity_backordered` INT NOT NULL DEFAULT 0,
                `quantity_allocated` INT NOT NULL DEFAULT 0,
                `quantity_fulfilled` INT NOT NULL DEFAULT 0,
                `status` VARCHAR(32) NOT NULL DEFAULT \'open\',
                `expected_restock_at` DATETIME(3) NULL,
                `created_at` DATETIME(3) NOT NULL,
                `updated_at` DATETIME(3) NULL,
                PRIMARY KEY (`id`),
                UNIQUE KEY `uniq.custom_backorder.order_line_item` (`order_line_item_id`, `order_line_item_version_id`),
                KEY `idx.custom_backorder.product_status_created` (`product_id`, `status`, `created_at`),
                KEY `idx.custom_backorder.order` (`order_id`, `order_version_id`)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
        ');
    }

    public function updateDestructive(Connection $connection): void
    {
    }
}

