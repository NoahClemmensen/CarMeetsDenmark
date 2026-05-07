import { Controller } from '@hotwired/stimulus';

const MAX_TOASTS = 5;

export default class extends Controller {
    static values = {
        fadeOutAfterMs: { type: Number, default: 5000 },
        dismissable: { type: Boolean, default: true },
    };

    connect() {
        this.enforceToastLimit();

        requestAnimationFrame(() => {
            this.element.setAttribute('data-state', 'visible');
        });

        if (this.fadeOutAfterMsValue > 0) {
            this.autoCloseTimer = setTimeout(() => {
                this.dismiss();
            }, this.fadeOutAfterMsValue);
        }

        // Remove toast before Turbo caches the page to prevent stale toasts on back-button
        this.handleBeforeCache = () => this.element.remove();
        document.addEventListener('turbo:before-cache', this.handleBeforeCache, { once: true });
    }

    disconnect() {
        if (this.autoCloseTimer) {
            clearTimeout(this.autoCloseTimer);
        }
        if (this.handleBeforeCache) {
            document.removeEventListener('turbo:before-cache', this.handleBeforeCache);
        }
    }

    dismiss() {
        if (this.isDismissing) return;
        this.isDismissing = true;

        if (this.autoCloseTimer) {
            clearTimeout(this.autoCloseTimer);
        }

        this.element.setAttribute('data-state', 'leaving');

        setTimeout(() => {
            this.element.remove();
        }, 300);
    }

    enforceToastLimit() {
        const container = this.element.parentElement;
        if (!container) return;

        const toasts = container.querySelectorAll("[data-controller='toast']");
        if (toasts.length > MAX_TOASTS) {
            const excess = Array.from(toasts).slice(MAX_TOASTS);
            excess.forEach((toast) => toast.remove());
        }
    }
}
