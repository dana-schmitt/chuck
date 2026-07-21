import { Controller } from '@hotwired/stimulus';

/*
 * Toggles a "like" on a joke without reloading the page. Posts to the like
 * endpoint with a CSRF token, then reflects the new state on the button.
 * On the liked-jokes page the element removes itself once unliked.
 */
export default class extends Controller {
    static values = {
        url: String,
        token: String,
        liked: Boolean,
        removeOnUnlike: Boolean,
    };

    static targets = ['icon', 'count'];

    async toggle() {
        let response;
        try {
            response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.tokenValue,
                    Accept: 'application/json',
                },
            });
        } catch (error) {
            return;
        }

        if (!response.ok) {
            return;
        }

        const data = await response.json();
        this.likedValue = data.liked;

        if (this.hasCountTarget && typeof data.likeCount === 'number') {
            this.countTarget.textContent = data.likeCount;
        }

        if (!this.likedValue && this.removeOnUnlikeValue) {
            this.element.remove();
        }
    }

    likedValueChanged() {
        const element = this.hasIconTarget ? this.iconTarget : this.element;
        element.classList.toggle('text-red-500', this.likedValue);
        element.classList.toggle('text-gray-300', !this.likedValue);
        this.element.setAttribute('aria-pressed', this.likedValue ? 'true' : 'false');
    }
}
