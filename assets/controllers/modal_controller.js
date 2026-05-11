import { Controller } from '@hotwired/stimulus';

const FOCUSABLE = 'a[href], button:not([disabled]), textarea:not([disabled]), input:not([disabled]), select:not([disabled]), [tabindex]:not([tabindex="-1"])';

export default class extends Controller {
    static targets = ['dialog', 'backdrop', 'header', 'body', 'footer'];

    static values = {
        closeOnEscape: { type: Boolean, default: true },
        closeOnBackdrop: { type: Boolean, default: true },
    };

    connect() {
        this.handleKeydown = this.handleKeydown.bind(this);
        this.handleTurboBeforeCache = this.handleTurboBeforeCache.bind(this);
        this.previouslyFocusedElement = null;

        document.addEventListener('turbo:before-cache', this.handleTurboBeforeCache);
    }

    disconnect() {
        document.removeEventListener('turbo:before-cache', this.handleTurboBeforeCache);
        document.removeEventListener('keydown', this.handleKeydown);
        this.restoreScroll();
    }

    show() {
        if (this.isOpen()) return;

        this.previouslyFocusedElement = document.activeElement;

        this.element.classList.add('modal--open');
        this.element.setAttribute('aria-hidden', 'false');

        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', this.handleKeydown);

        requestAnimationFrame(() => {
            this.trapFocus();
        });
    }

    hide() {
        this.element.dispatchEvent(new CustomEvent('modal:hide'));

        this.element.classList.remove('modal--open');
        this.element.setAttribute('aria-hidden', 'true');

        this.restoreScroll();
        document.removeEventListener('keydown', this.handleKeydown);

        this.clearContent();

        this.previouslyFocusedElement?.focus();
        this.previouslyFocusedElement = null;
    }

    showLoading(event) {
        const loadingText = event?.target?.dataset?.loadingText ?? 'Loading\u2026';
        if (this.hasBodyTarget) {
            this.bodyTarget.innerHTML =
                '<div class="flex flex-col items-center justify-center py-10 gap-3">' +
                '<svg class="w-8 h-8 animate-spin text-primary" viewBox="0 0 24 24" fill="none">' +
                '<path d="M12 2 A10 10 0 1 1 2 12" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-opacity="0.3" />' +
                '</svg>' +
                '<p class="text-sm text-secondary-text m-0">' + loadingText + '</p>' +
                '</div>';
        }
        if (this.hasFooterTarget) {
            this.footerTarget.innerHTML = '<div class="modal-footer"></div>';
        }
    }

    showSkeleton() {
        if (this.hasHeaderTarget) {
            this.headerTarget.innerHTML =
                '<div class="h-6 w-44 bg-neutral-dark rounded animate-pulse"></div>' +
                '<div class="h-6 w-6 bg-neutral-dark rounded animate-pulse"></div>';
        }
        if (this.hasBodyTarget) {
            this.bodyTarget.innerHTML =
                '<div class="modal-body space-y-3">' +
                '<div class="h-4 w-full bg-neutral-dark rounded animate-pulse"></div>' +
                '<div class="h-4 w-3/4 bg-neutral-dark rounded animate-pulse"></div>' +
                '<div class="h-4 w-1/2 bg-neutral-dark rounded animate-pulse"></div>' +
                '</div>';
        }
        if (this.hasFooterTarget) {
            this.footerTarget.innerHTML =
                '<div class="modal-footer">' +
                '<div class="h-9 w-20 bg-neutral-dark rounded-button animate-pulse"></div>' +
                '<div class="h-9 w-20 bg-neutral-dark rounded-button animate-pulse"></div>' +
                '</div>';
        }
    }

    backdropClick() {
        if (this.closeOnBackdropValue) {
            this.hide();
        }
    }

    handleKeydown(event) {
        if (event.key === 'Escape' && this.closeOnEscapeValue) {
            event.preventDefault();
            event.stopPropagation();
            this.hide();
            return;
        }

        if (event.key === 'Tab') {
            this.handleTabTrap(event);
        }
    }

    handleTurboBeforeCache() {
        if (this.isOpen()) {
            this.hide();
        }
    }

    isOpen() {
        return this.element.classList.contains('modal--open');
    }

    trapFocus() {
        if (!this.hasDialogTarget) return;

        const focusable = this.dialogTarget.querySelectorAll(FOCUSABLE);
        if (focusable.length > 0) {
            focusable[0].focus();
        }
    }

    handleTabTrap(event) {
        if (!this.hasDialogTarget) return;

        const focusable = Array.from(this.dialogTarget.querySelectorAll(FOCUSABLE));
        if (focusable.length === 0) return;

        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    }

    restoreScroll() {
        document.body.style.overflow = '';
    }

    clearContent() {
        if (this.hasHeaderTarget) this.headerTarget.innerHTML = '';
        if (this.hasBodyTarget) this.bodyTarget.innerHTML = '';
        if (this.hasFooterTarget) this.footerTarget.innerHTML = '<div id="modal-flashes"></div>';
    }
}
