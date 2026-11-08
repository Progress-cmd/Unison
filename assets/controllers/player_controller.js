import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['audio', 'title'];

    play(event)
    {
        this.audioTarget.src = event.detail.url;
        this.titleTarget.textContent = event.detail.title;

        this.audioTarget.play();
    }
}