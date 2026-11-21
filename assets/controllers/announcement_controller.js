import { Controller } from '@hotwired/stimulus';

// The announcement window (templates/shared/_announcement.html.twig): opens as soon as it's on
// the page. "J'ai compris" sends the form (the server notes it as seen) and closes it at once.
export default class extends Controller
{
    static targets = ['form'];

    connect()
    {
        this.element.showModal();

        // showModal() focuses the button, drawn with a ring as if already chosen: the window
        // takes the focus instead. Tab still goes to the button.
        this.element.focus();
    }

    // Escape counts as "J'ai compris", as in the v1: closed without being noted, it would
    // come back on every page.
    dismiss(event)
    {
        event.preventDefault();
        this.formTarget.requestSubmit();
    }

    close()
    {
        this.element.close();
    }
}
