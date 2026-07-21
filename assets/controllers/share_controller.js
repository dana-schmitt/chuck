import { Controller } from '@hotwired/stimulus';

/*
 * Shares a joke's canonical URL via the native share sheet (if available),
 * falling back to copying it to the clipboard.
 */
export default class extends Controller {
    static values = { url: String };
    static targets = ['label'];

    async share() {
        if (navigator.share) {
            try {
                await navigator.share({ url: this.urlValue });

                return;
            } catch (error) {
                // User cancelled the native share sheet, or it failed - fall back to copying.
            }
        }

        try {
            await navigator.clipboard.writeText(this.urlValue);
            this.flashLabel('Copied!');
        } catch (error) {
            // Clipboard access denied/unavailable; nothing more we can do here.
        }
    }

    flashLabel(text) {
        if (!this.hasLabelTarget) {
            return;
        }

        const original = this.labelTarget.textContent;
        this.labelTarget.textContent = text;
        setTimeout(() => {
            this.labelTarget.textContent = original;
        }, 1500);
    }
}
