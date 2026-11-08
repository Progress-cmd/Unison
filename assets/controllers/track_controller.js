import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static values = { url: String, title: String };

    play()
    {
        this.dispatch('play', { detail: { url: this.urlValue, title: this.titleValue } });
    }
}