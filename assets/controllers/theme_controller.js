import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    apply(event)
    {
        // The CSRF script also fires a "change" on the form's hidden token field: only the radios count.
        if (event.target.type !== 'radio') {
            return;
        }

        document.documentElement.dataset.theme = event.target.value;

        this.element.requestSubmit();
    }
}
