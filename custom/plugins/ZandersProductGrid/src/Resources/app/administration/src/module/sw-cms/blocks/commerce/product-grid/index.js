Shopware.Component.register('sw-cms-preview-product-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-block-product-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsBlock({
    name: 'product-grid',
    label: 'sw-cms.blocks.commerce.productGrid.label',
    category: 'commerce',
    component: 'sw-cms-block-product-grid',
    previewComponent: 'sw-cms-preview-product-grid',
    defaultConfig: {
        marginBottom: '20px',
        marginTop: '20px',
        marginLeft: null,
        marginRight: null,
        sizingMode: 'boxed',
    },
    slots: {
        productGrid: 'product-grid',
    },
});

