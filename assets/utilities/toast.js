/**
 * Show a client-side toast.
 *
 * Builds a toast element matching templates/web/_turbo/toast_stream.html.twig
 * and prepends it to #toast-container. The existing `toast` Stimulus controller
 * picks it up on connect, handling fade-in / auto-dismiss / close button.
 *
 * @param {string} message
 * @param {'success'|'error'|'warning'|'info'} [type='success']
 * @param {{ fadeOutAfterMs?: number, dismissable?: boolean }} [opts]
 */
export function showToast(message, type = 'success', opts = {}) {
    const container = document.getElementById('toast-container');
    if (!container) return;

    const validTypes = ['success', 'error', 'warning', 'info'];
    const toastType = validTypes.includes(type) ? type : 'success';

    const fadeOutAfterMs = opts.fadeOutAfterMs ?? 5000;
    const dismissable = opts.dismissable ?? true;

    const toast = document.createElement('div');
    toast.setAttribute('data-controller', 'toast');
    toast.setAttribute('data-toast-fade-out-after-ms-value', String(fadeOutAfterMs));
    toast.setAttribute('data-toast-dismissable-value', dismissable ? 'true' : 'false');
    toast.setAttribute('data-state', 'entering');
    toast.className = `toast toast--${toastType}`;
    toast.setAttribute('role', 'alert');

    const content = document.createElement('div');
    content.className = 'toast__content';
    const span = document.createElement('span');
    span.className = 'toast__message';
    span.textContent = message;
    content.appendChild(span);
    toast.appendChild(content);

    if (dismissable) {
        const btn = document.createElement('button');
        btn.type = 'button';
        btn.className = 'toast__close';
        btn.setAttribute('data-action', 'click->toast#dismiss');
        btn.setAttribute('aria-label', 'Dismiss');
        btn.textContent = '×';
        toast.appendChild(btn);
    }

    container.prepend(toast);
}
