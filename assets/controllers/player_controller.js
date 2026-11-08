import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['audio', 'title', 'toggle', 'elapsed', 'duration', 'progress'];
    queue = [];
    index = 0;

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

    formatTime(seconds)
    {
        const minutes = Math.floor(seconds / 60);
        const rest = Math.floor(seconds % 60);

        return `${minutes}:${String(rest).padStart(2, '0')}`;
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
    }

    seek()
    {
        this.audioTarget.currentTime = this.progressTarget.value;
    }

    loadCurrent()
    {
        const track = this.queue[this.index];

        this.audioTarget.src = track.url;
        this.titleTarget.textContent = track.title;

        this.audioTarget.play();
    }

    next()
    {
        if (this.index >= this.queue.length - 1) {
            return;
        }
        this.index++;
        
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
}