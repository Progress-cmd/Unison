import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    async play(event)
    {
        const response = await fetch(event.params.url);
        if (!response.ok) {
            return;
        }

        const tracks = await response.json();

        this.dispatch('play', {detail: { tracks: tracks, index: 0}});
    }
}