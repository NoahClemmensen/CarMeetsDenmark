/**
 * Custom Turbo Stream action: modal-hide.
 *
 * Response payload:
 *   <turbo-stream action="modal-hide" modal-id="app-modal">
 *     <template></template>
 *   </turbo-stream>
 *
 * Dispatches a `modal:request-hide` event on the named modal element.
 * The modal Stimulus controller listens for this and calls hide().
 * Defaults modal-id to `app-modal`.
 */
import * as Turbo from '@hotwired/turbo';

Turbo.StreamActions['modal-hide'] = function () {
    const modalId = this.getAttribute('modal-id') || 'app-modal';
    const modalElement = document.getElementById(modalId);
    if (!modalElement) return;
    modalElement.dispatchEvent(new CustomEvent('modal:request-hide', { bubbles: false }));
};
