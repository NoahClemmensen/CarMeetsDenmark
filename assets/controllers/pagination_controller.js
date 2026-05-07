import { Controller } from '@hotwired/stimulus';

/**
 * Stimulus controller for pagination that delegates to filter-form controller.
 *
 * Usage:
 *   <div data-controller="pagination"
 *        data-pagination-filter-form-outlet="#concrete-filters">
 *     <button data-page="2" data-action="pagination#navigate">2</button>
 *   </div>
 *
 * Requirements:
 *   - filter-form controller must be present on the page with a unique ID
 *   - Page buttons must have data-page attribute with the target page number
 *   - Filter form must have a hidden input with name="page"
 */
export default class extends Controller {
    static outlets = ['filter-form'];

    /**
     * Navigate to a specific page by updating the form's page input
     * and triggering submission without resetting to page 1.
     *
     * @param {Event} event - Click event from pagination button
     */
    navigate(event) {
        event.preventDefault();

        const button = event.currentTarget;
        const page = button.dataset.page;

        if (!page) {
            console.error('Pagination button missing data-page attribute');
            return;
        }

        // Check if filter-form outlet is connected
        if (!this.hasFilterFormOutlet) {
            console.error('Pagination controller requires filter-form outlet');
            this.fallbackNavigate(page);
            return;
        }

        // Find the page input in the filter form
        const pageInput = this.filterFormOutlet.element.querySelector('[name="page"]');
        if (!pageInput) {
            console.error('Filter form missing page input [name="page"]');
            return;
        }

        // Update page value and submit without resetting
        pageInput.value = page;

        // Dispatch custom event for tracking/hooks
        this.filterFormOutlet.element.dispatchEvent(
            new CustomEvent('filter-form:paginate', {
                bubbles: true,
                detail: { page }
            })
        );

        // Trigger form submission without page reset (false = don't reset to page 1)
        this.filterFormOutlet.doSubmit(false);
    }

    /**
     * Fallback navigation when outlet is not available.
     * Attempts to find form manually and submit via fetch.
     *
     * @param {string} page - Target page number
     */
    fallbackNavigate(page) {
        const form = document.querySelector('[data-controller="filter-form"]');
        if (!form) {
            console.error('Could not find filter-form on page');
            return;
        }

        const pageInput = form.querySelector('[name="page"]');
        if (!pageInput) {
            console.error('Page input not found in form');
            return;
        }

        pageInput.value = page;

        const formData = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: formData,
            headers: { 'Accept': 'text/vnd.turbo-stream.html' }
        })
        .then(response => response.text())
        .then(html => Turbo.renderStreamMessage(html))
        .catch(err => {
            console.error('Pagination fallback error:', err);
        });
    }
}