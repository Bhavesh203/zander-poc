import './module/sw-cms/elements/product-grid';
import './module/sw-cms/blocks/commerce/product-grid';

import enGB from './snippet/en-GB.json';
import deDE from './snippet/de-DE.json';

const { Locale } = Shopware;

if (Locale && typeof Locale.extendLocal === 'function') {
    Locale.extendLocal('en-GB', enGB);
    Locale.extendLocal('de-DE', deDE);
}

