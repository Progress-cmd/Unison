import { Controller } from '@hotwired/stimulus';

const WAVED_KEY = 'unison.buddyWaved';

// The little character (templates/shared/_buddy.html.twig): waves once per visit, on the first
// page where he can be seen, and again when the mouse passes over him.
export default class extends Controller
{
    connect()
    {
        // Hidden here (the sidebar one on a phone): the visible one does the hello.
        if (this.element.offsetParent === null || this.alreadyWaved()) {
            return;
        }

        this.remember();
        this.timer = setTimeout(() => this.wave(), 700);
    }

    disconnect()
    {
        clearTimeout(this.timer);
    }

    wave()
    {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches || this.element.classList.contains('is-waving')) {
            return;
        }

        this.element.classList.add('is-waving');
        this.element.addEventListener('animationend', () => this.element.classList.remove('is-waving'), { once: true });
    }

    alreadyWaved()
    {
        try {
            return sessionStorage.getItem(WAVED_KEY) !== null;
        } catch {
            return false;
        }
    }

    remember()
    {
        try {
            sessionStorage.setItem(WAVED_KEY, '1');
        } catch {
            // Storage blocked: he'll say hello on every page load, no harm done.
        }
    }
}
