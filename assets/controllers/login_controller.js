import { Controller } from '@hotwired/stimulus';

// Login page: "Mot de passe oublié ?" takes along the address already typed in the form,
// so it doesn't have to be typed again (the forgotten-password page fills its field with it).
export default class extends Controller
{
    static targets = ['email'];

    forgot(event)
    {
        const email = this.emailTarget.value.trim();

        if (!email) {
            return;
        }

        const url = new URL(event.currentTarget.href);

        url.searchParams.set('email', email);
        event.currentTarget.href = url.toString();
    }
}
