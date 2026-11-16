import Plugin from 'src/plugin-system/plugin.class';

export default class ProductGridPlugin extends Plugin {
    static options = {
        slotId: null,
        endpoint: '',
        page: 1,
        limit: 8,
        loadMode: 'load_more',
        mobileLayout: 'slider',
        hasMore: true,
        total: 0,
        containerSelector: '[data-product-grid-container="true"]',
        buttonSelector: '[data-product-grid-button="true"]',
        loaderSelector: '[data-product-grid-loader="true"]',
        sentinelSelector: '[data-product-grid-sentinel="true"]',
        endSelector: '[data-product-grid-end="true"]',
        prevButtonSelector: '[data-product-grid-prev="true"]',
        nextButtonSelector: '[data-product-grid-next="true"]',
    };

    init() {
        this.container = this.el.querySelector(this.options.containerSelector);
        this.button = this.el.querySelector(this.options.buttonSelector);
        this.loader = this.el.querySelector(this.options.loaderSelector);
        this.sentinel = this.el.querySelector(this.options.sentinelSelector);
        this.endMessage = this.el.querySelector(this.options.endSelector);
        this.prevBtn = this.el.querySelector(this.options.prevButtonSelector);
        this.nextBtn = this.el.querySelector(this.options.nextButtonSelector);

        this.isLoading = false;
        this.currentPage = parseInt(this.options.page, 10) || 1;
        this.hasMore = Boolean(this.options.hasMore);
        this.observer = null;

        if (this.button) {
            this._registerButtonEvents();
        }

        if (this.prevBtn || this.nextBtn) {
            this._registerSliderArrowEvents();
        }

        if (this.options.mobileLayout === 'slider' && this.container) {
            this._registerMobileScrollEvents();
        }

        if (this.options.loadMode === 'infinite_scroll' && this.sentinel) {
            this._registerIntersectionObserver();
        }
    }

    _registerButtonEvents() {
        this.button.addEventListener('click', (event) => {
            event.preventDefault();
            this.loadNextPage();
        });
    }

    _registerSliderArrowEvents() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', (event) => {
                event.preventDefault();
                const scrollAmount = this.container.firstElementChild ? (this.container.firstElementChild.offsetWidth + 14) : 260;
                this.container.scrollBy({ left: -scrollAmount, behavior: 'smooth' });
            });
        }

        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', (event) => {
                event.preventDefault();
                const scrollAmount = this.container.firstElementChild ? (this.container.firstElementChild.offsetWidth + 14) : 260;
                this.container.scrollBy({ left: scrollAmount, behavior: 'smooth' });
            });
        }
    }

    _registerMobileScrollEvents() {
        this.container.addEventListener('scroll', () => {
            if (window.innerWidth < 768 && this.options.loadMode === 'infinite_scroll' && !this.isLoading && this.hasMore) {
                if (this.container.scrollLeft + this.container.clientWidth >= this.container.scrollWidth - 120) {
                    this.loadNextPage();
                }
            }
        }, { passive: true });
    }

    _registerIntersectionObserver() {
        if (!('IntersectionObserver' in window)) {
            // Fallback for older browsers: show load more button
            if (this.button) {
                this.button.style.display = '';
                this._registerButtonEvents();
            }
            return;
        }

        const observerOptions = {
            root: null,
            rootMargin: '250px 0px',
            threshold: 0.1,
        };

        this.observer = new IntersectionObserver((entries) => {
            entries.forEach((entry) => {
                if (entry.isIntersecting && !this.isLoading && this.hasMore) {
                    this.loadNextPage();
                }
            });
        }, observerOptions);

        this.observer.observe(this.sentinel);
    }

    loadNextPage() {
        if (this.isLoading || !this.hasMore) {
            return;
        }

        this.isLoading = true;
        this._showLoader();

        if (this.button) {
            this.button.disabled = true;
        }

        const nextPage = this.currentPage + 1;
        const endpointUrl = new URL(this.options.endpoint, window.location.origin);
        endpointUrl.searchParams.set('page', nextPage);

        fetch(endpointUrl.toString(), {
            method: 'GET',
            headers: {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json',
            },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`HTTP error ${response.status}`);
                }
                return response.json();
            })
            .then((data) => {
                if (data.success && data.html) {
                    this.container.insertAdjacentHTML('beforeend', data.html);

                    this.currentPage = parseInt(data.page, 10);
                    this.hasMore = Boolean(data.hasMore);

                    // Reinitialize plugins for new product cards (e.g. wishlist, image slider)
                    if (window.PluginManager && typeof window.PluginManager.initializePlugins === 'function') {
                        window.PluginManager.initializePlugins();
                    }

                    if (!this.hasMore) {
                        this._onAllLoaded();
                    }
                } else {
                    this._onAllLoaded();
                }
            })
            .catch((error) => {
                console.error('[ProductGridPlugin] Error loading products:', error);
            })
            .finally(() => {
                this.isLoading = false;
                this._hideLoader();

                if (this.button) {
                    this.button.disabled = false;
                }
            });
    }

    _showLoader() {
        if (this.loader) {
            this.loader.classList.remove('d-none');
        }
    }

    _hideLoader() {
        if (this.loader) {
            this.loader.classList.add('d-none');
        }
    }

    _onAllLoaded() {
        this.hasMore = false;

        if (this.button) {
            this.button.style.display = 'none';
        }

        if (this.observer && this.sentinel) {
            this.observer.unobserve(this.sentinel);
            this.observer.disconnect();
            this.sentinel.style.display = 'none';
        }

        if (this.endMessage) {
            this.endMessage.classList.remove('d-none');
        }
    }

    destroy() {
        if (this.observer) {
            this.observer.disconnect();
        }
        super.destroy();
    }
}
