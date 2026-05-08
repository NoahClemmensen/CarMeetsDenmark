import { Controller } from '@hotwired/stimulus';
import * as Turbo from '@hotwired/turbo';

export default class extends Controller {
    static values = {
        url: String,
        modalId: { type: String, default: 'app-modal' },
    };

    connect() {
        this.handleModalHide = this.handleModalHide.bind(this);
    }

    open(event) {
        event.preventDefault();

        const url = this.urlValue || this.element.getAttribute('href');
        if (!url) return;

        const modalElement = document.getElementById(this.modalIdValue);
        if (!modalElement) return;

        const modalController = this.application.getControllerForElementAndIdentifier(
            modalElement,
            'modal',
        );
        if (!modalController) return;

        // Abort any in-flight fetch from a previous open
        this.abortFetch();
        this.abortController = new AbortController();

        // Remove any stale listener before adding a new one
        modalElement.removeEventListener('modal:hide', this.handleModalHide);
        modalElement.addEventListener('modal:hide', this.handleModalHide, { once: true });

        modalController.showSkeleton();
        modalController.show();

        fetch(url, {
            headers: {
                Accept: 'text/vnd.turbo-stream.html',
                'X-Requested-With': 'XMLHttpRequest',
            },
            signal: this.abortController.signal,
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error(`Request failed with status ${response.status}`);
                }
                return response.text();
            })
            .then((html) => {
                // Only render if modal is still open
                if (modalController.isOpen()) {
                    Turbo.renderStreamMessage(html);
                }
            })
            .catch((error) => {
                if (error.name === 'AbortError') return;

                if (modalController.isOpen()) {
                    this.showError(modalController);
                }
            });
    }

    handleModalHide() {
        this.abortFetch();
    }

    disconnect() {
        this.abortFetch();
    }

    abortFetch() {
        if (this.abortController) {
            this.abortController.abort();
            this.abortController = null;
        }
    }

    showError(modalController) {
        if (modalController.hasHeaderTarget) {
            modalController.headerTarget.innerHTML =
                '<h2 class="modal-title">Error</h2>' +
                '<button type="button" class="modal-close" data-action="click->modal#hide" aria-label="Close">' +
                '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20" fill="currentColor" class="w-5 h-5"><path d="M6.28 5.22a.75.75 0 0 0-1.06 1.06L8.94 10l-3.72 3.72a.75.75 0 1 0 1.06 1.06L10 11.06l3.72 3.72a.75.75 0 1 0 1.06-1.06L11.06 10l3.72-3.72a.75.75 0 0 0-1.06-1.06L10 8.94 6.28 5.22z" /></svg>' +
                '</button>';
        }
        if (modalController.hasBodyTarget) {
            modalController.bodyTarget.innerHTML =
                '<div class="py-6 text-center text-secondary-text">' +
                '<p>Something went wrong loading this content.</p>' +
                '<p class="text-sm mt-1">Please try again.</p>' +
                '</div>';
        }
        if (modalController.hasFooterTarget) {
            modalController.footerTarget.innerHTML =
                '<div class="modal-footer">' +
                '<button type="button" class="btn-grey" data-action="click->modal#hide">Close</button>' +
                '</div>';
        }
    }
}
