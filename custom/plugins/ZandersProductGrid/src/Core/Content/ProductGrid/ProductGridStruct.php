<?php declare(strict_types=1);

namespace ZandersProductGrid\Core\Content\ProductGrid;

use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Framework\Struct\Struct;

class ProductGridStruct extends Struct
{
    protected ?ProductCollection $products = null;

    protected int $total = 0;

    protected int $page = 1;

    protected int $limit = 8;

    protected bool $hasMore = false;

    protected int $columns = 4;

    protected string $loadMode = 'load_more';

    protected ?string $title = null;

    protected string $boxLayout = 'standard';

    protected string $displayMode = 'standard';

    protected ?string $slotId = null;

    protected ?string $streamId = null;

    protected string $mobileLayout = 'slider';

    public function __construct()
    {
        $this->products = new ProductCollection();
    }

    public function getProducts(): ?ProductCollection
    {
        return $this->products;
    }

    public function setProducts(ProductCollection $products): void
    {
        $this->products = $products;
    }

    public function getTotal(): int
    {
        return $this->total;
    }

    public function setTotal(int $total): void
    {
        $this->total = $total;
    }

    public function getPage(): int
    {
        return $this->page;
    }

    public function setPage(int $page): void
    {
        $this->page = $page;
    }

    public function getLimit(): int
    {
        return $this->limit;
    }

    public function setLimit(int $limit): void
    {
        $this->limit = $limit;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function setHasMore(bool $hasMore): void
    {
        $this->hasMore = $hasMore;
    }

    public function getColumns(): int
    {
        return $this->columns;
    }

    public function setColumns(int $columns): void
    {
        $this->columns = $columns;
    }

    public function getLoadMode(): string
    {
        return $this->loadMode;
    }

    public function setLoadMode(string $loadMode): void
    {
        $this->loadMode = $loadMode;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): void
    {
        $this->title = $title;
    }

    public function getBoxLayout(): string
    {
        return $this->boxLayout;
    }

    public function setBoxLayout(string $boxLayout): void
    {
        $this->boxLayout = $boxLayout;
    }

    public function getDisplayMode(): string
    {
        return $this->displayMode;
    }

    public function setDisplayMode(string $displayMode): void
    {
        $this->displayMode = $displayMode;
    }

    public function getSlotId(): ?string
    {
        return $this->slotId;
    }

    public function setSlotId(?string $slotId): void
    {
        $this->slotId = $slotId;
    }

    public function getStreamId(): ?string
    {
        return $this->streamId;
    }

    public function setStreamId(?string $streamId): void
    {
        $this->streamId = $streamId;
    }

    public function getMobileLayout(): string
    {
        return $this->mobileLayout;
    }

    public function setMobileLayout(string $mobileLayout): void
    {
        $this->mobileLayout = $mobileLayout;
    }

    public function getApiAlias(): string
    {
        return 'cms_product_grid';
    }
}

