<?php declare(strict_types=1);

namespace ZandersProductGrid\Storefront\Controller;

use Shopware\Core\System\SalesChannel\SalesChannelContext;
use Shopware\Storefront\Controller\StorefrontController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use ZandersProductGrid\Core\Content\ProductGrid\ProductGridLoader;

#[Route(defaults: ['_routeScope' => ['storefront']])]
class ProductGridController extends StorefrontController
{
    public function __construct(
        private readonly ProductGridLoader $productGridLoader
    ) {
    }

    #[Route(
        path: '/widgets/cms/product-grid/{slotId}',
        name: 'widgets.cms.product-grid.load',
        methods: ['GET', 'POST'],
        defaults: ['XmlHttpRequest' => true]
    )]
    public function loadMore(string $slotId, Request $request, SalesChannelContext $context): Response
    {
        $page = $request->query->getInt('page', $request->request->getInt('page', 1));

        try {
            $gridStruct = $this->productGridLoader->loadBySlot($slotId, $page, $context);

            $html = $this->renderView('@Storefront/storefront/element/cms-element-product-grid-items.html.twig', [
                'grid' => $gridStruct,
            ]);

            return new JsonResponse([
                'success' => true,
                'html' => $html,
                'page' => $gridStruct->getPage(),
                'hasMore' => $gridStruct->hasMore(),
                'total' => $gridStruct->getTotal(),
                'count' => $gridStruct->getProducts()?->count() ?? 0,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage(),
                'html' => '',
                'page' => $page,
                'hasMore' => false,
                'total' => 0,
                'count' => 0,
            ], Response::HTTP_INTERNAL_SERVER_ERROR);
        }
    }
}
