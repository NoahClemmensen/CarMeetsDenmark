import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['timezoneInput', 'languageInput']

    connect() {
        const language = navigator.language;
        const timezone = Intl.DateTimeFormat().resolvedOptions().timeZone;

        if (this.timezoneInputTarget.value === '') {
            this.timezoneInputTarget.value = timezone;
        }

        if (this.languageInputTarget.value === '') {
            this.languageInputTarget.value = language;
        }
    }
}
