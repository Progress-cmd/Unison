import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = [
        'audio',
        'cover',
        'title',
        'artists',
        'toggle',
        'elapsed',
        'duration',
        'progress',
        'bars',
        'expand',
        'repeat',
    ];

    queue = [];
    index = 0;
    repeat = 'off';

    // --- Actions, called from the HTML ---

    play(event)
    {
        this.queue = event.detail.tracks;
        this.index = event.detail.index;

        this.loadCurrent();
    }

    toggle()
    {
        if (!this.audioTarget.src) {
            return;
        }

        if (this.audioTarget.paused) {
            this.audioTarget.play();
        } else {
            this.audioTarget.pause();
        }
    }

    next()
    {
        if (!this.queue.length) {
            return;
        }

        if (this.index >= this.queue.length - 1 && this.repeat === 'off') {
            return;
        }

        this.index = (this.index + 1) % this.queue.length;
        this.loadCurrent();
    }

    previous()
    {
        if (this.audioTarget.currentTime > 3 || this.index === 0) {
            this.audioTarget.currentTime = 0;
            return;
        }

        this.index--;
        this.loadCurrent();
    }

    seek()
    {
        this.audioTarget.currentTime = this.progressTarget.value;
    }

    setVolume(event)
    {
        this.audioTarget.volume = event.target.value;
    }

    toggleExpanded()
    {
        this.setExpanded(!this.element.classList.contains('is-expanded'));
    }

    collapse()
    {
        this.setExpanded(false);
    }

    toggleRepeat()
    {
        const nextMode = { off: 'all', all: 'one', one: 'off' };

        this.setRepeat(nextMode[this.repeat]);
    }

    // --- Reactions to the <audio> element ---

    showPlaying()
    {
        this.element.classList.add('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Pause');
    }

    showPaused()
    {
        this.element.classList.remove('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Lecture');
    }

    updateDuration()
    {
        this.progressTarget.max = this.audioTarget.duration;
        this.durationTarget.textContent = this.formatTime(this.audioTarget.duration);
    }

    updateProgress()
    {
        this.progressTarget.value = this.audioTarget.currentTime;
        this.elapsedTarget.textContent = this.formatTime(this.audioTarget.currentTime);

        const bars = Array.from(this.barsTarget.children);
        const played = bars.length * this.audioTarget.currentTime / this.audioTarget.duration;

        bars.forEach((bar, index) => bar.classList.toggle('is-played', index < played));
    }

    trackEnded()
    {
        if (this.repeat === 'one') {
            this.setRepeat('off');

            this.audioTarget.currentTime = 0;
            this.audioTarget.play();

            return;
        }
        this.next();
    }

    // --- Internal helpers ---

    loadCurrent()
    {
        const track = this.queue[this.index];

        document.documentElement.dataset.currentUrl = track.url;
        this.audioTarget.src = track.url;
        this.coverTarget.src = track.cover;
        this.titleTarget.textContent = track.title;
        this.artistsTarget.textContent = track.artists;

        this.renderWaveform(track.waveform);

        this.dispatch('change', { detail: { url: track.url } });

        this.audioTarget.play();
    }

    renderWaveform(waveform)
    {
        const peaks = waveform
            ? Array.from(atob(waveform), char => char.charCodeAt(0))
            : new Array(120).fill(0);

        this.barsTarget.replaceChildren(...peaks.map(peak => this.createBar(peak)));
    }

    createBar(peak)
    {
        const bar = document.createElement('span');
        const level = Math.pow(peak / 255, 1.5);

        bar.style.height = `${Math.max(level * 100, 6)}%`;

        return bar;
    }

    formatTime(seconds)
    {
        const minutes = Math.floor(seconds / 60);
        const rest = Math.floor(seconds % 60);

        return `${minutes}:${String(rest).padStart(2, '0')}`;
    }

    setExpanded(expanded)
    {
        this.element.classList.toggle('is-expanded', expanded);
        this.expandTarget.setAttribute('aria-expanded', expanded);
    }

    setRepeat(mode)
    {
        const labels = { off: 'Répéter', all: 'Répéter tous les morceaux', one: 'Répéter ce morceau une fois' };

        this.repeat = mode;
        this.repeatTarget.dataset.mode = mode;

        this.repeatTarget.setAttribute('aria-pressed', mode !== 'off');
        this.repeatTarget.setAttribute('aria-label', labels[mode]);
    }
}
