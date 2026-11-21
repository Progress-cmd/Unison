import { Controller } from '@hotwired/stimulus';

// Things falling from the top of the screen (templates/base.html.twig): snow (secret word, and now
// and then in December) and hearts (secret word). Called by a secret word, they fall above everything for a few seconds;
// December's snow stays behind the player and the navigation. Never clickable, nothing when the
// system asks for less motion; with the tab hidden, it waits to be seen.
const DECEMBER_FIRST = [30, 90];
const DECEMBER_NEXT = [180, 480];

const random = (min, max) => min + Math.random() * (max - min);

const KINDS = {
    snow: { count: 46, duration: [9000, 14000], delay: 4500, size: [4, 9], sway: 26, spin: 0 },
    hearts: { count: 38, duration: [6000, 9500], delay: 3000, size: [12, 24], sway: 34, spin: 22 },
};
const HEART_COLOURS = 3;

export default class extends Controller
{
    static targets = ['heart'];

    connect()
    {
        if (!this.timer && new Date().getMonth() === 11) {
            this.schedule(DECEMBER_FIRST);
        }
    }

    disconnect()
    {
        clearTimeout(this.timer);
        this.timer = null;
    }

    // December: a light snowfall now and then, behind the player.
    schedule([min, max])
    {
        this.timer = setTimeout(() => {
            this.fall('snow', { above: false, count: 18 });
            this.schedule(DECEMBER_NEXT);
        }, random(min, max) * 1000);
    }

    snow()
    {
        this.fall('snow');
    }

    hearts()
    {
        this.fall('hearts');
    }

    fall(kind, { above = true, count = KINDS[kind].count } = {})
    {
        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        if (document.hidden) {
            document.addEventListener('visibilitychange', () => this.fall(kind, { above, count }), { once: true });

            return;
        }

        const settings = KINDS[kind];

        if (above) {
            this.element.classList.add('is-above');
        }

        for (let i = 0; i < count; i++) {
            const drop = this.create(kind);
            const size = random(...settings.size);

            drop.style.left = `${random(0, 100)}%`;
            drop.style.width = drop.style.height = `${size}px`;
            drop.classList.toggle('is-above', above);
            this.element.append(drop);

            drop.animate(this.path(settings), {
                duration: random(...settings.duration),
                delay: random(0, settings.delay),
                easing: 'linear',
                fill: 'backwards',
            }).finished.catch(() => {}).finally(() => {
                drop.remove();

                if (!this.element.querySelector('.is-above')) {
                    this.element.classList.remove('is-above');
                }
            });
        }
    }

    create(kind)
    {
        if (kind === 'hearts') {
            const heart = this.heartTarget.content.firstElementChild.cloneNode(true);
            heart.classList.add(`drop--heart-${1 + Math.floor(Math.random() * HEART_COLOURS)}`);

            return heart;
        }

        const drop = document.createElement('span');
        drop.className = `drop drop--${kind}`;

        return drop;
    }

    // Down the whole height, swaying from side to side, fading out at the very end.
    path({ sway, spin })
    {
        const height = window.innerHeight;
        const phase = random(0, Math.PI * 2);
        const turns = random(1, 2.5);
        const drift = random(-40, 40);
        const steps = 12;

        return Array.from({ length: steps + 1 }, (_, i) => {
            const t = i / steps;
            const x = Math.sin(phase + t * Math.PI * 2 * turns) * sway + drift * t;
            const y = -30 + t * (height + 60);

            return {
                transform: `translate(${x}px, ${y}px) rotate(${spin * Math.sin(phase + t * Math.PI * 2)}deg)`,
                opacity: t > 0.85 ? (1 - t) / 0.15 : 1,
            };
        });
    }
}
