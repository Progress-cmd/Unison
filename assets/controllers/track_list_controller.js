import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['track'];

    play(event)
    {
        const tracks = this.trackTargets.map(row => ({ url: row.dataset.url, title: row.dataset.title, waveform: row.dataset.waveform, cover: row.dataset.cover, artists: row.dataset.artists }));
        this.dispatch('play', { detail: { tracks: tracks, index: event.params.index } });
    }
}