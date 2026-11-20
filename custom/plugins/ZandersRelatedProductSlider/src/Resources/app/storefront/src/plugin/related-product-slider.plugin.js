import Plugin from 'src/plugin-system/plugin.class';

export default class RelatedProductSliderPlugin extends Plugin {
    static options = {
        trackSelector: '.js-zanders-alt-track',
        prevButtonSelector: '.js-zanders-alt-prev',
        nextButtonSelector: '.js-zanders-alt-next',
        stockToggleSelector: '.js-zanders-alt-stock-toggle',
        cardSelector: '.js-zanders-alt-card',
        cardGap: 16,
        autoplay: false,
        autoplayTimeout: 4000,
        speed: 350
    };

    init() {
        this.track = this.el.querySelector(this.options.trackSelector);
        this.prevBtn = this.el.querySelector(this.options.prevButtonSelector);
        this.nextBtn = this.el.querySelector(this.options.nextButtonSelector);
        this.stockToggle = this.el.querySelector(this.options.stockToggleSelector);
        this.autoplayTimer = null;

        if (this.track && (this.prevBtn || this.nextBtn)) {
            this._registerArrowEvents();
        }

        if (this.track && this.stockToggle) {
            this._registerStockToggleEvents();
        }

        if (this.options.autoplay && this.track) {
            this._startAutoplay();
        }
    }

    _registerArrowEvents() {
        if (this.prevBtn) {
            this.prevBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._slide(-1);
            });
        }

        if (this.nextBtn) {
            this.nextBtn.addEventListener('click', (e) => {
                e.preventDefault();
                this._slide(1);
            });
        }
    }

    _slide(direction) {
        const firstVisible = this.track.querySelector(`${this.options.cardSelector}:not(.is-hidden)`);
        const step = firstVisible
            ? (firstVisible.getBoundingClientRect().width + this.options.cardGap)
            : 280;

        this.track.scrollBy({
            left: direction * step,
            behavior: 'smooth'
        });
    }

    _registerStockToggleEvents() {
        let hideOutOfStock = false;

        this.stockToggle.addEventListener('click', (e) => {
            e.preventDefault();
            hideOutOfStock = !hideOutOfStock;
            this.stockToggle.setAttribute('aria-pressed', hideOutOfStock ? 'true' : 'false');
            this.stockToggle.classList.toggle('is-active', hideOutOfStock);
            this.stockToggle.textContent = hideOutOfStock
                ? 'SHOW OUT OF STOCK ITEMS'
                : 'HIDE OUT OF STOCK ITEMS';

            const cards = this.track.querySelectorAll(this.options.cardSelector);
            cards.forEach((card) => {
                const inStock = card.getAttribute('data-in-stock') === '1';
                card.classList.toggle('is-hidden', hideOutOfStock && !inStock);
            });
        });
    }

    _startAutoplay() {
        this.autoplayTimer = setInterval(() => {
            if (!this.track) return;
            if (this.track.scrollLeft + this.track.clientWidth >= this.track.scrollWidth - 10) {
                this.track.scrollTo({ left: 0, behavior: 'smooth' });
            } else {
                this._slide(1);
            }
        }, this.options.autoplayTimeout);
    }
}

