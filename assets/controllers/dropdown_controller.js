import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['trigger', 'menu'];

    connect() {
        this._handleClickOutside = this._handleClickOutside.bind(this);
    }

    disconnect() {
        document.removeEventListener('click', this._handleClickOutside);
    }

    toggle(event) {
        event?.stopPropagation();
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.element.classList.add('open');
        if (this.hasMenuTarget) {
            this.menuTarget.classList.remove('hidden');
        }
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', 'true');
        }
        document.addEventListener('click', this._handleClickOutside);
    }

    close() {
        this.element.classList.remove('open');
        if (this.hasMenuTarget) {
            this.menuTarget.classList.add('hidden');
        }
        if (this.hasTriggerTarget) {
            this.triggerTarget.setAttribute('aria-expanded', 'false');
        }
        document.removeEventListener('click', this._handleClickOutside);
    }

    get isOpen() {
        return this.element.classList.contains('open');
    }

    _handleClickOutside(event) {
        if (!this.element.contains(event.target)) {
            this.close();
        }
    }
}
