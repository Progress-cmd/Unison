import { Controller } from '@hotwired/stimulus';

// Now and then, a butterfly crosses the screen (templates/base.html.twig). Rare on purpose: the
// first after one to three minutes, then one every four to ten. It flies behind the player and the
// navigation, can't be clicked, and stays home when the tab is hidden, when the player covers the
// screen (phone) or when the system asks for less motion. The element is kept by Turbo from page
// to page, so the timer goes on while browsing.
const FIRST = [60, 180];
const NEXT = [240, 600];
const LATER = [30, 90];
const COLOURS = 3;

export default class extends Controller
{
    static targets = ['template'];

    connect()
    {
        if (!this.timer) {
            this.schedule(FIRST);
        }
    }

    disconnect()
    {
        clearTimeout(this.timer);
        this.timer = null;
    }

    schedule([min, max])
    {
        this.timer = setTimeout(() => this.fly(), (min + Math.random() * (max - min)) * 1000);
    }

    fly()
    {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        const playerCoversScreen = document.querySelector('.player.is-expanded') && matchMedia('(max-width: 1023px)').matches;

        if (document.hidden || playerCoversScreen) {
            this.schedule(LATER);

            return;
        }

        const butterfly = this.templateTarget.content.firstElementChild.cloneNode(true);
        butterfly.classList.add(`butterfly--${1 + Math.floor(Math.random() * COLOURS)}`);

        // From one side to the other, somewhere in the upper half, fluttering up and down.
        const leftToRight = Math.random() < 0.5;
        const width = window.innerWidth;
        const height = window.innerHeight;
        const startY = height * (0.12 + Math.random() * 0.4);
        const endY = startY + (Math.random() - 0.5) * height * 0.3;
        const waves = 2 + Math.random() * 2;
        const steps = 24;

        butterfly.classList.toggle('is-flying-left', !leftToRight);

        const frames = Array.from({ length: steps + 1 }, (_, i) => {
            const t = i / steps;
            const x = leftToRight ? -40 + t * (width + 80) : width + 40 - t * (width + 80);
            const y = startY + (endY - startY) * t + Math.sin(t * Math.PI * 2 * waves) * 28;
            const tilt = Math.cos(t * Math.PI * 2 * waves) * 12;

            return { transform: `translate(${x}px, ${y}px) rotate(${tilt}deg)` };
        });

        this.element.append(butterfly);

        butterfly.animate(frames, { duration: 11000 + Math.random() * 5000, easing: 'linear' })
            .finished
            .catch(() => {})
            .finally(() => butterfly.remove());

        this.schedule(NEXT);
    }
}
