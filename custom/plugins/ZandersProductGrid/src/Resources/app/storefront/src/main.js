import ProductGridPlugin from './plugin/product-grid.plugin';

const pluginManager = window.PluginManager;
pluginManager.register('ProductGrid', ProductGridPlugin, '[data-product-grid="true"]');

