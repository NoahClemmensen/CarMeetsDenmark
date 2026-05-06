/**
 * Custom Turbo Stream action: redirect.
 *
 * Response payload:
 *   <turbo-stream action="redirect" url="/somewhere"
 *                 toast-message="..." toast-type="success">
 *     <template></template>
 *   </turbo-stream>
 *
 * If toast-message is set, it is stashed in sessionStorage so it can be
 * rendered after Turbo.visit loads the destination page. The `turbo:load`
 * listener below picks it up once.
 */
import * as Turbo from '@hotwired/turbo';
import { showToast } from '../utilities/toast.js';

Turbo.StreamActions.redirect = function () {
    const url = this.getAttribute('url');
    const toastMessage = this.getAttribute('toast-message');
    if (toastMessage) {
        sessionStorage.setItem('pending-toast', JSON.stringify({
            message: toastMessage,
            type: this.getAttribute('toast-type') || 'success',
        }));
    }
    if (url) {
        requestAnimationFrame(() => Turbo.visit(url));
    }
};

document.addEventListener('turbo:load', () => {
    const raw = sessionStorage.getItem('pending-toast');
    if (!raw) return;
    sessionStorage.removeItem('pending-toast');

    let parsed;
    try {
        parsed = JSON.parse(raw);
    } catch {
        return;
    }

    showToast(parsed.message, parsed.type);
});
