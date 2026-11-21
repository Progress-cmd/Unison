import { Controller } from '@hotwired/stimulus';

// Fireflies (templates/base.html.twig): little lights that drift and blink in place for a few
// seconds. A secret word calls a crowd of them, above everything; at night (1 to 5 a.m.) a few of
// them come instead of the butterfly (butterflies_controller.js), behind the player. Never
// clickable, nothing when the system asks for less motion; with the tab hidden, they wait.
const random = (min, max) => min + Math.random() * (max - min);

export default class extends Controller
{
    glow(event)
    {
        const night = Boolean(event?.detail?.night);

        if (matchMedia('(prefers-reduced-motion: reduce)').matches) {
            return;
        }

        if (document.hidden) {
            document.addEventListener('visibilitychange', () => this.glow(event), { once: true });

            return;
        }

        if (!night) {
            this.element.classList.add('is-above');
        }

        const count = night ? 8 : 24;

        for (let i = 0; i < count; i++) {
            const firefly = document.createElement('span');

            firefly.className = 'firefly';
            firefly.classList.toggle('is-above', !night);
            // Each one blinks at its own pace.
            firefly.style.setProperty('--blink', `${random(1.2, 2.4).toFixed(2)}s`);
            firefly.style.setProperty('--blink-delay', `${random(0, 1.5).toFixed(2)}s`);
            this.element.append(firefly);

            firefly.animate(this.path(), {
                duration: random(9000, 14000),
                delay: random(0, 2500),
                easing: 'ease-in-out',
                fill: 'backwards',
            }).finished.catch(() => {}).finally(() => {
                firefly.remove();

                if (!this.element.querySelector('.is-above')) {
                    this.element.classList.remove('is-above');
                }
            });
        }
    }

    // A slow wander around a spot of the screen, lighting up at the start, fading at the end.
    path()
    {
        let x = random(0.08, 0.92) * window.innerWidth;
        let y = random(0.12, 0.8) * window.innerHeight;
        const steps = 6;

        return Array.from({ length: steps + 1 }, (_, i) => {
            const frame = { transform: `translate(${x}px, ${y}px)`, opacity: i === 0 || i === steps ? 0 : 1 };

            x += random(-45, 45);
            y += random(-30, 30);

            return frame;
        });
    }
}
