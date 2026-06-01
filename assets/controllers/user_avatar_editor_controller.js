import { Controller } from '@hotwired/stimulus';

const OUTPUT_SIZE = 400; // pixels, what we save server-side

export default class extends Controller {
    static targets = [
        'image', 'initial', 'fileInput', 'removeCheckbox', 'removeBtn',
        'cropper', 'cropFrame', 'cropImage', 'zoom',
    ];

    connect() {
        this.originalSrc = this.imageTarget.getAttribute('src') || '';
        this.hasSavedAvatar = this.element.dataset.hasSavedAvatar === 'true';
        this.cropState = null;
        this.pan = null;

        this.onPanMove = this.onPanMove.bind(this);
        this.onPanEnd = this.onPanEnd.bind(this);
        this.onKeyDown = this.onKeyDown.bind(this);

        this.updateRemoveVisibility();
    }

    disconnect() {
        window.removeEventListener('pointermove', this.onPanMove);
        window.removeEventListener('pointerup', this.onPanEnd);
        document.removeEventListener('keydown', this.onKeyDown);
        this.restoreScroll();
    }

    previewFile() {
        const file = this.fileInputTarget.files?.[0];
        if (!file) return;

        // A new upload undoes any pending removal.
        this.removeCheckboxTarget.checked = false;
        this.element.classList.remove('user-avatar-editor--remove');

        const reader = new FileReader();
        reader.onload = (event) => {
            this.openCropModal(event.target.result);
        };
        reader.readAsDataURL(file);
    }

    openCropModal(src) {
        this.cropImageTarget.onload = () => this.initCropState();
        this.cropImageTarget.src = src;
        this.zoomTarget.value = '1';

        this.cropperTarget.classList.add('modal--open');
        this.cropperTarget.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
        document.addEventListener('keydown', this.onKeyDown);
    }

    closeCropModal() {
        this.cropperTarget.classList.remove('modal--open');
        this.cropperTarget.setAttribute('aria-hidden', 'true');
        this.restoreScroll();
        document.removeEventListener('keydown', this.onKeyDown);
    }

    onKeyDown(event) {
        if (event.key === 'Escape') {
            event.preventDefault();
            this.cancelCrop();
        }
    }

    restoreScroll() {
        document.body.style.overflow = '';
    }

    initCropState() {
        const frameSize = this.cropFrameTarget.clientWidth;
        const imgW = this.cropImageTarget.naturalWidth;
        const imgH = this.cropImageTarget.naturalHeight;
        const minScale = frameSize / Math.min(imgW, imgH);

        this.cropState = {
            frameSize,
            imgW,
            imgH,
            minScale,
            scale: minScale,
            offsetX: (frameSize - imgW * minScale) / 2,
            offsetY: (frameSize - imgH * minScale) / 2,
        };
        this.zoomTarget.value = '1';
        this.applyCropTransform();
    }

    zoomChanged() {
        if (!this.cropState) return;
        const zoom = parseFloat(this.zoomTarget.value);
        const { frameSize, minScale, scale: oldScale, offsetX, offsetY } = this.cropState;
        const newScale = minScale * zoom;

        const centerX = frameSize / 2;
        const centerY = frameSize / 2;
        const ratio = newScale / oldScale;

        this.cropState.scale = newScale;
        this.cropState.offsetX = centerX - (centerX - offsetX) * ratio;
        this.cropState.offsetY = centerY - (centerY - offsetY) * ratio;
        this.clampOffsets();
        this.applyCropTransform();
    }

    startPan(event) {
        if (!this.cropState) return;
        event.preventDefault();
        this.pan = {
            startX: event.clientX,
            startY: event.clientY,
            originX: this.cropState.offsetX,
            originY: this.cropState.offsetY,
        };
        window.addEventListener('pointermove', this.onPanMove);
        window.addEventListener('pointerup', this.onPanEnd);
    }

    onPanMove(event) {
        if (!this.pan || !this.cropState) return;
        const dx = event.clientX - this.pan.startX;
        const dy = event.clientY - this.pan.startY;
        this.cropState.offsetX = this.pan.originX + dx;
        this.cropState.offsetY = this.pan.originY + dy;
        this.clampOffsets();
        this.applyCropTransform();
    }

    onPanEnd() {
        this.pan = null;
        window.removeEventListener('pointermove', this.onPanMove);
        window.removeEventListener('pointerup', this.onPanEnd);
    }

    clampOffsets() {
        const { frameSize, imgW, imgH, scale } = this.cropState;
        const scaledW = imgW * scale;
        const scaledH = imgH * scale;
        const minX = frameSize - scaledW;
        const minY = frameSize - scaledH;
        this.cropState.offsetX = Math.min(0, Math.max(minX, this.cropState.offsetX));
        this.cropState.offsetY = Math.min(0, Math.max(minY, this.cropState.offsetY));
    }

    applyCropTransform() {
        const { offsetX, offsetY, scale } = this.cropState;
        this.cropImageTarget.style.transform =
            `translate(${offsetX}px, ${offsetY}px) scale(${scale})`;
    }

    /**
     * Commit the framed crop: render to canvas, replace the file in the input,
     * update the small preview, and close the modal. The form then submits
     * normally with the cropped file.
     */
    async applyCrop() {
        if (!this.cropState) {
            this.closeCropModal();
            return;
        }

        try {
            const dataUrl = await this.renderCropToFile();
            this.imageTarget.src = dataUrl;
            this.imageTarget.classList.remove('hidden');
            this.initialTarget.classList.add('hidden');
        } catch (err) {
            console.error('Avatar crop failed', err);
            // Leave the original picked file in place so the form still works.
        }

        this.closeCropModal();
        this.updateRemoveVisibility();
    }

    cancelCrop() {
        // Discard the picked file and revert the small preview.
        this.fileInputTarget.value = '';
        if (this.originalSrc) {
            this.imageTarget.src = this.originalSrc;
            this.imageTarget.classList.remove('hidden');
            this.initialTarget.classList.add('hidden');
        } else {
            this.imageTarget.src = '';
            this.imageTarget.classList.add('hidden');
            this.initialTarget.classList.remove('hidden');
        }
        this.cropState = null;
        this.closeCropModal();
        this.updateRemoveVisibility();
    }

    remove() {
        if (this.hasPendingFile()) {
            this.cancelCrop();
            return;
        }
        if (this.hasSavedAvatar) {
            const willRemove = !this.removeCheckboxTarget.checked;
            this.removeCheckboxTarget.checked = willRemove;
            this.element.classList.toggle('user-avatar-editor--remove', willRemove);
            this.imageTarget.classList.toggle('hidden', willRemove);
            this.initialTarget.classList.toggle('hidden', !willRemove);
        }
        this.updateRemoveVisibility();
    }

    updateRemoveVisibility() {
        const shouldShow = this.hasPendingFile() || this.hasSavedAvatar;
        this.removeBtnTarget.classList.toggle('hidden', !shouldShow);
    }

    hasPendingFile() {
        return (this.fileInputTarget.files?.length ?? 0) > 0;
    }

    async renderCropToFile() {
        const { frameSize, scale, offsetX, offsetY } = this.cropState;
        const canvas = document.createElement('canvas');
        canvas.width = OUTPUT_SIZE;
        canvas.height = OUTPUT_SIZE;
        const ctx = canvas.getContext('2d');

        const sx = -offsetX / scale;
        const sy = -offsetY / scale;
        const sSize = frameSize / scale;

        ctx.drawImage(
            this.cropImageTarget,
            sx, sy, sSize, sSize,
            0, 0, OUTPUT_SIZE, OUTPUT_SIZE,
        );

        const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', 0.9));
        if (!blob) throw new Error('Canvas produced no blob');

        const original = this.fileInputTarget.files[0];
        const baseName = (original?.name || 'avatar').replace(/\.[^.]+$/, '');
        const cropped = new File([blob], `${baseName}.jpg`, { type: 'image/jpeg' });

        const dt = new DataTransfer();
        dt.items.add(cropped);
        this.fileInputTarget.files = dt.files;

        return canvas.toDataURL('image/jpeg', 0.9);
    }
}
