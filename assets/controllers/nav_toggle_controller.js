import { Controller } from '@hotwired/stimulus';

export default class extends Controller {
    static targets = ['menu', 'openIcon', 'closeIcon'];

    connect() {
        this.boundCloseOnEscape = this.closeOnEscape.bind(this);
        document.addEventListener('keydown', this.boundCloseOnEscape);
    }

    disconnect() {
        document.removeEventListener('keydown', this.boundCloseOnEscape);
    }

    toggle() {
        this.open = !this.open;
    }

    close() {
        this.open = false;
    }

    closeOnClickOutside(event) {
        if (this.open && !this.element.contains(event.target)) {
            this.close();
        }
    }

    closeOnEscape(event) {
        if (event.key === 'Escape') {
            this.close();
        }
    }

    get open() {
        return !this.menuTarget.classList.contains('hidden');
    }

    set open(value) {
        this.menuTarget.classList.toggle('hidden', !value);
        this.openIconTarget.classList.toggle('hidden', value);
        this.closeIconTarget.classList.toggle('hidden', !value);
        this.element.classList.toggle('shadow-lg', value);
        this.element.classList.toggle('bg-gray-400', value);
        this.element.classList.toggle('bg-gray-600', !value);
    }
}
