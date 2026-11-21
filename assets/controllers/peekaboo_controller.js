import { Controller } from '@hotwired/stimulus';

// Now and then, the little character peeks out from behind the sidebar (templates/base.html.twig),
// looks around for a moment and goes back. Computer only (on a phone the bar is at the bottom,
// under the mini-player). Shy: the mouse coming near sends him back at once. Kept by Turbo from
// page to page, so the time between two visits goes on while browsing.
const FIRST = [60, 150];
const NEXT = [240, 540];
const LATER = [30, 90];
const HIDDEN = 'translateX(-100%)';
const OUT = 'translateX(-42%)';

const random = (min, max) => min + Math.random() * (max - min);

export default class extends Controller
{
    connect()
    {
        if (!this.timer) {
            this.schedule(FIRST);
        }
    }

    disconnect()
    {
        clearTimeout(this.timer);
        clearTimeout(this.backTimer);
        this.timer = null;
    }

    schedule([min, max])
    {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.peek(), random(min, max) * 1000);
    }

    peek()
    {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const canBeSeen = matchMedia('(min-width: 1024px)').matches && !document.hidden && !document.querySelector('dialog[open]');

        if (!canBeSeen) {
            this.schedule(LATER);

            return;
        }

        // Somewhere along the middle of the bar: away from the menu at the top and the player.
        const top = random(0.3, 0.62) * window.innerHeight;

        this.element.style.top = `${Math.min(top, window.innerHeight - 170)}px`;
        this.element.classList.add('is-out');
        this.out = true;
        this.move(HIDDEN, OUT, 650, 'cubic-bezier(0.2, 0.9, 0.3, 1.2)');

        this.backTimer = setTimeout(() => this.hide(), random(2400, 3600));
    }

    // Back behind the bar: slowly after a look around, at once when the mouse comes (shy).
    hide(event)
    {
        if (!this.out) {
            return;
        }

        this.out = false;
        clearTimeout(this.backTimer);
        this.move(OUT, HIDDEN, event ? 180 : 500, 'ease-in')
            .finished.catch(() => {})
            .finally(() => this.element.classList.remove('is-out'));

        this.schedule(NEXT);
    }

    move(from, to, duration, easing)
    {
        return this.element.animate([{ transform: from }, { transform: to }], { duration, easing, fill: 'forwards' });
    }
}
