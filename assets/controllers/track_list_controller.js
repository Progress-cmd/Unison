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
        const tracks = this.trackTargets.map(row => JSON.parse(row.dataset.track));

        // The position of the row in the list: rows come and go (favourite removed, more loaded).
        const index = this.trackTargets.indexOf(event.currentTarget.closest('[data-track-list-target="track"]'));

        this.dispatch('play', { detail: { tracks: tracks, index: index } });
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
