import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for filter forms with Turbo Stream updates.
 *
 * Usage:
 *   <form data-controller="filter-form"
 *         data-filter-form-targets-value='["target-id-1", "target-id-2"]'
 *         data-filter-form-default-synced-value="all">
 *     <select data-filter-form-target="autoSubmit" data-action="change->filter-form#submit">
 *     <input data-filter-form-target="search" data-action="input->filter-form#debouncedSubmit">
 *   </form>
 */
export default class extends Controller {
    static targets = ['autoSubmit', 'search', 'page', 'clearGroup', 'spinnerGroup'];

    static values = {
        targets: Array,           // Turbo Stream target IDs to add loading class
        defaultSynced: { type: String, default: 'all' },
        debounce: { type: Number, default: 400 }
    };

    connect() {
        this.abortController = null;
        this.debounceTimer = null;
        this.updateClearButtonVisibility();
    }

    disconnect() {
        if (this.abortController) {
            this.abortController.abort();
        }
        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }
    }

    submit(event) {
        if (event) {
            event.preventDefault();
        }
        this.updateClearButtonVisibility();
        this.doSubmit(true);
    }

    submitWithoutReset(event) {
        if (event) {
            event.preventDefault();
        }
        this.doSubmit(false);
    }

    debouncedSubmit() {
        this.updateClearButtonVisibility();

        if (this.debounceTimer) {
            clearTimeout(this.debounceTimer);
        }

        this.debounceTimer = setTimeout(() => {
            this.doSubmit(true);
        }, this.debounceValue);
    }

    searchKeydown(event) {
        if (event.key === 'Enter') {
            event.preventDefault();
            if (this.debounceTimer) {
                clearTimeout(this.debounceTimer);
            }
            this.doSubmit(true);
        }
    }

    paginate(event) {
        event.preventDefault();
        const page = event.currentTarget.dataset.page;
        if (page && this.hasPageTarget) {
            this.pageTarget.value = page;
            this.doSubmit(false);
        }
    }

    clear() {
        // Reset form and reload page
        window.location.href = this.element.action;
    }

    doSubmit(resetPage) {
        if (resetPage && this.hasPageTarget) {
            this.pageTarget.value = '1';
        }

        const formData = new FormData(this.element);

        // Add loading state to targets
        this.targetsValue.forEach(targetId => {
            const target = document.getElementById(targetId);
            if (target) {
                target.classList.add('loading');
            }
        });

        this.showSpinner();

        // Abort previous request
        if (this.abortController) {
            this.abortController.abort();
        }
        this.abortController = new AbortController();

        fetch(this.element.action, {
            method: 'POST',
            body: formData,
            headers: {
                'Accept': 'text/vnd.turbo-stream.html'
            },
            signal: this.abortController.signal
        })
        .then(response => response.text())
        .then(html => {
            Turbo.renderStreamMessage(html);
        })
        .catch(err => {
            if (err.name === 'AbortError') {
                return;
            }
            console.error('Filter error:', err);
            // Fallback to regular form submit
            this.element.submit();
        })
        .finally(() => {
            this.targetsValue.forEach(targetId => {
                const target = document.getElementById(targetId);
                if (target) {
                    target.classList.remove('loading');
                }
            });
            this.hideSpinner();
        });
    }

    updateClearButtonVisibility() {
        if (!this.hasClearGroupTarget) return;

        const search = this.hasSearchTarget ? this.searchTarget.value.trim() : '';
        let hasNonDefault = search !== '';

        // Check all autoSubmit targets (selects) for non-default values
        // This makes it work with any number or type of autoSubmit targets, not just the hardcoded ones
        if (!hasNonDefault) {
            this.autoSubmitTargets.forEach(el => {
                const name = el.getAttribute('name');
                const value = el.value || '';
                if (name === 'synced') {
                    if (value !== this.defaultSyncedValue) hasNonDefault = true;
                } else if (value !== '') {
                    hasNonDefault = true;
                }
            });
        }

        this.clearGroupTarget.style.display = hasNonDefault ? '' : 'none';
    }

    showSpinner() {
        if (this.hasSpinnerGroupTarget) {
            this.spinnerGroupTarget.style.display = '';
        }
    }

    hideSpinner() {
        if (this.hasSpinnerGroupTarget) {
            this.spinnerGroupTarget.style.display = 'none';
        }
    }
}
