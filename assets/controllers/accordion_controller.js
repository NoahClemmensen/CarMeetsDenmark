import { Controller } from '@hotwired/stimulus';

/**
 * Accordion controller — smooth height-animated collapsible panel.
 *
 * Usage:
 *   <div data-controller="accordion">
 *     <button data-action="click->accordion#toggle" aria-expanded="false">
 *       Title
 *       <svg data-accordion-target="icon">...</svg>
 *     </button>
 *     <div data-accordion-target="panel">
 *       ...content...
 *     </div>
 *   </div>
 *
 * Add `data-accordion-open-value="true"` to start expanded.
 */
export default class extends Controller {
    static targets = ['panel', 'icon'];
    static values = { open: { type: Boolean, default: false }, defaultOpen: { type: Boolean, default: false } };

    connect() {
        if (this.defaultOpenValue && !this.openValue) {
            this.openValue = true;
        }

        this.panelTarget.style.overflow = 'hidden';
        this.panelTarget.style.transition = 'height 240ms cubic-bezier(0.4, 0, 0.2, 1)';

        if (this.hasIconTarget) {
            this.iconTarget.style.transition = 'transform 240ms cubic-bezier(0.4, 0, 0.2, 1)';
        }

        if (this.openValue) {
            this.element.classList.add('open');
            this.panelTarget.style.height = 'auto';
            this._setIconRotation(true);
            this._updateTriggerAriaExpanded(true);
        } else {
            this.panelTarget.style.height = '0px';
            this._setIconRotation(false);
        }
    }

    toggle() {
        this.isOpen ? this.close() : this.open();
    }

    open() {
        this.element.classList.add('open');
        this.panelTarget.style.height = `${this.panelTarget.scrollHeight}px`;
        this._setIconRotation(true);
        this._updateTriggerAriaExpanded(true);

        this.panelTarget.addEventListener('transitionend', (event) => {
            if (event.target === this.panelTarget && event.propertyName === 'height' && this.isOpen) {
                this.panelTarget.style.height = 'auto';
            }
        }, { once: true });
    }

    close() {
        // Lock to fixed px before collapsing (required when height is 'auto')
        this.panelTarget.style.height = `${this.panelTarget.scrollHeight}px`;
        this.panelTarget.offsetHeight; // force reflow
        this.panelTarget.style.height = '0px';

        this.element.classList.remove('open');
        this._setIconRotation(false);
        this._updateTriggerAriaExpanded(false);
    }

    get isOpen() {
        return this.element.classList.contains('open');
    }

    _setIconRotation(open) {
        if (this.hasIconTarget) {
            this.iconTarget.style.transform = open ? 'rotate(180deg)' : 'rotate(0deg)';
        }
    }

    _updateTriggerAriaExpanded(open) {
        const trigger = this.element.querySelector('[data-action*="accordion#toggle"]');
        if (trigger) {
            trigger.setAttribute('aria-expanded', open ? 'true' : 'false');
        }
    }
}
