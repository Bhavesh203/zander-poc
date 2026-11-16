(function() {
    'use strict';

    const { Mixin } = Shopware;
    const { Criteria, EntityCollection } = Shopware.Data;

    // 1. Translations
    const enGB = {
        'sw-cms': {
            elements: {
                productGrid: {
                    label: 'Product Grid',
                    preview: { label: 'Product Grid' },
                    config: {
                        tab: {
                            content: 'Content',
                            settings: 'Settings'
                        },
                        label: {
                            title: 'Title',
                            productAssignmentType: 'Product assignment',
                            productStreamChoice: 'Product stream',
                            productStreamSorting: 'Stream sorting',
                            productStreamLimit: 'Stream max limit (0 = all)',
                            selection: 'Manual product selection',
                            loadMode: 'Pagination / Scroll mode',
                            loadModeButton: 'Load more button',
                            loadModeInfinite: 'Infinite scroll (auto-load on scroll)',
                            itemsPerPage: 'Products per batch / page',
                            columns: 'Grid columns (Desktop)',
                            mobileLayout: 'Mobile layout',
                            mobileLayoutSlider: 'Slider / Carousel (Swipe on mobile)',
                            mobileLayoutGrid: 'Grid (2 columns on mobile)'
                        },
                        placeholder: {
                            title: 'e.g. Featured Products',
                            productStreamChoice: 'Select a product stream...',
                            productStreamSorting: 'Select sorting...'
                        }
                    }
                }
            },
            blocks: {
                commerce: {
                    productGrid: {
                        label: 'Product Grid'
                    }
                }
            }
        }
    };

    const deDE = {
        'sw-cms': {
            elements: {
                productGrid: {
                    label: 'Produkt-Grid',
                    preview: { label: 'Produkt-Grid' },
                    config: {
                        tab: {
                            content: 'Inhalt',
                            settings: 'Einstellungen'
                        },
                        label: {
                            title: 'Titel',
                            productAssignmentType: 'Produktzuweisung',
                            productStreamChoice: 'Product Stream',
                            productStreamSorting: 'Stream-Sortierung',
                            productStreamLimit: 'Stream Max. Limit (0 = alle)',
                            selection: 'Manuelle Produktauswahl',
                            loadMode: 'Seiten-/Scroll-Modus',
                            loadModeButton: 'Mehr laden Button',
                            loadModeInfinite: 'Infinite Scroll (automatisches Nachladen)',
                            itemsPerPage: 'Produkte pro Seite / Charge',
                            columns: 'Grid-Spalten (Desktop)',
                            mobileLayout: 'Mobil-Layout',
                            mobileLayoutSlider: 'Slider / Karussell (Wischen auf Mobilgeräten)',
                            mobileLayoutGrid: 'Grid (2 Spalten auf Mobilgeräten)'
                        },
                        placeholder: {
                            title: 'z.B. Ausgewählte Produkte',
                            productStreamChoice: 'Product Stream auswählen...',
                            productStreamSorting: 'Sortierung auswählen...'
                        }
                    }
                }
            },
            blocks: {
                commerce: {
                    productGrid: {
                        label: 'Produkt-Grid'
                    }
                }
            }
        }
    };

    if (Shopware.Locale && typeof Shopware.Locale.extendLocal === 'function') {
        Shopware.Locale.extendLocal('en-GB', enGB);
        Shopware.Locale.extendLocal('de-DE', deDE);
    }

    // 2. CMS Block Components
    Shopware.Component.register('sw-cms-preview-product-grid', {
        template: `
            <div class="sw-cms-preview-product-grid" style="padding: 10px; width: 100%; height: 100%;">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 6px; width: 100%; height: 100%;">
                    <div v-for="i in 4" :key="i" style="overflow: hidden; border-radius: 2px; border: 1px solid #d1d9e0;">
                        <sw-cms-product-box-preview />
                    </div>
                </div>
            </div>
        `,
    });

    Shopware.Component.register('sw-cms-block-product-grid', {
        template: `
            <div class="sw-cms-block-product-grid">
                <slot name="productGrid"></slot>
            </div>
        `,
    });

    // 3. Register CMS Block
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

    // 4. CMS Element Preview Component
    Shopware.Component.register('sw-cms-el-preview-product-grid', {
        template: `
            <div class="sw-cms-el-preview-product-grid" style="padding: 8px; width: 100%; height: 100%;">
                <div style="display: grid; grid-template-columns: repeat(2, 1fr); grid-gap: 4px; width: 100%; height: 100%;">
                    <div v-for="i in 4" :key="i" style="overflow: hidden; border-radius: 2px; border: 1px solid #d1d9e0;">
                        <sw-cms-product-box-preview />
                    </div>
                </div>
            </div>
        `,
    });

    // 5. CMS Element Main Component (Designer View)
    Shopware.Component.register('sw-cms-el-product-grid', {
        template: `
            <div class="sw-cms-el-product-grid" style="width: 100%; height: 100%; display: flex; flex-direction: column;">
                <div v-if="element && element.config && element.config.title && element.config.title.value"
                     style="font-size: 1.25rem; font-weight: 600; text-align: center; margin-bottom: 16px; color: #18974e;">
                    {{ element.config.title.value }}
                </div>

                <div style="display: grid; grid-gap: 16px; width: 100%;" :style="gridStyle">
                    <template v-if="!products || products.length === 0">
                        <sw-cms-el-product-box
                            v-for="index in gridColumns"
                            :key="index"
                            :element="demoProductElement"
                        />
                    </template>
                    <template v-else>
                        <sw-cms-el-product-box
                            v-for="(product, index) in products.slice(0, gridColumns * 2)"
                            :key="product.id || index"
                            :element="getProductEl(product)"
                        />
                    </template>
                </div>

                <div style="margin-top: 24px; text-align: center;">
                    <div v-if="loadMode === 'load_more'">
                        <button type="button" disabled style="background-color: #8c1d2f; color: #ffffff; border: none; padding: 8px 24px; border-radius: 4px; font-weight: 600; font-size: 13px; cursor: not-allowed; opacity: 0.85;">
                            {{ $t('sw-cms.elements.productGrid.config.label.loadModeButton') }}
                        </button>
                    </div>
                    <div v-else style="display: inline-flex; align-items: center; gap: 6px; color: #758ca3; font-size: 13px; font-style: italic;">
                        <span>&#8635;</span>
                        {{ $t('sw-cms.elements.productGrid.config.label.loadModeInfinite') }}
                    </div>
                </div>
            </div>
        `,
        mixins: [
            Mixin.getByName('cms-element'),
        ],
        computed: {
            demoProductElement() {
                return {
                    config: {
                        boxLayout: {
                            source: 'static',
                            value: this.element?.config?.boxLayout?.value || 'standard',
                        },
                        displayMode: {
                            source: 'static',
                            value: this.element?.config?.displayMode?.value || 'standard',
                        },
                    },
                    data: null,
                };
            },
            gridColumns() {
                return Number(this.element?.config?.columns?.value) || 4;
            },
            gridStyle() {
                return {
                    gridTemplateColumns: 'repeat(' + this.gridColumns + ', 1fr)',
                };
            },
            products() {
                if (this.element?.data?.products && this.element.data.products.length > 0) {
                    return this.element.data.products;
                }
                return null;
            },
            loadMode() {
                return this.element?.config?.loadMode?.value || 'load_more';
            },
        },
        created() {
            this.createdComponent();
        },
        methods: {
            createdComponent() {
                this.initElementConfig('product-grid');
                this.initElementData('product-grid');
            },
            getProductEl(product) {
                return {
                    config: {
                        boxLayout: {
                            source: 'static',
                            value: this.element?.config?.boxLayout?.value || 'standard',
                        },
                        displayMode: {
                            source: 'static',
                            value: this.element?.config?.displayMode?.value || 'standard',
                        },
                    },
                    data: {
                        product: product,
                    },
                };
            },
        },
    });

    // 6. CMS Element Config Component (Modal)
    Shopware.Component.register('sw-cms-el-config-product-grid', {
        template: `
            <div class="sw-cms-el-config-product-grid" v-if="element && element.config">
                <sw-tabs default-item="content">
                    <template #default="{ active }">
                        <sw-tabs-item
                            name="content"
                            :title="$t('sw-cms.elements.productGrid.config.tab.content')"
                            :active-tab="active"
                        >
                            {{ $t('sw-cms.elements.productGrid.config.tab.content') }}
                        </sw-tabs-item>

                        <sw-tabs-item
                            name="settings"
                            :title="$t('sw-cms.elements.productGrid.config.tab.settings')"
                            :active-tab="active"
                        >
                            {{ $t('sw-cms.elements.productGrid.config.tab.settings') }}
                        </sw-tabs-item>
                    </template>

                    <template #content="{ active }">
                        <div v-if="active === 'content' || !active" class="sw-cms-el-config-product-grid__tab-content" style="padding: 20px 0; display: flex; flex-direction: column; gap: 20px;">
                            <sw-text-field
                                v-if="element.config.title"
                                v-model:value="element.config.title.value"
                                :label="$t('sw-cms.elements.productGrid.config.label.title')"
                                :placeholder="$t('sw-cms.elements.productGrid.config.placeholder.title')"
                            />

                            <sw-single-select
                                v-if="element.config.products"
                                v-model:value="element.config.products.source"
                                :label="$t('sw-cms.elements.productGrid.config.label.productAssignmentType')"
                                :options="productAssignmentTypes"
                                @update:value="onChangeAssignmentType"
                            />

                            <div v-if="element.config.products && element.config.products.source === 'product_stream'" style="display: flex; flex-direction: column; gap: 20px;">
                                <sw-entity-single-select
                                    v-model:value="element.config.products.value"
                                    entity="product_stream"
                                    :criteria="productStreamCriteria"
                                    :label="$t('sw-cms.elements.productGrid.config.label.productStreamChoice')"
                                    :placeholder="$t('sw-cms.elements.productGrid.config.placeholder.productStreamChoice')"
                                    show-clearable-button
                                    @update:value="onChangeProductStream"
                                />

                                <sw-container columns="1fr 1fr" gap="20px">
                                    <sw-single-select
                                        v-if="element.config.productStreamSorting"
                                        v-model:value="element.config.productStreamSorting.value"
                                        :options="productStreamSortingOptions"
                                        :label="$t('sw-cms.elements.productGrid.config.label.productStreamSorting')"
                                    />
                                    <sw-number-field
                                        v-if="element.config.productStreamLimit"
                                        v-model:value="element.config.productStreamLimit.value"
                                        :min="0"
                                        :label="$t('sw-cms.elements.productGrid.config.label.productStreamLimit')"
                                    />
                                </sw-container>
                            </div>

                            <div v-else style="display: flex; flex-direction: column; gap: 10px;">
                                <sw-entity-multi-select
                                    v-if="productCollection"
                                    v-model:entity-collection="productCollection"
                                    :label="$t('sw-cms.elements.productGrid.config.label.selection')"
                                    :criteria="productMediaFilter"
                                    :context="productMultiSelectContext"
                                    @update:entity-collection="onProductsChange"
                                />
                            </div>
                        </div>

                        <div v-if="active === 'settings'" class="sw-cms-el-config-product-grid__tab-settings" style="padding: 20px 0; display: flex; flex-direction: column; gap: 20px;">
                            <sw-single-select
                                v-if="element.config.loadMode"
                                v-model:value="element.config.loadMode.value"
                                :label="$t('sw-cms.elements.productGrid.config.label.loadMode')"
                                :options="loadModeOptions"
                            />

                            <sw-single-select
                                v-if="element.config.mobileLayout"
                                v-model:value="element.config.mobileLayout.value"
                                :label="$t('sw-cms.elements.productGrid.config.label.mobileLayout')"
                                :options="mobileLayoutOptions"
                            />

                            <sw-container columns="1fr 1fr" gap="20px">
                                <sw-number-field
                                    v-if="element.config.productLimit"
                                    v-model:value="element.config.productLimit.value"
                                    :min="1"
                                    :max="48"
                                    :label="$t('sw-cms.elements.productGrid.config.label.itemsPerPage')"
                                />
                                <sw-single-select
                                    v-if="element.config.columns"
                                    v-model:value="element.config.columns.value"
                                    :options="columnOptions"
                                    :label="$t('sw-cms.elements.productGrid.config.label.columns')"
                                />
                            </sw-container>
                        </div>
                    </template>
                </sw-tabs>
            </div>
        `,
        inject: ['repositoryFactory'],
        mixins: [
            Mixin.getByName('cms-element'),
        ],
        data() {
            return {
                productCollection: null,
                productStream: null,
            };
        },
        computed: {
            productRepository() {
                return this.repositoryFactory.create('product');
            },
            productStreamRepository() {
                return this.repositoryFactory.create('product_stream');
            },
            productAssignmentTypes() {
                return [
                    { id: 1, value: 'static', label: 'Manual selection' },
                    { id: 2, value: 'product_stream', label: 'Dynamic product stream' },
                ];
            },
            loadModeOptions() {
                return [
                    { id: 1, value: 'load_more', label: 'Load more button' },
                    { id: 2, value: 'infinite_scroll', label: 'Infinite scroll (auto-load on scroll)' },
                ];
            },
            mobileLayoutOptions() {
                return [
                    { id: 1, value: 'slider', label: this.$t('sw-cms.elements.productGrid.config.label.mobileLayoutSlider') || 'Slider / Carousel (Swipe on mobile)' },
                    { id: 2, value: 'grid', label: this.$t('sw-cms.elements.productGrid.config.label.mobileLayoutGrid') || 'Grid (2 columns on mobile)' },
                ];
            },
            columnOptions() {
                return [
                    { id: 2, value: 2, label: '2 Columns' },
                    { id: 3, value: 3, label: '3 Columns' },
                    { id: 4, value: 4, label: '4 Columns (Recommended)' },
                    { id: 6, value: 6, label: '6 Columns' },
                ];
            },
            productStreamSortingOptions() {
                return [
                    { id: 1, value: 'name:ASC', label: 'Name, A-Z' },
                    { id: 2, value: 'name:DESC', label: 'Name, Z-A' },
                    { id: 3, value: 'price:ASC', label: 'Price, low to high' },
                    { id: 4, value: 'price:DESC', label: 'Price, high to low' },
                    { id: 5, value: 'releaseDate:DESC', label: 'Newest' },
                    { id: 6, value: 'random', label: 'Random' },
                ];
            },
            productMultiSelectContext() {
                const context = Object.assign({}, Shopware.Context.api);
                context.inheritance = true;
                return context;
            },
            productMediaFilter() {
                const criteria = new Criteria(1, 25);
                criteria.addAssociation('cover');
                return criteria;
            },
            productStreamCriteria() {
                return new Criteria(1, 25);
            },
        },
        created() {
            this.createdComponent();
        },
        methods: {
            createdComponent() {
                this.initElementConfig('product-grid');

                if (!this.element.config) {
                    if (this.$set) {
                        this.$set(this.element, 'config', {});
                    } else {
                        this.element.config = {};
                    }
                }

                const defaultValues = {
                    title: { source: 'static', value: '' },
                    products: { source: 'static', value: [] },
                    productLimit: { source: 'static', value: 8 },
                    columns: { source: 'static', value: 4 },
                    loadMode: { source: 'static', value: 'load_more' },
                    mobileLayout: { source: 'static', value: 'slider' },
                    productStreamSorting: { source: 'static', value: 'name:ASC' },
                    productStreamLimit: { source: 'static', value: 0 },
                    boxLayout: { source: 'static', value: 'standard' },
                    displayMode: { source: 'static', value: 'standard' },
                };

                Object.keys(defaultValues).forEach((key) => {
                    if (!this.element.config[key]) {
                        if (this.$set) {
                            this.$set(this.element.config, key, defaultValues[key]);
                        } else {
                            this.element.config[key] = defaultValues[key];
                        }
                    }
                });

                this.productCollection = new EntityCollection(
                    '/product',
                    'product',
                    Shopware.Context.api
                );

                if (this.element.config.products?.source === 'product_stream') {
                    this.loadProductStream();
                } else if (Array.isArray(this.element.config.products?.value) && this.element.config.products.value.length > 0) {
                    this.loadProducts();
                }
            },
            loadProducts() {
                const productIds = this.element.config.products?.value;
                if (!productIds || !Array.isArray(productIds) || productIds.length === 0) {
                    return;
                }
                const criteria = new Criteria(1, 100);
                criteria.setIds(productIds);
                criteria.addAssociation('cover');
                this.productRepository.search(criteria, this.productMultiSelectContext).then((result) => {
                    this.productCollection = result;
                }).catch((err) => {
                    console.error('[ProductGrid] Error loading products:', err);
                });
            },
            loadProductStream() {
                const streamId = this.element.config.products?.value;
                if (!streamId || typeof streamId !== 'string') {
                    return;
                }
                this.productStreamRepository.get(streamId, Shopware.Context.api).then((stream) => {
                    this.productStream = stream;
                }).catch((err) => {
                    console.error('[ProductGrid] Error loading product stream:', err);
                });
            },
            onProductsChange() {
                if (!this.productCollection) {
                    this.element.config.products.value = [];
                    return;
                }
                this.element.config.products.value = this.productCollection.getIds();
                if (typeof this.$set === 'function') {
                    this.$set(this.element.data, 'products', this.productCollection);
                } else {
                    this.element.data.products = this.productCollection;
                }
            },
            onChangeAssignmentType(source) {
                this.element.config.products.source = source;
                if (source === 'static') {
                    this.element.config.products.value = [];
                    this.productCollection = new EntityCollection('/product', 'product', Shopware.Context.api);
                } else {
                    this.element.config.products.value = null;
                    this.productStream = null;
                }
            },
            onChangeProductStream(streamId) {
                this.element.config.products.value = streamId;
                if (streamId) {
                    this.loadProductStream();
                } else {
                    this.productStream = null;
                }
            },
        },
    });

    // 7. Register CMS Element
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
            loadMode: {
                source: 'static',
                value: 'load_more',
            },
            mobileLayout: {
                source: 'static',
                value: 'slider',
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
})();
