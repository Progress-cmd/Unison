import { Controller } from '@hotwired/stimulus';

const KEY = 'unison.anniversary';

// The account's anniversary (templates/shared/home.html.twig): a cloud of butterflies, the first
// time the home page shows that day.
export default class extends Controller
{
    connect()
    {
        const today = new Date().toDateString();

        try {
            if (localStorage.getItem(KEY) === today) {
                return;
            }

            localStorage.setItem(KEY, today);
        } catch {
            // Storage blocked: the butterflies come at each visit of the home page that day.
        }

        this.timer = setTimeout(() => window.dispatchEvent(new CustomEvent('unison:butterflies')), 800);
    }

    disconnect()
    {
        clearTimeout(this.timer);
    }
}
