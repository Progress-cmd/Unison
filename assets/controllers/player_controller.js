import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['audio', 'title', 'toggle'];

    play(event)
    {
        this.audioTarget.src = event.detail.url;
        this.titleTarget.textContent = event.detail.title;

        this.audioTarget.play();
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
}