<?php declare(strict_types=1);

namespace CustomBackorder\Core\Content\Backorder;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemDefinition;
use Shopware\Core\Checkout\Order\OrderDefinition;
use Shopware\Core\Content\Product\ProductDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\EntityDefinition;
use Shopware\Core\Framework\DataAbstractionLayer\Field\DateTimeField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\FkField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\PrimaryKey;
use Shopware\Core\Framework\DataAbstractionLayer\Field\Flag\Required;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IdField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\IntField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ManyToOneAssociationField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\ReferenceVersionField;
use Shopware\Core\Framework\DataAbstractionLayer\Field\StringField;
use Shopware\Core\Framework\DataAbstractionLayer\FieldCollection;

class BackorderDefinition extends EntityDefinition
{
    public const ENTITY_NAME = 'custom_backorder';

    public const STATUS_OPEN = 'open';
    public const STATUS_PARTIALLY_ALLOCATED = 'partially_allocated';
    public const STATUS_ALLOCATED = 'allocated';
    public const STATUS_FULFILLED = 'fulfilled';
    public const STATUS_CANCELLED = 'cancelled';

    public function getEntityName(): string
    {
        return self::ENTITY_NAME;
    }

    public function getEntityClass(): string
    {
        return BackorderEntity::class;
    }

    public function getCollectionClass(): string
    {
        return BackorderCollection::class;
    }

    protected function defineFields(): FieldCollection
    {
        return new FieldCollection([
            (new IdField('id', 'id'))->addFlags(new PrimaryKey(), new Required()),

            (new FkField('order_id', 'orderId', OrderDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderDefinition::class))->addFlags(new Required()),

            (new FkField('order_line_item_id', 'orderLineItemId', OrderLineItemDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(OrderLineItemDefinition::class))->addFlags(new Required()),

            (new FkField('product_id', 'productId', ProductDefinition::class))->addFlags(new Required()),
            (new ReferenceVersionField(ProductDefinition::class))->addFlags(new Required()),

            (new IntField('quantity_ordered', 'quantityOrdered'))->addFlags(new Required()),
            (new IntField('quantity_physical', 'quantityPhysical'))->addFlags(new Required()),
            (new IntField('quantity_backordered', 'quantityBackordered'))->addFlags(new Required()),
            (new IntField('quantity_allocated', 'quantityAllocated'))->addFlags(new Required()),
            (new IntField('quantity_fulfilled', 'quantityFulfilled'))->addFlags(new Required()),

            (new StringField('status', 'status'))->addFlags(new Required()),
            new DateTimeField('expected_restock_at', 'expectedRestockAt'),

            new ManyToOneAssociationField('order', 'order_id', OrderDefinition::class, 'id', false),
            new ManyToOneAssociationField('orderLineItem', 'order_line_item_id', OrderLineItemDefinition::class, 'id', false),
            new ManyToOneAssociationField('product', 'product_id', ProductDefinition::class, 'id', false),
        ]);
    }
}

