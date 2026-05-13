import { Controller } from '@hotwired/stimulus';

/**
 * Toggles between image-upload and embed-input states.
 * If images are selected, hides the embed input and vice versa.
 */
export default class extends Controller {
    static targets = ['imageInput', 'embedInput'];

    connect() {
        this.refresh();
        if (this.hasImageInputTarget) {
            this.imageInputTarget.addEventListener('change', () => this.refresh());
        }
        if (this.hasEmbedInputTarget) {
            this.embedInputTarget.addEventListener('input', () => this.refresh());
        }
    }

    refresh() {
        const hasImages = this.hasImageInputTarget && this.imageInputTarget.files && this.imageInputTarget.files.length > 0;
        const hasEmbed = this.hasEmbedInputTarget && this.embedInputTarget.value.trim() !== '';

        if (this.hasEmbedInputTarget) {
            this.embedInputTarget.disabled = hasImages;
        }
        if (this.hasImageInputTarget) {
            this.imageInputTarget.disabled = hasEmbed;
        }
    }
}
