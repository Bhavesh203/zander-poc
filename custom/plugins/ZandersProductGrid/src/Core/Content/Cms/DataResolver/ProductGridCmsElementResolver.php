<?php declare(strict_types=1);

namespace ZandersProductGrid\Core\Content\Cms\DataResolver;

use Shopware\Core\Content\Cms\Aggregate\CmsSlot\CmsSlotEntity;
use Shopware\Core\Content\Cms\DataResolver\CriteriaCollection;
use Shopware\Core\Content\Cms\DataResolver\Element\AbstractCmsElementResolver;
use Shopware\Core\Content\Cms\DataResolver\Element\ElementDataCollection;
use Shopware\Core\Content\Cms\DataResolver\ResolverContext\ResolverContext;
use ZandersProductGrid\Core\Content\ProductGrid\ProductGridLoader;

class ProductGridCmsElementResolver extends AbstractCmsElementResolver
{
    public function __construct(
        private readonly ProductGridLoader $productGridLoader
    ) {
    }

    public function getType(): string
    {
        return 'product-grid';
    }

    public function collect(CmsSlotEntity $slot, ResolverContext $resolverContext): ?CriteriaCollection
    {
        // Criteria collection is delegated directly to enriched loader for unified pagination
        return null;
    }

    public function enrich(CmsSlotEntity $slot, ResolverContext $resolverContext, ElementDataCollection $result): void
    {
        $config = $slot->getFieldConfig();
        $context = $resolverContext->getSalesChannelContext();

        $gridStruct = $this->productGridLoader->load($config, 1, $context, $slot->getId());

        $slot->setData($gridStruct);
    }
}

