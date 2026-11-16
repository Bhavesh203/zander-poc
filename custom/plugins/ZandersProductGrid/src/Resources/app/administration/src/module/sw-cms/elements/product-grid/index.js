Shopware.Component.register('sw-cms-el-preview-product-grid', () => import('./preview'));
Shopware.Component.register('sw-cms-el-config-product-grid', () => import('./config'));
Shopware.Component.register('sw-cms-el-product-grid', () => import('./component'));

Shopware.Service('cmsService').registerCmsElement({
    name: 'product-grid',
    label: 'sw-cms.elements.productGrid.label',
    component: 'sw-cms-el-product-grid',
    configComponent: 'sw-cms-el-config-product-grid',
    previewComponent: 'sw-cms-el-preview-product-grid',
    defaultConfig: {
        products: {
            source: 'static',
            value: [],
            required: true,
            entity: {
                name: 'product',
                criteria: new Shopware.Data.Criteria(1, 25).addAssociation('cover'),
            },
        },
        title: {
            source: 'static',
            value: '',
        },
        productLimit: {
            source: 'static',
            value: 8,
        },
        columns: {
            source: 'static',
            value: 4,
        },
        mobileLayout: {
            source: 'static',
            value: 'slider',
        },
        loadMode: {
            source: 'static',
            value: 'load_more',
        },
        boxLayout: {
            source: 'static',
            value: 'standard',
        },
        displayMode: {
            source: 'static',
            value: 'standard',
        },
        productStreamSorting: {
            source: 'static',
            value: 'name:ASC',
        },
        productStreamLimit: {
            source: 'static',
            value: 0,
        },
    },
    collect: Shopware.Service('cmsService').getCollectFunction(),
});

