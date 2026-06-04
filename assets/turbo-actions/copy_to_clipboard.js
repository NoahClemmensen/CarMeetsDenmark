/**
 * Custom Turbo Stream action: copy-to-clipboard.
 *
 * Response payload:
 *   <turbo-stream action="copy-to-clipboard" value="https://.../share/abc">
 *   </turbo-stream>
 *
 * Writes `value` to the system clipboard. Pure side effect, no DOM update.
 * If the clipboard write fails (insecure context, permission denied), a
 * warning toast is shown so the user knows the copy didn't land and can
 * use an explicit copy button instead.
 */
import * as Turbo from '@hotwired/turbo';
import { writeToClipboard } from '../utilities/clipboard.js';
import { showToast } from '../utilities/toast.js';

Turbo.StreamActions['copy-to-clipboard'] = async function () {
    const value = this.getAttribute('value');
    if (!value) return;
    try {
        await writeToClipboard(value);
    } catch (_) {
        showToast('Could not copy link to clipboard. Use the Copy button.', 'warning');
    }
};
