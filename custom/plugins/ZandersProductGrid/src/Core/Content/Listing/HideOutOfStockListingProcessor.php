<?php declare(strict_types=1);

namespace ZandersProductGrid\Core\Content\Listing;

use Shopware\Core\Content\Product\SalesChannel\Listing\Processor\AbstractListingProcessor;
use Shopware\Core\Content\Product\SalesChannel\Listing\ProductListingResult;
use Shopware\Core\Framework\Adapter\Request\RequestParamHelper;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\RangeFilter;
use Shopware\Core\Framework\Plugin\Exception\DecorationPatternException;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Symfony\Component\HttpFoundation\Request;

/**
 * Listing processor that filters out out-of-stock products when the
 * `hide-out-of-stock=1` query/request parameter is set from the shop toolbar.
 */
class HideOutOfStockListingProcessor extends AbstractListingProcessor
{
    public const PARAM_NAME = 'hide-out-of-stock';

    public function getDecorated(): AbstractListingProcessor
    {
        throw new DecorationPatternException(self::class);
    }

    public function prepare(Request $request, Criteria $criteria, SalesChannelContext $context): void
    {
        if (!$this->isHideOutOfStockEnabled($request)) {
            return;
        }

        $criteria->addFilter(new RangeFilter('product.availableStock', [
            RangeFilter::GT => 0,
        ]));
    }

    public function process(Request $request, ProductListingResult $result, SalesChannelContext $context): void
    {
        $result->addCurrentFilter(self::PARAM_NAME, $this->isHideOutOfStockEnabled($request));
    }

    private function isHideOutOfStockEnabled(Request $request): bool
    {
        $raw = RequestParamHelper::get($request, self::PARAM_NAME, false);

        if (\is_bool($raw)) {
            return $raw;
        }

        if (\is_int($raw)) {
            return $raw > 0;
        }

        if (\is_string($raw)) {
            return \in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
        }

        return false;
    }
}

