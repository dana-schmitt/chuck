import { Controller } from '@hotwired/stimulus';

/*
 * Toggles an emoji reaction on a comment without reloading the page. Posts to
 * the reaction endpoint with a CSRF token, then reflects the updated counts
 * and pressed state on all reaction buttons for that comment.
 */
export default class extends Controller {
    static values = { url: String, token: String };
    static targets = ['button'];

    async toggle(event) {
        const emoji = event.currentTarget.dataset.emoji;

        let response;
        try {
            response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.tokenValue,
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json',
                },
                body: new URLSearchParams({ emoji }),
            });
        } catch (error) {
            return;
        }

        if (!response.ok) {
            return;
        }

        const data = await response.json();

        this.buttonTargets.forEach((button) => {
            const buttonEmoji = button.dataset.emoji;
            const countTarget = button.querySelector('[data-reaction-count]');
            if (countTarget) {
                countTarget.textContent = data.counts[buttonEmoji] ?? 0;
            }

            if (buttonEmoji === emoji) {
                button.classList.toggle('border-indigo-400', data.reacted);
                button.classList.toggle('bg-indigo-50', data.reacted);
                button.classList.toggle('border-gray-200', !data.reacted);
            }
        });
    }
}
