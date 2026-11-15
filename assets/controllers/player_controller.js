import { Controller } from '@hotwired/stimulus';
import SoundAnalyser from '../player/sound_analyser.js';

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
        'shuffle',
        'upcoming',
        'upcomingRow',
        'favorite',
        'favoriteTrack',
        'add',
    ];

    static values = {
        saveUrl: String,
        saveToken: String,
        loadUrl: String,
        randomUrl: String,
        favoriteUrl: String
    };

    queue = [];
    lastSave = 0;
    index = 0;
    repeat = 'off';
    shuffle = false;
    sourceQueue = [];

    initialize()
    {
        this.sound = new SoundAnalyser(this.audioTarget, 120);
        this.restoreState();
    }

    // --- Actions, called from the HTML ---

    play(event)
    {
        this.sourceQueue = event.detail.tracks;
        this.queue = event.detail.tracks;
        this.index = event.detail.index;

        this.arrangeQueue();

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

        this.saveState();
    }

    toggleShuffle()
    {
        this.shuffle = !this.shuffle;

        this.shuffleTarget.setAttribute('aria-pressed', this.shuffle);

        this.arrangeQueue();

        this.renderUpcoming();

        this.saveState();
    }

    jumpTo(event)
    {
        this.index = event.params.index;

        this.loadCurrent();
    }

    wakeSound()
    {
        this.sound.wake();
    }

    // --- Reactions to the <audio> element ---

    showPlaying()
    {
        this.element.classList.add('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Pause');

        this.startAnimation();
    }

    showPaused()
    {
        this.element.classList.remove('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Lecture');

        this.stopAnimation();

        this.saveState();
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

        if (!this.audioTarget.paused && Date.now() - this.lastSave > 15000) {
            this.saveState();
        }
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

    loadCurrent(autoplay = true)
    {
        const track = this.queue[this.index];

        document.documentElement.dataset.currentUrl = track.url;
        this.audioTarget.src = track.url;
        this.coverTarget.src = track.cover;
        this.titleTarget.textContent = track.title;
        this.artistsTarget.textContent = track.artists;

        this.renderWaveform(track.waveform);

        this.renderUpcoming();

        this.dispatch('change', { detail: { url: track.url } });

        if (autoplay) {
            this.audioTarget.play();

            this.saveState();
        }

        this.refreshFavorite();
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
        const height = `${Math.max(level * 100, 6)}%`;

        bar.style.height = height;
        bar.dataset.height = height;

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
        // Escape calls collapse() even when the player is already small: nothing to animate.
        if (expanded === this.element.classList.contains('is-expanded')) {
            return;
        }

        const apply = () => {
            this.element.classList.toggle('is-expanded', expanded);
            this.expandTarget.setAttribute('aria-expanded', expanded);
        };

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // The browser pictures the player before and after apply(), then animates
        // between the two (see "view-transition-name: player" in player.css).
        if (document.startViewTransition && !reduceMotion) {
            document.startViewTransition(apply);
        } else {
            apply();
        }
    }

    setRepeat(mode)
    {
        const labels = { off: 'Répéter', all: 'Répéter tous les morceaux', one: 'Répéter ce morceau une fois' };

        this.repeat = mode;
        this.repeatTarget.dataset.mode = mode;

        this.repeatTarget.setAttribute('aria-pressed', mode !== 'off');
        this.repeatTarget.setAttribute('aria-label', labels[mode]);
    }

    arrangeQueue()
    {
        if (!this.queue.length) {
            return;
        }
        const current = this.queue[this.index];

        if (this.shuffle) {
            const others = this.sourceQueue.filter(track => track !== current);

            this.queue = [current, ...this.shuffled(others)];
            this.index = 0;
        } else {
            this.queue = this.sourceQueue;
            this.index = this.sourceQueue.indexOf(current);
        }
    }

    shuffled(tracks)
    {
        const result = [...tracks];

        for (let i = result.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [result[i], result[j]] = [result[j], result[i]];
        }
        return result;
    }

    renderUpcoming()
    {
        const first = this.index + 1;
        const rows = this.queue.slice(first).map((track, offset) => this.createUpcomingRow(track, first + offset));

        this.upcomingTarget.replaceChildren(...rows);
    }

    createUpcomingRow(track, index)
    {
        const row = this.upcomingRowTarget.content.cloneNode(true);

        row.querySelector('.track-title').textContent = track.title;
        row.querySelector('.track-artists').textContent = track.artists;
        row.querySelector('button').dataset.playerIndexParam = index;

        return row;
    }

    startAnimation()
    {
        cancelAnimationFrame(this.frame);
        this.frame = requestAnimationFrame(() => this.drawLevels());
    }

    drawLevels()
    {
        const bars = this.barsTarget.children;

        this.sound.levels().forEach((level, index) => {
            bars[index].style.height = `${Math.max(level * 100, 6)}%`;
        });
        this.startAnimation();
    }

    stopAnimation()
    {
        cancelAnimationFrame(this.frame);

        Array.from(this.barsTarget.children).forEach(bar => { bar.style.height = bar.dataset.height; });
    }

    saveState()
    {
        if (!this.queue.length) {
            return;
        }

        this.lastSave = Date.now();

        const state = {
            queue: this.queue.map(track => track.id),
            sourceQueue: this.sourceQueue.map(track => track.id),
            currentIndex: this.index,
            position: Math.floor(this.audioTarget.currentTime),
            shuffle: this.shuffle,
            repeatMode: this.repeat,
            _token: this.saveTokenValue,
        };

        fetch(this.saveUrlValue, {
            method: 'POST',
            body: JSON.stringify(state),
            keepalive: true,
        });
    }

    async restoreState()
    {
        const response = await fetch(this.loadUrlValue);

        if (response.status === 204) {
            this.loadRandom();
            return;
        }

        if (!response.ok) {
            return;
        }

        const state = await response.json();

        this.queue = state.queue;
        this.sourceQueue = state.sourceQueue.map(item => this.queue.find(track => track.id === item.id) ?? item);
        this.index = state.currentIndex;

        this.shuffle = state.shuffle;
        this.shuffleTarget.setAttribute('aria-pressed', this.shuffle);
        this.setRepeat(state.repeatMode);

        this.audioTarget.addEventListener('loadedmetadata', () => {
            this.audioTarget.currentTime = state.position;
        }, { once: true });

        this.loadCurrent(false);
    }

    async loadRandom()
    {
        const response = await fetch(this.randomUrlValue);

        if (response.status !== 200) {
            return;
        }

        const tracks = await response.json();

        this.queue = tracks;
        this.sourceQueue = tracks;
        this.index = 0;

        this.loadCurrent(false);
    }

    async refreshFavorite()
    {
        if (this.queue.length === 0) {
            return;
        }

        const track = this.queue[this.index];

        this.favoriteTrackTarget.value = track.id;
        this.addTarget.dataset.playlistPickerUrlParam = track.playlistsUrl;

        const response = await fetch(`${this.favoriteUrlValue}?track=${track.id}`);

        if (!response.ok) {
            return;
        }

        const { favorite } = await response.json();


        this.favoriteTarget.setAttribute('aria-pressed', favorite);
        this.favoriteTarget.setAttribute('aria-label', favorite ? 'Retirer des favoris' : 'Ajouter aux favoris ')
    }
}
