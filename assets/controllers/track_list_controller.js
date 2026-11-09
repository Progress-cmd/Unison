import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['track'];

    connect()
    {
        this.markCurrent(document.documentElement.dataset.currentUrl);
    }
    
    play(event)
    {
        const tracks = this.trackTargets.map(row => ({
            url: row.dataset.url,
            title: row.dataset.title,
            artists: row.dataset.artists,
            cover: row.dataset.cover,
            waveform: row.dataset.waveform,
        }));

        this.dispatch('play', { detail: { tracks: tracks, index: event.params.index } });
    }

    highlight(event)
    {
        this.markCurrent(event.detail.url);
    }

    markCurrent(url)
    {
        this.trackTargets.forEach(row => row.setAttribute('aria-current', row.dataset.url === url));
    }
}
