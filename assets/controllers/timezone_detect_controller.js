import { Controller } from '@hotwired/stimulus';

/**
 * Auto-fills an input with the browser's detected IANA timezone if it's empty.
 *
 * Usage:
 *   <form data-controller="timezone-detect">
 *     <input data-timezone-detect-target="input">
 *   </form>
 */
export default class extends Controller {
    static targets = ['input'];

    connect() {
        if (!this.hasInputTarget) return;
        if (this.inputTarget.value !== '') return;

        this.inputTarget.value = Intl.DateTimeFormat().resolvedOptions().timeZone;
    }
}
