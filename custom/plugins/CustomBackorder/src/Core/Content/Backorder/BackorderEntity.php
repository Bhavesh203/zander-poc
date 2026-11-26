<?php declare(strict_types=1);

namespace CustomBackorder\Core\Content\Backorder;

use Shopware\Core\Checkout\Order\Aggregate\OrderLineItem\OrderLineItemEntity;
use Shopware\Core\Checkout\Order\OrderEntity;
use Shopware\Core\Content\Product\ProductEntity;
use Shopware\Core\Framework\DataAbstractionLayer\Entity;
use Shopware\Core\Framework\DataAbstractionLayer\EntityIdTrait;

class BackorderEntity extends Entity
{
    use EntityIdTrait;

    protected string $orderId;
    protected string $orderLineItemId;
    protected string $productId;
    protected int $quantityOrdered = 0;
    protected int $quantityPhysical = 0;
    protected int $quantityBackordered = 0;
    protected int $quantityAllocated = 0;
    protected int $quantityFulfilled = 0;
    protected string $status = BackorderDefinition::STATUS_OPEN;
    protected ?\DateTimeInterface $expectedRestockAt = null;
    protected ?OrderEntity $order = null;
    protected ?OrderLineItemEntity $orderLineItem = null;
    protected ?ProductEntity $product = null;

    public function getOrderId(): string
    {
        return $this->orderId;
    }

    public function setOrderId(string $orderId): void
    {
        $this->orderId = $orderId;
    }

    public function getOrderLineItemId(): string
    {
        return $this->orderLineItemId;
    }

    public function setOrderLineItemId(string $orderLineItemId): void
    {
        $this->orderLineItemId = $orderLineItemId;
    }

    public function getProductId(): string
    {
        return $this->productId;
    }

    public function setProductId(string $productId): void
    {
        $this->productId = $productId;
    }

    public function getQuantityOrdered(): int
    {
        return $this->quantityOrdered;
    }

    public function setQuantityOrdered(int $quantityOrdered): void
    {
        $this->quantityOrdered = $quantityOrdered;
    }

    public function getQuantityPhysical(): int
    {
        return $this->quantityPhysical;
    }

    public function setQuantityPhysical(int $quantityPhysical): void
    {
        $this->quantityPhysical = $quantityPhysical;
    }

    public function getQuantityBackordered(): int
    {
        return $this->quantityBackordered;
    }

    public function setQuantityBackordered(int $quantityBackordered): void
    {
        $this->quantityBackordered = $quantityBackordered;
    }

    public function getQuantityAllocated(): int
    {
        return $this->quantityAllocated;
    }

    public function setQuantityAllocated(int $quantityAllocated): void
    {
        $this->quantityAllocated = $quantityAllocated;
    }

    public function getQuantityFulfilled(): int
    {
        return $this->quantityFulfilled;
    }

    public function setQuantityFulfilled(int $quantityFulfilled): void
    {
        $this->quantityFulfilled = $quantityFulfilled;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): void
    {
        $this->status = $status;
    }

    public function getExpectedRestockAt(): ?\DateTimeInterface
    {
        return $this->expectedRestockAt;
    }

    public function setExpectedRestockAt(?\DateTimeInterface $expectedRestockAt): void
    {
        $this->expectedRestockAt = $expectedRestockAt;
    }

    public function getOrder(): ?OrderEntity
    {
        return $this->order;
    }

    public function setOrder(?OrderEntity $order): void
    {
        $this->order = $order;
    }

    public function getOrderLineItem(): ?OrderLineItemEntity
    {
        return $this->orderLineItem;
    }

    public function setOrderLineItem(?OrderLineItemEntity $orderLineItem): void
    {
        $this->orderLineItem = $orderLineItem;
    }

    public function getProduct(): ?ProductEntity
    {
        return $this->product;
    }

    public function setProduct(?ProductEntity $product): void
    {
        $this->product = $product;
    }
}

