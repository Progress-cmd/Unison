import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['track'];

    // Long lists (50 rows, more while scrolling): the page that answers ?queue=<row>
    // with the whole list (App\Playback\Service\ListQueue).
    static values = { queueUrl: String };

    connect()
    {
        this.markCurrent(document.documentElement.dataset.currentUrl);
    }

    play(event)
    {
        const tracks = this.trackTargets.map(row => JSON.parse(row.dataset.track));

        // The position of the row in the list: rows come and go (favourite removed, more loaded).
        const index = this.trackTargets.indexOf(event.currentTarget.closest('[data-track-list-target="track"]'));

        // Plays at once with the rows on the page (a phone only lets sound start right after
        // the tap), then the whole list takes over when it arrives.
        this.dispatch('play', { detail: { tracks: tracks, index: index } });

        if (this.hasQueueUrlValue && this.element.querySelector('[data-infinite-scroll-target="more"]')) {
            this.loadWholeList(index);
        }
    }

    async loadWholeList(index)
    {
        const url = new URL(this.queueUrlValue, window.location.href);

        url.searchParams.set('queue', index);

        const response = await fetch(url).catch(() => null);

        // No network: the queue stays the rows on the page.
        if (!response?.ok) {
            return;
        }

        const { tracks, index: position } = await response.json();

        this.dispatch('queue', { detail: { tracks: tracks, index: position } });
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
