import template from './sw-cms-el-product-grid.html.twig';
import './sw-cms-el-product-grid.scss';

const { Mixin } = Shopware;

export default {
    template,

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
            return this.element?.config?.columns?.value || 4;
        },

        gridStyle() {
            return {
                gridTemplateColumns: `repeat(${this.gridColumns}, 1fr)`,
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
                    product,
                },
            };
        },
    },
};

