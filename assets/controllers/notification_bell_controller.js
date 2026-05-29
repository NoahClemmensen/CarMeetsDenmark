import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['badge', 'panel'];
    static values = { url: String };

    connect() {
        this.outsideClickHandler = this.handleOutsideClick.bind(this);
        this.escapeHandler = this.handleEscape.bind(this);
    }

    disconnect() {
        document.removeEventListener('click', this.outsideClickHandler);
        document.removeEventListener('keydown', this.escapeHandler);
    }

    async toggle(event) {
        event.stopPropagation();
        if (!this.hasPanelTarget) {
            await this.createPanel();
            return;
        }
        if (this.panelTarget.dataset.open === 'true') {
            this.closePanel();
        } else {
            this.openPanel();
        }
    }

    async createPanel() {
        const panel = document.createElement('div');
        panel.setAttribute('data-notification-bell-target', 'panel');
        panel.dataset.open = 'false';
        Object.assign(panel.style, {
            position: 'absolute',
            top: '100%',
            right: '0',
            marginTop: '0.5rem',
            zIndex: '50',
            transition: 'opacity 180ms ease-out, transform 180ms ease-out',
            transformOrigin: 'top right',
            opacity: '0',
            transform: 'translateY(-4px) scale(0.97)',
            pointerEvents: 'none',
        });
        this.element.appendChild(panel);

        try {
            const response = await fetch(this.urlValue, { headers: { Accept: 'text/html' } });
            panel.innerHTML = await response.text();
        } catch (e) {
            panel.innerHTML = '<div class="p-6 text-center text-sm text-error">Could not load notifications.</div>';
        }

        requestAnimationFrame(() => this.openPanel());

        if (this.hasBadgeTarget) {
            this.badgeTarget.remove();
        }
    }

    openPanel() {
        const p = this.panelTarget;
        p.dataset.open = 'true';
        p.style.opacity = '1';
        p.style.transform = 'translateY(0) scale(1)';
        p.style.pointerEvents = 'auto';
        setTimeout(() => {
            document.addEventListener('click', this.outsideClickHandler);
            document.addEventListener('keydown', this.escapeHandler);
        }, 0);
    }

    closePanel() {
        const p = this.panelTarget;
        p.dataset.open = 'false';
        p.style.opacity = '0';
        p.style.transform = 'translateY(-4px) scale(0.97)';
        p.style.pointerEvents = 'none';
        document.removeEventListener('click', this.outsideClickHandler);
        document.removeEventListener('keydown', this.escapeHandler);
    }

    handleOutsideClick(event) {
        if (!this.element.contains(event.target)) {
            this.closePanel();
        }
    }

    handleEscape(event) {
        if (event.key === 'Escape') {
            this.closePanel();
        }
    }
}
