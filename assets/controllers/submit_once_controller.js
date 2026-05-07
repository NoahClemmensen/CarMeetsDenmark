import { Controller } from '@hotwired/stimulus';

/**
 * Disables the submit button on first click and ignores subsequent submits.
 * Prevents accidental double-submission on forms that mutate server state
 * (e.g. the support-mode distributor switcher cards).
 */
export default class extends Controller {
    submit(event) {
        const button = event.submitter ?? this.element.querySelector('button[type="submit"]');
        if (!button) return;
        if (button.dataset.submitting === 'true') {
            event.preventDefault();
            return;
        }
        button.dataset.submitting = 'true';
        button.disabled = true;
    }
}
