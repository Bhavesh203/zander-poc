<?php declare(strict_types=1);

namespace ZandersProductGrid\Core\Content\ProductGrid;

use Psr\Log\LoggerInterface;
use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\FieldConfigCollection;
use Shopware\Core\Content\Product\ProductCollection;
use Shopware\Core\Content\Product\SalesChannel\AbstractProductCloseoutFilterFactory;
use Shopware\Core\Content\ProductStream\Exception\EmptyProductStreamException;
use Shopware\Core\Content\ProductStream\Exception\NoFilterException;
use Shopware\Core\Content\ProductStream\Service\AbstractProductStreamBuilder;
use Shopware\Core\Content\ProductStream\Service\ProductStreamBuilderInterface;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Exception\EntityNotFoundException;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Sorting\FieldSorting;
use Shopware\Core\Framework\Uuid\Uuid;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Core\System\SystemConfig\SystemConfigService;

class ProductGridLoader
{
    public const DEFAULT_LIMIT = 8;
    public const DEFAULT_COLUMNS = 4;

    /**
     * @param SalesChannelRepository<ProductCollection> $productRepository
     * @param EntityRepository $cmsSlotRepository
     */
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $cmsSlotRepository,
        private readonly ProductStreamBuilderInterface|AbstractProductStreamBuilder $productStreamBuilder,
        private readonly SystemConfigService $systemConfigService,
        private readonly AbstractProductCloseoutFilterFactory $productCloseoutFilterFactory,
        private readonly LoggerInterface $logger
    ) {
    }

    /**
     * Load products by slot ID from database
     */
    public function loadBySlot(string $slotId, int $page, SalesChannelContext $context): ProductGridStruct
    {
        if (!Uuid::isValid($slotId)) {
            $struct = new ProductGridStruct();
            $struct->setSlotId($slotId);
            return $struct;
        }

        $criteria = new Criteria([$slotId]);
        $slot = $this->cmsSlotRepository->search($criteria, $context->getContext())->first();

        if (!$slot instanceof CmsSlotEntity) {
            $this->logger->warning(sprintf('Product grid slot "%s" not found', $slotId));
            $struct = new ProductGridStruct();
            $struct->setSlotId($slotId);
            return $struct;
        }

        $config = $slot->getTranslation('config') ?? $slot->getConfig() ?? [];

        return $this->load($config, $page, $context, $slotId);
    }

    /**
     * Load products based on config array or FieldConfigCollection
     */
    public function load(
        array|FieldConfigCollection $config,
        int $page,
        SalesChannelContext $context,
        ?string $slotId = null
    ): ProductGridStruct {
        $gridStruct = new ProductGridStruct();
        $gridStruct->setSlotId($slotId);
        $gridStruct->setPage(max(1, $page));

        $title = (string) $this->extractValue($config, 'title', '');
        $limit = (int) $this->extractValue($config, 'productLimit', self::DEFAULT_LIMIT);
        if ($limit <= 0) {
            $limit = (int) $this->extractValue($config, 'limit', self::DEFAULT_LIMIT);
        }
        if ($limit <= 0) {
            $limit = self::DEFAULT_LIMIT;
        }

        $columns = (int) $this->extractValue($config, 'columns', self::DEFAULT_COLUMNS);
        if ($columns <= 0) {
            $columns = self::DEFAULT_COLUMNS;
        }

        $loadMode = (string) $this->extractValue($config, 'loadMode', 'load_more');
        $mobileLayout = (string) $this->extractValue($config, 'mobileLayout', 'slider');
        $boxLayout = (string) $this->extractValue($config, 'boxLayout', 'standard');
        $displayMode = (string) $this->extractValue($config, 'displayMode', 'standard');

        $gridStruct->setTitle($title !== '' ? $title : null);
        $gridStruct->setLimit($limit);
        $gridStruct->setColumns($columns);
        $gridStruct->setLoadMode($loadMode);
        $gridStruct->setMobileLayout($mobileLayout);
        $gridStruct->setBoxLayout($boxLayout);
        $gridStruct->setDisplayMode($displayMode);

        $source = (string) $this->extractSource($config, 'products', 'static');

        if ($source === 'product_stream') {
            $this->loadFromProductStream($gridStruct, $config, $page, $limit, $context);
        } else {
            $this->loadFromStaticProducts($gridStruct, $config, $page, $limit, $context);
        }

        return $gridStruct;
    }

    private function loadFromStaticProducts(
        ProductGridStruct $gridStruct,
        array|FieldConfigCollection $config,
        int $page,
        int $limit,
        SalesChannelContext $context
    ): void {
        $rawProductIds = $this->extractValue($config, 'products', []);

        if (!is_array($rawProductIds)) {
            $rawProductIds = $rawProductIds ? [$rawProductIds] : [];
        }

        $productIds = array_values(array_filter(array_unique($rawProductIds)));
        $total = count($productIds);
        $gridStruct->setTotal($total);

        if ($total === 0) {
            $gridStruct->setProducts(new ProductCollection());
            $gridStruct->setHasMore(false);
            return;
        }

        $offset = ($page - 1) * $limit;
        if ($offset >= $total) {
            $gridStruct->setProducts(new ProductCollection());
            $gridStruct->setHasMore(false);
            return;
        }

        $pageProductIds = array_slice($productIds, $offset, $limit);

        $criteria = new Criteria($pageProductIds);
        $this->addStandardAssociations($criteria);

        if ($this->shouldHideUnavailableProducts($context)) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
        }

        $result = $this->productRepository->search($criteria, $context);
        $products = $result->getEntities();

        // Preserve manually configured order
        $products->sortByIdArray($pageProductIds);

        $gridStruct->setProducts($products);
        $gridStruct->setHasMore(($offset + count($pageProductIds)) < $total);
    }

    private function loadFromProductStream(
        ProductGridStruct $gridStruct,
        array|FieldConfigCollection $config,
        int $page,
        int $limit,
        SalesChannelContext $context
    ): void {
        $streamId = (string) $this->extractValue($config, 'products', '');
        $gridStruct->setStreamId($streamId);

        if ($streamId === '') {
            $gridStruct->setProducts(new ProductCollection());
            $gridStruct->setHasMore(false);
            return;
        }

        $criteria = new Criteria();
        $criteria->addState(Criteria::STATE_ELASTICSEARCH_AWARE);
        $this->addStandardAssociations($criteria);

        try {
            if ($this->productStreamBuilder instanceof AbstractProductStreamBuilder) {
                $this->productStreamBuilder->enrichCriteria($criteria, $streamId, $context->getContext());
            } else {
                $criteria->addFilter(...$this->productStreamBuilder->buildFilters($streamId, $context->getContext()));
            }
        } catch (EntityNotFoundException $e) {
            $this->logger->warning(sprintf('Product stream "%s" not found for CMS product grid: %s', $streamId, $e->getMessage()));
            $gridStruct->setProducts(new ProductCollection());
            $gridStruct->setHasMore(false);
            return;
        } catch (EmptyProductStreamException|NoFilterException $e) {
            $this->logger->notice(sprintf('Product stream "%s" has no filters: %s', $streamId, $e->getMessage()));
        }

        if ($this->shouldHideUnavailableProducts($context)) {
            $criteria->addFilter($this->productCloseoutFilterFactory->create($context));
        }

        // Apply sorting
        $sorting = (string) $this->extractValue($config, 'productStreamSorting', 'name:ASC');
        if ($sorting === 'random') {
            $criteria->addSorting(new FieldSorting('random()'));
        } elseif (str_contains($sorting, ':')) {
            [$field, $direction] = explode(':', $sorting, 2);
            $criteria->addSorting(new FieldSorting($field, strtoupper($direction) === 'DESC' ? FieldSorting::DESCENDING : FieldSorting::ASCENDING));
        } else {
            $criteria->addSorting(new FieldSorting('name', FieldSorting::ASCENDING));
        }

        // Check if stream max limit is configured
        $streamLimit = (int) $this->extractValue($config, 'productStreamLimit', 0);

        $offset = ($page - 1) * $limit;

        if ($streamLimit > 0 && $offset >= $streamLimit) {
            $gridStruct->setProducts(new ProductCollection());
            $gridStruct->setHasMore(false);
            return;
        }

        // Adjust batch limit if close to streamLimit
        $currentBatchLimit = $limit;
        if ($streamLimit > 0 && ($offset + $limit) > $streamLimit) {
            $currentBatchLimit = max(0, $streamLimit - $offset);
        }

        $criteria->setOffset($offset);
        $criteria->setLimit($currentBatchLimit);
        $criteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

        $result = $this->productRepository->search($criteria, $context);
        $dbTotal = $result->getTotal();

        $effectiveTotal = $streamLimit > 0 ? min($dbTotal, $streamLimit) : $dbTotal;
        $gridStruct->setTotal($effectiveTotal);

        $products = $result->getEntities();
        $gridStruct->setProducts($products);

        $loadedSoFar = $offset + $products->count();
        $gridStruct->setHasMore($loadedSoFar < $effectiveTotal);
    }

    private function addStandardAssociations(Criteria $criteria): void
    {
        $criteria->addAssociation('cover.media');
        $criteria->addAssociation('options.group');
        $criteria->addAssociation('manufacturer');
        $criteria->addAssociation('prices');
    }

    private function shouldHideUnavailableProducts(SalesChannelContext $context): bool
    {
        return $this->systemConfigService->getBool(
            'core.listing.hideCloseoutProductsWhenOutOfStock',
            $context->getSalesChannelId()
        );
    }

    private function extractValue(array|FieldConfigCollection $config, string $key, mixed $default = null): mixed
    {
        if ($config instanceof FieldConfigCollection) {
            $field = $config->get($key);
            return $field !== null ? $field->getValue() : $default;
        }

        if (isset($config[$key])) {
            $item = $config[$key];
            if (is_array($item) && array_key_exists('value', $item)) {
                return $item['value'];
            }
            return $item;
        }

        return $default;
    }

    private function extractSource(array|FieldConfigCollection $config, string $key, string $default = 'static'): string
    {
        if ($config instanceof FieldConfigCollection) {
            $field = $config->get($key);
            return $field !== null ? ($field->getSource() ?? $default) : $default;
        }

        if (isset($config[$key]) && is_array($config[$key])) {
            return $config[$key]['source'] ?? $default;
        }

        return $default;
    }
}
