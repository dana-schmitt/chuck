import { Controller } from '@hotwired/stimulus';

/*
 * Requests an AI explanation of the joke's wordplay/cultural context in the selected language.
 * Renders the response via textContent (never innerHTML) so nothing the LLM returns is ever
 * interpreted as HTML.
 */
export default class extends Controller {
    static values = { url: String, token: String };
    static targets = ['result', 'localeButton', 'explainButton'];

    connect() {
        this.locale = (navigator.language || '').toLowerCase().startsWith('de') ? 'de' : 'en';
        this.updateLocaleButtons();
    }

    setLocale(event) {
        this.locale = event.currentTarget.dataset.locale;
        this.updateLocaleButtons();
    }

    updateLocaleButtons() {
        this.localeButtonTargets.forEach((button) => {
            const active = button.dataset.locale === this.locale;
            button.classList.toggle('text-zinc-100', active);
            button.classList.toggle('border-indigo-500', active);
            button.classList.toggle('text-zinc-500', !active);
            button.classList.toggle('border-zinc-700', !active);
        });
    }

    async explain() {
        this.explainButtonTarget.disabled = true;
        this.resultTarget.textContent = 'Thinking…';
        this.resultTarget.classList.remove('hidden');

        let response;
        try {
            response = await fetch(this.urlValue, {
                method: 'POST',
                headers: {
                    'X-CSRF-Token': this.tokenValue,
                    'Content-Type': 'application/x-www-form-urlencoded',
                    Accept: 'application/json',
                },
                body: new URLSearchParams({ locale: this.locale }),
            });
        } catch (error) {
            this.resultTarget.textContent = "Sorry, we couldn't reach the explanation service. Please try again later.";
            this.explainButtonTarget.disabled = false;
            return;
        }

        const data = await response.json();

        if (!response.ok) {
            this.resultTarget.textContent = data.error || "Sorry, we couldn't generate an explanation right now.";
            this.explainButtonTarget.disabled = false;
            return;
        }

        this.resultTarget.textContent = data.explanation;
        this.explainButtonTarget.disabled = false;
    }
}
