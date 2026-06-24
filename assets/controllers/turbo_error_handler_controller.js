import { Controller } from '@hotwired/stimulus';
import { t } from '../utilities/i18n.js';

export default class extends Controller {
    connect() {
        this.handleFrameMissing = this.handleFrameMissing.bind(this);
        this.handleFetchError = this.handleFetchError.bind(this);

        document.addEventListener('turbo:frame-missing', this.handleFrameMissing);
        document.addEventListener('turbo:fetch-request-error', this.handleFetchError);
    }

    disconnect() {
        document.removeEventListener('turbo:frame-missing', this.handleFrameMissing);
        document.removeEventListener('turbo:fetch-request-error', this.handleFetchError);
    }

    handleFrameMissing(event) {
        // Only intercept frame errors inside the modal; let others use Turbo's default behavior
        const frame = event.target;
        if (frame.closest('#app-modal')) {
            event.preventDefault();
            this.showErrorToast(t('errors.content_load'));
        }
    }

    handleFetchError(event) {
        event.preventDefault();
        this.showErrorToast(t('errors.network'));
    }

    showErrorToast(message) {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        toast.setAttribute('data-controller', 'toast');
        toast.setAttribute('data-toast-fade-out-after-ms-value', '8000');
        toast.setAttribute('data-toast-dismissable-value', 'true');
        toast.setAttribute('data-state', 'entering');
        toast.setAttribute('class', 'toast toast--error');
        toast.setAttribute('role', 'alert');
        toast.innerHTML =
            '<div class="toast__content"><span class="toast__message">' +
            this.escapeHtml(message) +
            '</span></div>' +
            '<button type="button" class="toast__close" data-action="click->toast#dismiss" aria-label="' + this.escapeHtml(t('common.dismiss')) + '">&times;</button>';

        container.prepend(toast);
    }

    escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
}
