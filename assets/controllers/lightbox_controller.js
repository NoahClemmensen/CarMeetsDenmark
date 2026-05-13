import { Controller } from '@hotwired/stimulus';

/**
 * Simple image lightbox. Click an image, opens a fullscreen modal.
 * Reads the data-lightbox-images-value JSON array on the host element.
 */
export default class extends Controller {
    static values = { images: Array };

    open(event) {
        const idx = parseInt(event.params.index, 10) || 0;
        const images = this.imagesValue;
        if (!images.length) return;

        let currentIdx = idx;
        const overlay = document.createElement('div');
        overlay.className = 'fixed inset-0 bg-black/90 z-50 flex items-center justify-center';
        overlay.innerHTML = `
            <button type="button" aria-label="Close" class="absolute top-4 right-4 text-white text-3xl">&times;</button>
            <button type="button" aria-label="Previous" class="absolute left-4 text-white text-3xl">&lsaquo;</button>
            <img src="${images[currentIdx]}" class="max-h-[90vh] max-w-[90vw] object-contain"/>
            <button type="button" aria-label="Next" class="absolute right-4 text-white text-3xl">&rsaquo;</button>
        `;

        const [closeBtn, prevBtn, imgEl, nextBtn] = [
            overlay.children[0], overlay.children[1], overlay.children[2], overlay.children[3]
        ];
        const update = () => { imgEl.src = images[currentIdx]; };

        closeBtn.addEventListener('click', () => overlay.remove());
        overlay.addEventListener('click', (e) => { if (e.target === overlay) overlay.remove(); });
        prevBtn.addEventListener('click', () => { currentIdx = (currentIdx - 1 + images.length) % images.length; update(); });
        nextBtn.addEventListener('click', () => { currentIdx = (currentIdx + 1) % images.length; update(); });

        document.body.appendChild(overlay);
    }
}
