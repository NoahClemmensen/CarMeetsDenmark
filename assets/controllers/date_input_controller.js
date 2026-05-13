import { Controller } from '@hotwired/stimulus';

/**
 * Tags a date/time input with `is-empty` whenever its value is empty, so CSS can
 * dim the format hint ("dd.mm.åååå, --.--") to match the placeholder color of
 * regular text inputs. Works for both required and optional inputs (unlike
 * `:invalid`, which only fires when `required` is set).
 */
export default class extends Controller {
    connect() {
        this.sync();
    }

    sync() {
        this.element.classList.toggle('is-empty', this.element.value === '');
    }
}
