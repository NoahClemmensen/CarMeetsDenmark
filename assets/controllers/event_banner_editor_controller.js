import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['image', 'fileInput', 'removeCheckbox', 'removeBtn'];

    connect() {
        this.originalSrc = this.imageTarget.src;
        this.hasSavedBanner = this.element.dataset.hasSavedBanner === 'true';
        this.updateRemoveVisibility();
    }

    previewFile() {
        const file = this.fileInputTarget.files?.[0];
        if (!file) return;

        // A new upload undoes any pending removal of the saved banner.
        this.removeCheckboxTarget.checked = false;
        this.element.classList.remove('event-banner-editor--remove');

        const reader = new FileReader();
        reader.onload = (event) => {
            this.imageTarget.src = event.target.result;
        };
        reader.readAsDataURL(file);

        this.updateRemoveVisibility();
    }

    remove() {
        if (this.hasPendingFile()) {
            // Cancel the pending upload, revert preview to original.
            this.fileInputTarget.value = '';
            this.imageTarget.src = this.originalSrc;
        } else if (this.hasSavedBanner) {
            // Toggle removal of the already-saved banner.
            const willRemove = !this.removeCheckboxTarget.checked;
            this.removeCheckboxTarget.checked = willRemove;
            this.element.classList.toggle('event-banner-editor--remove', willRemove);
        }

        this.updateRemoveVisibility();
    }

    updateRemoveVisibility() {
        const shouldShow = this.hasPendingFile() || this.hasSavedBanner;
        this.removeBtnTarget.classList.toggle('hidden', !shouldShow);
    }

    hasPendingFile() {
        return (this.fileInputTarget.files?.length ?? 0) > 0;
    }
}
