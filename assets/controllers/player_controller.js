import { Controller } from '@hotwired/stimulus';
import SoundAnalyser from '../player/sound_analyser.js';
import TrackCache from '../player/track_cache.js';
import { reportError } from '../error_reporter.js';

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
        'tip',
        'tipBrowser',
        'queueToggle',
        'toast',
        'options',
        'volume',
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
    cache = new TrackCache();
    lastPrepare = 0;
    // When the <audio> fails (network lost): where it was, and how many reloads in a row.
    errorPosition = null;
    failures = 0;

    initialize()
    {
        this.restoreVolume();
        this.sound = new SoundAnalyser(this.audioTarget, 120);
        this.restoreState();

        if ('mediaSession' in navigator) {
            navigator.mediaSession.setActionHandler('play', () => this.resume());
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

        this.showBackgroundTip();
    }

    toggle()
    {
        if (!this.audioTarget.src) {
            return;
        }

        if (this.audioTarget.paused) {
            this.resume();
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

    // From the bar or from « Paramètres audio »: both sliders show it, and it's kept for next time.
    volumeChanged()
    {
        this.volumeTarget.value = this.audioTarget.volume;

        try {
            localStorage.setItem('unison.volume', String(this.audioTarget.volume));
        } catch {
            // Storage blocked: the volume lasts for this visit only.
        }
    }

    toggleExpanded()
    {
        this.setExpanded(!this.element.classList.contains('is-expanded'));
    }

    // Escape with a window open over the player (options, audio settings): that window closes,
    // the player stays open.
    collapse(event)
    {
        if (event?.type === 'keydown' && document.querySelector('dialog[open]')) {
            return;
        }

        this.setExpanded(false);
    }

    // Following a link from the detailed player (an artist in the options sheet…): the new
    // page must not stay hidden under it. No animation, Turbo is already changing the page.
    collapseOnVisit()
    {
        this.setExpanded(false, false);
    }

    // Phone, full screen: the queue takes the place of the cover, and back.
    toggleQueue()
    {
        this.setQueueOpen(!this.element.classList.contains('is-queue-open'));
    }

    // --- Swipe down to close the full-screen player (phone) ---

    swipeStart(event)
    {
        if (!this.element.classList.contains('is-expanded') || window.matchMedia('(min-width: 1024px)').matches) {
            return;
        }

        // The sliders follow the finger sideways; a scrolled queue scrolls back up first; a window
        // over the player (audio settings…) isn't the player.
        const queue = event.target.closest('.player-upcoming');

        if (event.target.closest('input[type="range"], dialog') || (queue && queue.scrollTop > 0)) {
            return;
        }

        const touch = event.touches[0];

        this.swipe = { x: touch.clientX, y: touch.clientY, time: Date.now(), distance: 0 };
        this.element.style.transition = 'none';
    }

    swipeMove(event)
    {
        if (!this.swipe) {
            return;
        }

        const touch = event.touches[0];
        const dx = touch.clientX - this.swipe.x;
        const dy = touch.clientY - this.swipe.y;

        // Mostly sideways at the start: not a swipe down, let it be.
        if (this.swipe.distance === 0 && Math.abs(dx) > Math.abs(dy)) {
            this.swipeCancel();
            return;
        }

        this.swipe.distance = Math.max(0, dy);
        this.element.style.transform = `translateY(${this.swipe.distance}px)`;
    }

    swipeEnd()
    {
        if (!this.swipe) {
            return;
        }

        const { distance, time } = this.swipe;
        const speed = distance / Math.max(1, Date.now() - time);

        this.swipe = null;

        // Far enough, or a quick flick: close. Otherwise the card slides back up.
        if (distance > 140 || (distance > 50 && speed > 0.6)) {
            this.setExpanded(false);
        } else {
            this.swipeCancel();
        }
    }

    swipeCancel()
    {
        this.swipe = null;
        this.element.style.transition = 'transform 0.2s ease';
        this.element.style.transform = '';
    }

    toggleRepeat()
    {
        const nextMode = { off: 'all', all: 'one', one: 'off' };

        this.setRepeat(nextMode[this.repeat]);

        this.prepareAhead();

        this.saveState();
    }

    toggleShuffle()
    {
        this.shuffle = !this.shuffle;

        this.shuffleTarget.setAttribute('aria-pressed', this.shuffle);

        this.arrangeQueue();

        this.renderUpcoming();

        this.prepareAhead();

        this.saveState();
    }

    jumpTo(event)
    {
        this.index = event.params.index;

        this.loadCurrent();
    }

    // A long list is only partly on the page: once fetched (track-list:queue), the whole
    // list, around the track clicked, replaces the queue made of the rows shown.
    // The track playing goes on, untouched.
    extendQueue(event)
    {
        const { tracks, index } = event.detail;
        const current = this.queue[this.index];

        // Another track was started meanwhile: this list is no longer the one playing.
        if (!current || tracks[index]?.id !== current.id) {
            return;
        }

        tracks[index] = current;
        this.sourceQueue = tracks;

        // Not shuffled: the same list. Shuffled: the current track first, the rest mixed.
        this.queue = tracks;
        this.index = index;
        this.arrangeQueue();

        this.renderUpcoming();

        this.cache.keep(this.aheadUrls());
        this.lastPrepare = Date.now() - 10000;

        this.saveState();
    }

    // --- Queue, filled from a track's options (track-options:play-next / add-to-queue) ---

    playNext(event)
    {
        const { track } = event.detail;

        this.insertTrack(track, true);
        this.notify(`« ${track.title} » sera joué juste après.`);
    }

    addToQueue(event)
    {
        const { track } = event.detail;

        this.insertTrack(track, false);
        this.notify(`« ${track.title} » ajouté à la file d'attente.`);
    }

    // Right after the current track, or at the end. Inserted in both orders, the one played
    // (queue) and the original one (sourceQueue), so turning shuffle on or off keeps it.
    insertTrack(track, afterCurrent)
    {
        // A copy: the same track can be queued twice, and the queue tells its rows apart by object.
        const item = { ...track };

        // Nothing loaded yet: the track becomes the queue. "Juste après" plays it at once.
        if (!this.queue.length) {
            this.queue = [item];
            this.sourceQueue = this.queue;
            this.index = 0;
            this.loadCurrent(afterCurrent);

            return;
        }

        const current = this.queue[this.index];
        const insert = (list, position) => [...list.slice(0, position), item, ...list.slice(position)];

        this.sourceQueue = insert(
            this.sourceQueue,
            afterCurrent ? this.sourceQueue.indexOf(current) + 1 : this.sourceQueue.length,
        );

        // Not shuffled, both orders are the same list.
        this.queue = this.shuffle
            ? insert(this.queue, afterCurrent ? this.index + 1 : this.queue.length)
            : this.sourceQueue;
        this.index = this.queue.indexOf(current);

        this.renderUpcoming();

        // The next track may have changed: free the old one, download the new one in about 10 s.
        this.cache.keep(this.aheadUrls());
        this.lastPrepare = Date.now() - 10000;

        this.saveState();
    }

    // The ✕ of a row of "À suivre". Only upcoming tracks have one: the index is after the current one.
    removeFromQueue(event)
    {
        const removed = this.queue[event.params.index];

        if (!removed || event.params.index <= this.index) {
            return;
        }

        const current = this.queue[this.index];
        const without = list => list.filter(track => track !== removed);

        this.sourceQueue = without(this.sourceQueue);
        this.queue = this.shuffle ? without(this.queue) : this.sourceQueue;
        this.index = this.queue.indexOf(current);

        this.renderUpcoming();

        this.cache.keep(this.aheadUrls());
        this.lastPrepare = Date.now() - 10000;

        this.saveState();
    }

    // A short message above the player, for actions that change nothing visible on the page.
    notify(message)
    {
        this.toastTarget.textContent = message;
        this.toastTarget.hidden = false;

        clearTimeout(this.toastTimer);
        this.toastTimer = setTimeout(() => { this.toastTarget.hidden = true; }, 3000);
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

        if ('mediaSession' in navigator) {
            navigator.mediaSession.playbackState = 'playing';
        }
    }

    showPaused()
    {
        this.element.classList.remove('is-playing');
        this.toggleTarget.setAttribute('aria-label', 'Lecture');

        this.stopAnimation();

        this.saveState();

        this.updatePositionState();

        // Between two tracks the <audio> pauses for a moment: the notification must stay.
        if ('mediaSession' in navigator && !this.audioTarget.ended) {
            navigator.mediaSession.playbackState = 'paused';
        }
    }

    updateDuration()
    {
        this.progressTarget.max = this.audioTarget.duration;
        this.durationTarget.textContent = this.formatTime(this.audioTarget.duration);

        this.updatePositionState();
    }

    updateProgress()
    {
        // Screen off or app in background: nobody sees the player, the phone keeps its strength for the sound.
        if (!document.hidden) {
            this.progressTarget.value = this.audioTarget.currentTime;
            this.elapsedTarget.textContent = this.formatTime(this.audioTarget.currentTime);

            const bars = Array.from(this.barsTarget.children);
            const played = bars.length * this.audioTarget.currentTime / this.audioTarget.duration;

            bars.forEach((bar, index) => bar.classList.toggle('is-played', index < played));
        }

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

        // Playing well again 5 s past the failure: the next failure starts a new count.
        if (this.errorPosition !== null && this.audioTarget.currentTime > this.errorPosition + 5) {
            this.errorPosition = null;
            this.failures = 0;
        }

        // Downloads the next track, then tries again now and then if the network was asleep.
        if (Date.now() - this.lastPrepare > 20000) {
            this.prepareAhead();
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

    // The <audio> failed, most often because the network was cut while it was fetching
    // the rest of the track. It never skips to the next track: it reloads this one where
    // it stopped, a few times with a growing delay, then waits for the play button.
    recover()
    {
        if (this.errorPosition === null) {
            this.errorPosition = this.audioTarget.currentTime;
        }

        this.failures++;

        clearTimeout(this.retryTimer);

        if (this.failures > 5) {
            this.showPaused();

            // Given up: worth knowing on the Erreurs page (which track, which browser error).
            const failed = this.queue[this.index];
            const mediaError = this.audioTarget.error;

            reportError(`Lecture abandonnée après 5 essais : « ${failed?.title} » (titre ${failed?.id})`, {
                stack: mediaError ? `MediaError ${mediaError.code} ${mediaError.message}` : null,
            });

            return;
        }

        const track = this.queue[this.index];

        this.retryTimer = setTimeout(() => {
            if (track === this.queue[this.index]) {
                this.reload();
            }
        }, Math.min(2000 * this.failures, 15000));
    }

    // --- Internal helpers ---

    // The first time a track is started on an Android browser other than Firefox, explains the
    // battery setting without which music stops with the screen off. Once per device.
    showBackgroundTip()
    {
        const agent = navigator.userAgent;

        if (!/Android/i.test(agent) || /Firefox/i.test(agent)) {
            return;
        }

        try {
            if (localStorage.getItem('unison.backgroundTip')) {
                return;
            }

            localStorage.setItem('unison.backgroundTip', 'seen');
        } catch {
            // No storage (private browsing…): show it once per visit at most.
            if (this.tipShown) {
                return;
            }
        }

        this.tipShown = true;

        const browsers = [['SamsungBrowser', 'Samsung Internet'], ['EdgA', 'Edge'], ['OPR', 'Opera'], ['Chrome', 'Chrome']];
        const name = browsers.find(([token]) => agent.includes(token))?.[1] ?? 'ton navigateur';

        this.tipBrowserTargets.forEach(target => { target.textContent = name; });

        this.tipTarget.showModal();
    }

    // Play, or after a failure, reload the track where it stopped.
    resume()
    {
        if (this.audioTarget.error) {
            this.failures = 0;
            this.reload();
            return;
        }

        this.audioTarget.play().catch(() => {});
    }

    reload()
    {
        const track = this.queue[this.index];
        const position = this.errorPosition ?? this.audioTarget.currentTime;

        this.audioTarget.src = this.sourceOf(track);

        this.audioTarget.addEventListener('loadedmetadata', () => {
            this.audioTarget.currentTime = position;
        }, { once: true });

        this.audioTarget.play().catch(() => {});
    }

    // The track after the current one, as next() would pick it; null at the end of the queue.
    upcomingTrack()
    {
        if (this.index < this.queue.length - 1) {
            return this.queue[this.index + 1];
        }

        return this.repeat === 'all' ? this.queue[0] : null;
    }

    // The local copy of a track when playing from memory, else its stream address.
    sourceOf(track)
    {
        return this.cache.get(track.url) ?? track.url;
    }

    // The tracks worth keeping in memory: the current one (if it came from there) and the next one.
    aheadUrls()
    {
        return [this.queue[this.index], this.upcomingTrack()].filter(Boolean).map(track => track.url);
    }

    // Downloads the next track in full, and frees the others. The current one isn't downloaded
    // a second time: the <audio> is already streaming it.
    prepareAhead()
    {
        this.lastPrepare = Date.now();

        this.cache.keep(this.aheadUrls());

        const next = this.upcomingTrack();

        if (next) {
            this.cache.prefetch(next.url);
        }
    }

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

        clearTimeout(this.retryTimer);
        this.errorPosition = null;
        this.failures = 0;

        document.documentElement.dataset.currentUrl = track.url;
        // From memory when downloaded ahead, else streamed (the first track starts at once).
        this.audioTarget.src = this.sourceOf(track);
        this.coverTarget.src = track.cover;
        this.titleTarget.textContent = track.title;
        this.artistsTarget.textContent = track.artists;

        this.renderWaveform(track.waveform);

        this.renderUpcoming();

        // Frees what is no longer needed at once, but waits about 10 s of listening before
        // downloading the next track: the start of this one keeps the network and the phone for itself,
        // and a page just opened (track loaded but not played) downloads nothing.
        this.cache.keep(this.aheadUrls());
        this.lastPrepare = Date.now() - 10000;

        this.dispatch('change', { detail: { url: track.url } });

        if (autoplay) {
            this.audioTarget.play().catch(() => {});

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

    setExpanded(expanded, animate = true)
    {
        // Escape calls collapse() even when the player is already small: nothing to animate.
        if (expanded === this.element.classList.contains('is-expanded')) {
            return;
        }

        const apply = () => {
            this.element.classList.toggle('is-expanded', expanded);
            this.expandTarget.setAttribute('aria-expanded', expanded);

            // Back to the cover next time, and no leftover of a swipe.
            this.setQueueOpen(false);
            this.element.style.transform = '';
            this.element.style.transition = '';
        };

        const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

        // The browser pictures the player before and after apply(), then animates
        // between the two (see "view-transition-name: player" in player.css).
        if (animate && document.startViewTransition && !reduceMotion) {
            document.startViewTransition(apply);
        } else {
            apply();
        }
    }

    setQueueOpen(open)
    {
        this.element.classList.toggle('is-queue-open', open);
        this.queueToggleTarget.setAttribute('aria-pressed', open);
        this.queueToggleTarget.setAttribute('aria-label', open ? 'Revenir à la pochette' : 'Afficher la file d\'attente');
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
        row.querySelectorAll('button').forEach(button => { button.dataset.playerIndexParam = index; });
        row.querySelector('.player-queue-remove').setAttribute('aria-label', `Retirer « ${track.title} » de la file d'attente`);

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
        }).catch(() => {});
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

        // Each saved entry takes its own object from the queue: a track queued twice stays two entries.
        const unused = [...this.queue];

        this.sourceQueue = state.sourceQueue.map(item => {
            const position = unused.findIndex(track => track.id === item.id);

            return position === -1 ? item : unused.splice(position, 1)[0];
        });
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
        // The ⋮ of the detailed player opens the options of this track.
        this.optionsTarget.dataset.track = JSON.stringify(track);

        const response = await fetch(`${this.favoriteUrlValue}?track=${track.id}`).catch(() => null);

        // Logged out meanwhile (the logout form also triggers this): the answer is the login page.
        if (!response?.ok || response.redirected) {
            return;
        }

        const { favorite } = await response.json().catch(() => ({}));

        if (favorite === undefined) {
            return;
        }

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

        // Title and artists at once, with the plain cover: in background, Firefox can take
        // a long time to decode the picture, and the notification would wait for it.
        this.setMetadata(track, track.cover);

        if (!track.cover) {
            return;
        }

        const square = await this.squareCover(track.cover);

        if (track === this.queue[this.index]) {
            this.setMetadata(track, square);
        }
    }

    setMetadata(track, cover)
    {
        navigator.mediaSession.metadata = new MediaMetadata({
            title: track.title,
            artist: track.artists,
            artwork: cover ? [{ src: cover }] : [],
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
        }).catch(() => {});
    }

    restoreVolume()
    {
        let saved = null;

        try {
            saved = parseFloat(localStorage.getItem('unison.volume'));
        } catch {
            return;
        }

        if (saved >= 0 && saved <= 1) {
            this.audioTarget.volume = saved;
        }
    }
}
