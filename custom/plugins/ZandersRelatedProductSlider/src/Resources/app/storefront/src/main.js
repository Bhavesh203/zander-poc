import RelatedProductSliderPlugin from './plugin/related-product-slider.plugin';

const pluginManager = window.PluginManager;
if (pluginManager && typeof pluginManager.register === 'function') {
    pluginManager.register('RelatedProductSlider', RelatedProductSliderPlugin, '[data-related-product-slider="true"]');
}

