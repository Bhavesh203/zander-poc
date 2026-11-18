<?php declare(strict_types=1);

namespace ZandersProductGrid\Storefront\Controller;

use Shopware\Core\Content\Category\CategoryEntity;
use Shopware\Core\Content\Product\Aggregate\ProductVisibility\ProductVisibilityDefinition;
use Shopware\Core\Content\Product\SalesChannel\ProductAvailableFilter;
use Shopware\Core\Framework\DataAbstractionLayer\EntityRepository;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Bucket\TermsAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Aggregation\Metric\EntityAggregation;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Bucket\TermsResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\AggregationResult\Metric\EntityResult;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Criteria;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsAnyFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\EqualsFilter;
use Shopware\Core\Framework\DataAbstractionLayer\Search\Filter\MultiFilter;
use Shopware\Core\System\SalesChannel\Entity\SalesChannelRepository;
use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class FinderController extends StorefrontController
{
    public function __construct(
        private readonly SalesChannelRepository $productRepository,
        private readonly EntityRepository $categoryRepository,
        private readonly EntityRepository $propertyOptionRepository,
    ) {
    }

    /**
     * AJAX endpoint powering the Firearms Finder and Ammo Finder slide-out drawers.
     *
     * Returns:
     * - Matching product total for the active category + selected manufacturer/property filters
     * - Available manufacturer options for the active selection
     * - Ordered property groups with either available options or empty list ("No Match Found")
     */
    #[Route(
        path: '/widgets/zanders/finder/{type}',
        name: 'widgets.zanders.finder',
        requirements: ['type' => 'firearms|ammo'],
        methods: ['GET'],
        defaults: ['XmlHttpRequest' => true]
    )]
    public function filter(string $type, Request $request, SalesChannelContext $context): Response
    {
        try {
            $category = $this->resolveFinderCategory($type, $context);
            $title = $type === 'ammo' ? 'Ammo Finder' : 'Firearms Finder';

            if (!$category) {
                return new JsonResponse([
                    'success' => true,
                    'type' => $type,
                    'title' => $title,
                    'categoryId' => null,
                    'categoryUrl' => $this->generateUrl('frontend.home.page'),
                    'total' => 0,
                    'manufacturer' => ['selected' => '', 'options' => []],
                    'groups' => [],
                ]);
            }

            $categoryId = $category->getId();
            $categoryUrl = $this->generateUrl('frontend.navigation.page', ['navigationId' => $categoryId]);

            $selectedManufacturer = trim((string) $request->query->get('manufacturer', ''));
            $rawProperties = trim((string) $request->query->get('properties', ''));
            $selectedPropertyIds = $rawProperties !== ''
                ? array_values(array_unique(array_filter(explode('|', $rawProperties))))
                : [];

            // Map each selected property option ID to its parent property group ID
            $selectedByGroup = $this->mapSelectedOptionsToGroups($selectedPropertyIds, $context);

            // 1. Base query (all products in category tree) -> discovers full set of groups & manufacturers
            $baseCriteria = $this->createBaseCategoryCriteria($categoryId, $context);
            $baseCriteria->setLimit(1);
            $baseCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
            $baseCriteria->addAggregation(new EntityAggregation('manufacturers', 'manufacturerId', 'product_manufacturer'));
            $baseCriteria->addAggregation(new EntityAggregation('propertyGroups', 'properties.groupId', 'property_group'));
            $baseCriteria->addAggregation(new EntityAggregation('propertyOptions', 'properties.id', 'property_group_option'));

            $baseResult = $this->productRepository->search($baseCriteria, $context);
            $baseAggs = $baseResult->getAggregations();

            $allGroups = [];
            /** @var EntityResult|null $groupAgg */
            $groupAgg = $baseAggs->get('propertyGroups');
            if ($groupAgg) {
                foreach ($groupAgg->getEntities() as $group) {
                    if (method_exists($group, 'getFilterable') && !$group->getFilterable()) {
                        continue;
                    }
                    $groupName = $group->getTranslation('name') ?? $group->getName() ?? '';
                    if ($groupName === '') {
                        continue;
                    }
                    $allGroups[$group->getId()] = [
                        'id' => $group->getId(),
                        'name' => $groupName,
                        'position' => method_exists($group, 'getPosition') ? (int) $group->getPosition() : 0,
                    ];
                }
            }

            // Sort property groups cleanly by position, then alphabetically
            uasort($allGroups, static function (array $a, array $b): int {
                return ($a['position'] <=> $b['position']) ?: strcasecmp($a['name'], $b['name']);
            });

            // 2. Filtered query (category + selected manufacturer + selected properties) -> live total & matching options
            $filteredCriteria = $this->createBaseCategoryCriteria($categoryId, $context);
            $filteredCriteria->setLimit(1);
            $filteredCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_EXACT);

            if ($selectedManufacturer !== '') {
                $filteredCriteria->addFilter(new EqualsFilter('manufacturerId', $selectedManufacturer));
            }
            $this->applyPropertyFilters($filteredCriteria, $selectedByGroup);

            $filteredCriteria->addAggregation(new TermsAggregation('matchingOptionIds', 'properties.id'));
            $filteredCriteria->addAggregation(new EntityAggregation('matchingManufacturers', 'manufacturerId', 'product_manufacturer'));

            $filteredResult = $this->productRepository->search($filteredCriteria, $context);
            $totalMatching = $filteredResult->getTotal();

            // Collect matching option IDs from current filtered product set
            $matchingOptionSet = [];
            /** @var TermsResult|null $matchingOptionsAgg */
            $matchingOptionsAgg = $filteredResult->getAggregations()->get('matchingOptionIds');
            if ($matchingOptionsAgg) {
                foreach ($matchingOptionsAgg->getBuckets() as $bucket) {
                    $matchingOptionSet[(string) $bucket->getKey()] = true;
                }
            }

            // 3. Manufacturer options query (filtered by category + properties, excluding manufacturer filter so user can switch)
            $mfgCriteria = $this->createBaseCategoryCriteria($categoryId, $context);
            $mfgCriteria->setLimit(1);
            $mfgCriteria->setTotalCountMode(Criteria::TOTAL_COUNT_MODE_NONE);
            $this->applyPropertyFilters($mfgCriteria, $selectedByGroup);
            $mfgCriteria->addAggregation(new EntityAggregation('availableManufacturers', 'manufacturerId', 'product_manufacturer'));

            $mfgResult = $this->productRepository->search($mfgCriteria, $context);
            $manufacturerOptions = [];
            /** @var EntityResult|null $mfgAgg */
            $mfgAgg = $mfgResult->getAggregations()->get('availableManufacturers');
            if ($mfgAgg) {
                foreach ($mfgAgg->getEntities() as $manufacturer) {
                    $name = $manufacturer->getTranslation('name') ?? $manufacturer->getName() ?? '';
                    if ($name !== '') {
                        $manufacturerOptions[] = [
                            'id' => $manufacturer->getId(),
                            'name' => $name,
                        ];
                    }
                }
            }
            usort($manufacturerOptions, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

            // 4. Organize base property options by group, filtering to options present in matching products
            $optionsByGroup = [];
            /** @var EntityResult|null $baseOptionsAgg */
            $baseOptionsAgg = $baseAggs->get('propertyOptions');
            if ($baseOptionsAgg) {
                foreach ($baseOptionsAgg->getEntities() as $option) {
                    $gid = $option->getGroupId();
                    if (!isset($allGroups[$gid])) {
                        continue;
                    }
                    $oid = $option->getId();
                    $isSelectedInGroup = isset($selectedByGroup[$gid]) && in_array($oid, $selectedByGroup[$gid], true);

                    if (!$isSelectedInGroup && !isset($matchingOptionSet[$oid])) {
                        continue;
                    }

                    $optName = $option->getTranslation('name') ?? $option->getName() ?? '';
                    if ($optName === '') {
                        continue;
                    }

                    $optionsByGroup[$gid][] = [
                        'id' => $oid,
                        'name' => $optName,
                    ];
                }
            }

            $groupsPayload = [];
            foreach ($allGroups as $gid => $groupMeta) {
                $opts = $optionsByGroup[$gid] ?? [];
                usort($opts, static fn (array $a, array $b): int => strcasecmp($a['name'], $b['name']));

                $selectedOptId = isset($selectedByGroup[$gid]) ? (string) ($selectedByGroup[$gid][0] ?? '') : '';

                $groupsPayload[] = [
                    'id' => $gid,
                    'name' => $groupMeta['name'],
                    'selected' => $selectedOptId,
                    'options' => $opts,
                ];
            }

            return new JsonResponse([
                'success' => true,
                'type' => $type,
                'title' => $title,
                'categoryId' => $categoryId,
                'categoryUrl' => $categoryUrl,
                'total' => $totalMatching,
                'manufacturer' => [
                    'selected' => $selectedManufacturer,
                    'options' => $manufacturerOptions,
                ],
                'groups' => $groupsPayload,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'total' => 0,
                'manufacturer' => ['selected' => '', 'options' => []],
                'groups' => [],
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }

    /**
     * Resolves the Firearms or Ammunition category entity in the active sales channel.
     */
    private function resolveFinderCategory(string $type, SalesChannelContext $context): ?CategoryEntity
    {
        $targetNames = $type === 'ammo'
            ? ['Ammunition', 'Ammo']
            : ['Firearms'];

        $criteria = new Criteria();
        $criteria->setLimit(1);
        $criteria->addFilter(new EqualsFilter('active', true));
        $criteria->addFilter(new EqualsAnyFilter('name', $targetNames));

        /** @var CategoryEntity|null $category */
        $category = $this->categoryRepository->search($criteria, $context->getContext())->first();

        return $category;
    }

    /**
     * Builds the base sales-channel product criteria scoped to the given category tree.
     */
    private function createBaseCategoryCriteria(string $categoryId, SalesChannelContext $context): Criteria
    {
        $criteria = new Criteria();
        $criteria->addFilter(new ProductAvailableFilter($context->getSalesChannelId(), ProductVisibilityDefinition::VISIBILITY_ALL));
        $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
            new EqualsFilter('categoryTree', $categoryId),
            new EqualsFilter('categoriesRo.id', $categoryId),
        ]));

        return $criteria;
    }

    /**
     * Maps flat selected property option IDs into `[groupId => [optionId, ...]]`.
     *
     * @param list<string> $optionIds
     * @return array<string, list<string>>
     */
    private function mapSelectedOptionsToGroups(array $optionIds, SalesChannelContext $context): array
    {
        if ($optionIds === []) {
            return [];
        }

        $criteria = new Criteria($optionIds);
        $options = $this->propertyOptionRepository->search($criteria, $context->getContext());

        $grouped = [];
        foreach ($options as $option) {
            $grouped[$option->getGroupId()][] = $option->getId();
        }

        return $grouped;
    }

    /**
     * Applies Shopware-compatible property filters (AND across distinct groups, OR within a group).
     *
     * @param array<string, list<string>> $selectedByGroup
     */
    private function applyPropertyFilters(Criteria $criteria, array $selectedByGroup): void
    {
        foreach ($selectedByGroup as $optionIds) {
            if ($optionIds === []) {
                continue;
            }
            $criteria->addFilter(new MultiFilter(MultiFilter::CONNECTION_OR, [
                new EqualsAnyFilter('propertyIds', $optionIds),
                new EqualsAnyFilter('optionIds', $optionIds),
            ]));
        }
    }
}

