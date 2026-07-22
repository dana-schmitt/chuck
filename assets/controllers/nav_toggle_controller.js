import { Controller } from '@hotwired/stimulus';

/*
 * Drives the mobile sidebar drawer: slides the menu in/out of view and
 * shows/hides its backdrop. On large screens the sidebar is always visible
 * (via lg: classes in the template) and this controller has no effect.
 */
export default class extends Controller {
    static targets = ['menu', 'backdrop', 'openIcon', 'closeIcon'];

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
        return !this.menuTarget.classList.contains('-translate-x-full');
    }

    set open(value) {
        this.menuTarget.classList.toggle('-translate-x-full', !value);

        if (this.hasBackdropTarget) {
            this.backdropTarget.classList.toggle('hidden', !value);
        }
        if (this.hasOpenIconTarget) {
            this.openIconTarget.classList.toggle('hidden', value);
        }
        if (this.hasCloseIconTarget) {
            this.closeIconTarget.classList.toggle('hidden', !value);
        }
    }
}
