/**
 * Write text to the clipboard.
 *
 * Uses the async Clipboard API when available, falling back to a
 * hidden-textarea + `execCommand('copy')` for older browsers or
 * insecure contexts.
 *
 * @param {string} text
 * @returns {Promise<void>}
 */
export async function writeToClipboard(text) {
    if (navigator.clipboard && window.isSecureContext !== false) {
        try {
            await navigator.clipboard.writeText(text);
            return;
        } catch (_) {
            // fall through to legacy path
        }
    }

    const textarea = document.createElement('textarea');
    textarea.value = text;
    textarea.setAttribute('readonly', '');
    textarea.style.position = 'fixed';
    textarea.style.top = '0';
    textarea.style.left = '0';
    textarea.style.opacity = '0';
    document.body.appendChild(textarea);
    textarea.select();
    try {
        const succeeded = document.execCommand('copy');
        if (!succeeded) {
            throw new Error('Clipboard copy failed');
        }
    } finally {
        document.body.removeChild(textarea);
    }
}
