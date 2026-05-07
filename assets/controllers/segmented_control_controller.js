import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    select(event) {
        const button = event.currentTarget;
        const value = button.dataset.segmentedControlValueParam;

        this.element.querySelector('select').value = value;

        this.element.querySelectorAll('.segmented-option').forEach(btn => {
            btn.classList.remove('segmented-option--active');
        });
        button.classList.add('segmented-option--active');
    }
}
