import template from './sw-cms-el-config-product-grid.html.twig';
import './sw-cms-el-config-product-grid.scss';

const { Mixin } = Shopware;
const { Criteria, EntityCollection } = Shopware.Data;

export default {
    template,

    inject: ['repositoryFactory'],

    mixins: [
        Mixin.getByName('cms-element'),
    ],

    data() {
        return {
            activeTab: 'content',
            productCollection: null,
            productStream: null,
        };
    },

    computed: {
        tabs() {
            return [
                {
                    label: this.$t('sw-cms.elements.productGrid.config.tab.content'),
                    name: 'content',
                },
                {
                    label: this.$t('sw-cms.elements.productGrid.config.tab.settings'),
                    name: 'settings',
                },
            ];
        },

        productRepository() {
            return this.repositoryFactory.create('product');
        },

        productStreamRepository() {
            return this.repositoryFactory.create('product_stream');
        },

        productAssignmentTypes() {
            return [
                {
                    id: 1,
                    value: 'static',
                    label: this.$t('sw-cms.elements.productSlider.config.productAssignmentTypeManual') || 'Manual selection',
                },
                {
                    id: 2,
                    value: 'product_stream',
                    label: this.$t('sw-cms.elements.productSlider.config.productAssignmentTypeStream') || 'Dynamic product group',
                },
            ];
        },

        loadModeOptions() {
            return [
                {
                    id: 1,
                    value: 'load_more',
                    label: this.$t('sw-cms.elements.productGrid.config.label.loadModeButton'),
                },
                {
                    id: 2,
                    value: 'infinite_scroll',
                    label: this.$t('sw-cms.elements.productGrid.config.label.loadModeInfinite'),
                },
            ];
        },

        columnOptions() {
            return [
                { id: 2, value: 2, label: '2' },
                { id: 3, value: 3, label: '3' },
                { id: 4, value: 4, label: '4 (Recommended)' },
                { id: 6, value: 6, label: '6' },
            ];
        },

        mobileLayoutOptions() {
            return [
                { id: 1, value: 'slider', label: this.$t('sw-cms.elements.productGrid.config.label.mobileLayoutSlider') || 'Slider / Carousel (Swipe on mobile)' },
                { id: 2, value: 'grid', label: this.$t('sw-cms.elements.productGrid.config.label.mobileLayoutGrid') || 'Grid (2 columns on mobile)' },
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
            const context = { ...Shopware.Context.api };
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

            if (this.element.config.products.source === 'static') {
                this.loadProducts();
            } else if (this.element.config.products.source === 'product_stream') {
                this.loadProductStream();
            }
        },

        loadProducts() {
            const productIds = this.element.config.products.value;

            if (!productIds || productIds.length === 0) {
                this.productCollection = new EntityCollection(
                    this.productRepository.route,
                    this.productRepository.entityName,
                    Shopware.Context.api,
                );
                return;
            }

            const criteria = new Criteria(1, 100);
            criteria.setIds(productIds);
            criteria.addAssociation('cover');

            this.productRepository.search(criteria, this.productMultiSelectContext).then((result) => {
                this.productCollection = result;
            });
        },

        loadProductStream() {
            const streamId = this.element.config.products.value;

            if (!streamId) {
                return;
            }

            this.productStreamRepository.get(streamId, Shopware.Context.api).then((stream) => {
                this.productStream = stream;
            });
        },

        onProductsChange() {
            if (!this.productCollection) {
                this.element.config.products.value = [];
                return;
            }

            this.element.config.products.value = this.productCollection.getIds();
            this.$set(this.element.data, 'products', this.productCollection);
        },

        onChangeAssignmentType(source) {
            this.element.config.products.source = source;
            if (source === 'static') {
                this.element.config.products.value = [];
                this.loadProducts();
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
};

