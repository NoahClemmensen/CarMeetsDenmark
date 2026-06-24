/**
 * Tiny translation lookup for strings rendered from JavaScript.
 *
 * The catalog is published by the `_js_translations.html.twig` partial as
 * `window.appI18n`, keyed by message id (e.g. 'heatmap.error'). Returns the
 * key itself if the catalog or entry is missing, so a misconfiguration is
 * visible rather than throwing.
 */
export function t(key) {
    const dict = (typeof window !== 'undefined' && window.appI18n) || {};
    return Object.prototype.hasOwnProperty.call(dict, key) ? dict[key] : key;
}
