import { Controller } from '@hotwired/stimulus';
import { writeToClipboard } from '../utilities/clipboard.js';

/**
 * Copy a URL to the clipboard and briefly swap the button label to confirm.
 *
 * Usage:
 *   <div data-controller="share">
 *     <input data-share-target="input" readonly value="...">
 *     <button data-action="click->share#copy" data-share-target="button">Copy</button>
 *   </div>
 */
export default class extends Controller {
    static targets = ['input', 'button'];
    static values = { confirmText: { type: String, default: 'Copied!' }, resetMs: { type: Number, default: 1500 } };

    async copy() {
        if (!this.hasInputTarget) return;

        try {
            await writeToClipboard(this.inputTarget.value);
        } catch (_) {
            return;
        }

        if (!this.hasButtonTarget) return;

        this.inputTarget.select();

        const original = this.buttonTarget.textContent;
        this.buttonTarget.textContent = this.confirmTextValue;
        clearTimeout(this._timeout);
        this._timeout = setTimeout(() => {
            this.buttonTarget.textContent = original;
        }, this.resetMsValue);
    }

    disconnect() {
        clearTimeout(this._timeout);
    }
}
