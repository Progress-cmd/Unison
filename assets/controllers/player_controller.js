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
        favoriteUrl: String,
        listenUrl: String,
        listenToken: String,
    };

    queue = [];
    listenedTime = 0;
    lastTime = 0;
    listenSent = false;
    lastSave = 0;
    index = 0;
    repeat = 'off';
    shuffle = false;
    sourceQueue = [];

    initialize()
    {
        this.sound = new SoundAnalyser(this.audioTarget, 120);
        this.restoreState();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('play', () => this.audioTarget.play());
            navigator.mediaSession.setActionHandler('pause', () => this.audioTarget.pause());
            navigator.mediaSession.setActionHandler('previoustrack', () => this.previous());
            navigator.mediaSession.setActionHandler('nexttrack', () => this.next());

            navigator.mediaSession.setActionHandler('seekto', (details) => {
                this.audioTarget.currentTime = details.seekTime;
            });
        }
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

        this.updatePositionState();
    }

    showPaused()
    {
        this.element.classList.remove('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Lecture');

        this.stopAnimation();

        this.saveState();

        this.updatePositionState();
    }

    updateDuration()
    {
        this.progressTarget.max = this.audioTarget.duration;
        this.durationTarget.textContent = this.formatTime(this.audioTarget.duration);

        this.updatePositionState();
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

        const gap = this.audioTarget.currentTime - this.lastTime;

        if (gap > 0 && gap < 2) {
            this.listenedTime += gap;
        }

        this.lastTime = this.audioTarget.currentTime;

        const threshold = this.audioTarget.duration < 30 ? this.audioTarget.duration * 0.8 : 30;

        if (!this.listenSent && this.listenedTime >= threshold) {
            this.listenSent = true;
            this.recordListen();
        }
    }

    trackEnded()
    {
        if (this.repeat === 'one') {
            this.setRepeat('off');

            this.audioTarget.currentTime = 0;
            this.audioTarget.play();

            this.resetListen();

            return;
        }

        this.next();
    }

    // --- Internal helpers ---

    async loadCurrent(autoplay = true)
    {
        const track = this.queue[this.index];

        if (!this.audioTarget.paused) {
            this.audioTarget.pause();

            await new Promise(resolve => setTimeout(resolve, 250));

            if (track !== this.queue[this.index]) {
                return;
            }
        }

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

        this.updateMediaSession(track);

        this.resetListen();
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
        this.addTarget.dataset.playlistPickerTitleParam = track.title;

        const response = await fetch(`${this.favoriteUrlValue}?track=${track.id}`);

        if (!response.ok) {
            return;
        }

        const { favorite } = await response.json();


        this.favoriteTarget.setAttribute('aria-pressed', favorite);
        this.favoriteTarget.setAttribute('aria-label', favorite ? 'Retirer des favoris' : 'Ajouter aux favoris');
    }

    shortcut(event)
    {
        if (event.ctrlKey || event.metaKey || event.altKey) {
            return;
        }

        if (event.target.closest('input, textarea, select')) {
            return;
        }

        if (this.queue.length === 0) {
            return;
        }

        if (document.querySelector('dialog[open]')) {
            return;
        }

        switch (event.key) {
            case ' ':
                this.toggle();
                break;

            case 'ArrowLeft':
                if (event.shiftKey) {
                    this.previous();
                } else {
                    this.seekBy(-10);
                }
                break;

            case 'ArrowRight':
                if (event.shiftKey) {
                    this.next();
                } else {
                    this.seekBy(10);
                }
                break;

            case 'f':
            case 'F':
                this.favoriteTarget.click();
                break;

            case 'e':
            case 'E':
                this.toggleExpanded();
                break;

            default:
                return;
        }

        event.preventDefault();
    }

    seekBy(seconds)
    {
        if (!this.audioTarget.duration) {
            return;
        }

        this.audioTarget.currentTime = Math.min(Math.max(this.audioTarget.currentTime + seconds, 0), this.audioTarget.duration);
    }

    async updateMediaSession(track)
    {
        if (!('mediaSession' in navigator)) {
            return;
        }

        const artwork = track.cover ? [{ src: await this.squareCover(track.cover) }] : [];

        if (track !== this.queue[this.index]) {
            return;
        }

        navigator.mediaSession.metadata = new MediaMetadata({
            title: track.title,
            artist: track.artists,
            artwork: artwork,
        });
    }

    async squareCover(url)
    {
        const image = new Image();
        image.crossOrigin = 'anonymous';
        image.src = url;

        try {
            await image.decode();

            const size = Math.min(image.naturalWidth, image.naturalHeight);
            const canvas = document.createElement('canvas');
            canvas.width = size;
            canvas.height = size;

            canvas.getContext('2d').drawImage(
                image,
                (image.naturalWidth - size) / 2, (image.naturalHeight - size) / 2, size, size,
                0, 0, size, size,
            );

            return canvas.toDataURL('image/jpeg', 0.9);
        } catch {
            return url;
        }
    }

    updatePositionState()
    {
        if (!('mediaSession' in navigator) || !Number.isFinite(this.audioTarget.duration)) {
            return;
        }

        navigator.mediaSession.setPositionState({
            duration: this.audioTarget.duration,
            position: this.audioTarget.currentTime,
            playbackRate: this.audioTarget.playbackRate,
        });
    }

    resetListen()
    {
        this.listenedTime = 0;
        this.lastTime = 0;
        this.listenSent = false;
    }

    recordListen()
    {
        fetch(this.listenUrlValue, {
            method: 'POST',
            body: JSON.stringify({'track': this.queue[this.index].id, '_token': this.listenTokenValue}),
            keepalive: true,
        });
    }
}
