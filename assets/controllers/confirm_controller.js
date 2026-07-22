import { Controller } from '@hotwired/stimulus';

/*
 * Generic "are you sure?" confirmation for a form submit, kept as a Stimulus
 * controller (rather than an inline onsubmit="") so no CSP exception is
 * needed for inline event handlers.
 */
export default class extends Controller {
    static values = { message: String };

    confirm(event) {
        if (!window.confirm(this.messageValue)) {
            event.preventDefault();
        }
    }
}
