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

const random = (min, max) => min + Math.random() * (max - min);

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
        this.timer = setTimeout(() => this.fly(), random(min, max) * 1000);
    }

    fly()
    {
        if (this.reducedMotion()) {
            return;
        }

        const playerCoversScreen = document.querySelector('.player.is-expanded') && matchMedia('(max-width: 1023px)').matches;

        if (document.hidden || playerCoversScreen) {
            this.schedule(LATER);

            return;
        }

        this.launch({ band: [0.12, 0.6], duration: random(11000, 16000) });
        this.schedule(NEXT);
    }

    // A cloud of butterflies across the whole screen (100 hours of listening: player_controller.js).
    // Above everything for once, still unclickable; waits for the tab to be seen.
    swarm()
    {
        if (this.reducedMotion()) {
            return;
        }

        if (document.hidden) {
            document.addEventListener('visibilitychange', () => this.swarm(), { once: true });

            return;
        }

        this.element.classList.add('is-swarming');

        const leftToRight = Math.random() < 0.5;

        for (let i = 0; i < 34; i++) {
            const flight = this.launch({
                band: [0.05, 0.9],
                duration: random(6500, 9500),
                delay: random(0, 3200),
                leftToRight,
                inSwarm: true,
            });

            // Back behind the player once the last one of the cloud has gone.
            flight.finished.catch(() => {}).finally(() => {
                if (!this.element.querySelector('.butterfly.in-swarm')) {
                    this.element.classList.remove('is-swarming');
                }
            });
        }
    }

    // One butterfly, from one side to the other within a band of the screen's height.
    launch({ band, duration, delay = 0, leftToRight = Math.random() < 0.5, inSwarm = false })
    {
        const butterfly = this.templateTarget.content.firstElementChild.cloneNode(true);
        const flap = random(0.11, 0.18);

        butterfly.classList.add(`butterfly--${1 + Math.floor(Math.random() * COLOURS)}`);
        butterfly.classList.toggle('in-swarm', inSwarm);
        const size = random(0.8, 1.15);

        butterfly.style.setProperty('--flap', `${flap}s`);

        this.element.append(butterfly);

        const animation = butterfly.animate(this.path(leftToRight, band, duration, flap, size), {
            duration,
            delay,
            easing: 'linear',
            fill: 'backwards',
        });

        this.glideNowAndThen(butterfly, animation);

        // Removed first, so the swarm's own callbacks (added after) see it gone.
        animation.finished.catch(() => {}).finally(() => butterfly.remove());

        return animation;
    }

    // A wandering path: a few random points joined by a smooth curve, crossed at an uneven
    // speed, the body bobbing a little at each wingbeat and pointing where it goes.
    path(leftToRight, [top, bottom], duration, flap, size)
    {
        const width = window.innerWidth;
        const height = window.innerHeight;
        const anchors = [];
        const count = 7 + Math.floor(Math.random() * 3);
        let y = height * random(top, bottom);

        for (let i = 0; i < count; i++) {
            const t = i / (count - 1);

            anchors.push({ x: -50 + t * (width + 100), y });
            y = Math.min(height * bottom, Math.max(height * top, y + random(-110, 110)));
        }

        if (!leftToRight) {
            anchors.reverse().forEach(point => { point.x = width - point.x; });
        }

        // Uneven speed: each stretch gets its own share of the time.
        const shares = anchors.slice(1).map(() => random(0.6, 1.5));
        const total = shares.reduce((sum, share) => sum + share, 0);
        const beats = duration / 1000 / (flap * 2);
        const frames = [];
        const steps = 16;
        let elapsed = 0;

        for (let i = 0; i < anchors.length - 1; i++) {
            for (let step = 0; step < steps; step++) {
                const local = step / steps;
                const offset = (elapsed + shares[i] * local) / total;
                const point = this.catmullRom(anchors, i, local);
                const ahead = this.catmullRom(anchors, i, Math.min(1, local + 0.08));
                const heading = Math.atan2(ahead.y - point.y, ahead.x - point.x) * 180 / Math.PI;
                const bob = Math.sin(offset * beats * Math.PI * 2) * 3;

                frames.push({
                    offset,
                    transform: `translate(${point.x}px, ${point.y + bob}px) rotate(${heading + 90}deg) scale(${size})`,
                });
            }

            elapsed += shares[i];
        }

        const end = anchors.at(-1);
        frames.push({ offset: 1, transform: `translate(${end.x}px, ${end.y}px) rotate(${leftToRight ? 90 : -90}deg) scale(${size})` });

        return frames;
    }

    // The point at `t` (0 to 1) between anchors[i] and anchors[i + 1], on a curve through them all.
    catmullRom(points, i, t)
    {
        const p0 = points[Math.max(0, i - 1)];
        const p1 = points[i];
        const p2 = points[i + 1];
        const p3 = points[Math.min(points.length - 1, i + 2)];
        const t2 = t * t;
        const t3 = t2 * t;
        const at = (a, b, c, d) => 0.5 * ((2 * b) + (-a + c) * t + (2 * a - 5 * b + 4 * c - d) * t2 + (-a + 3 * b - 3 * c + d) * t3);

        return { x: at(p0.x, p1.x, p2.x, p3.x), y: at(p0.y, p1.y, p2.y, p3.y) };
    }

    // Now and then the wings stop, half open, for a short glide.
    glideNowAndThen(butterfly, animation)
    {
        const glide = () => {
            if (!butterfly.isConnected) {
                return;
            }

            butterfly.classList.add('is-gliding');
            setTimeout(() => {
                butterfly.classList.remove('is-gliding');
                setTimeout(glide, random(1200, 3200));
            }, random(250, 650));
        };

        setTimeout(glide, random(1500, 3000) + (animation.effect.getTiming().delay ?? 0));
    }

    reducedMotion()
    {
        return matchMedia('(prefers-reduced-motion: reduce)').matches;
    }
}
