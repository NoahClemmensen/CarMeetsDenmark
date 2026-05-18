import { Controller } from '@hotwired/stimulus';

/**
 * Re-runs Instagram embed processing whenever a new IG blockquote enters the DOM
 * (Turbo Frame load, Turbo Stream replace, etc.).
 */
export default class extends Controller {
    connect() {
        if (window.instgrm && window.instgrm.Embeds) {
            window.instgrm.Embeds.process();
        }
    }
}
