import { Controller } from '@hotwired/stimulus';

// Long lists arrive 50 rows at a time. Their last row (templates/shared/_more_rows.html.twig)
// carries the address of the next page: when it comes near the screen, the next rows are
// fetched and take its place, with a new last row if more remain.
export default class extends Controller
{
    static targets = ['more', 'status'];

    initialize()
    {
        // Starts loading 600 px before the end of the list: the rows are there before the
        // finger gets to them.
        this.observer = new IntersectionObserver(
            entries => entries.filter(entry => entry.isIntersecting).forEach(entry => this.load(entry.target)),
            { rootMargin: '600px 0px' },
        );
    }

    moreTargetConnected(more)
    {
        this.observer.observe(more);
    }

    moreTargetDisconnected(more)
    {
        this.observer.unobserve(more);
    }

    disconnect()
    {
        this.observer.disconnect();
    }

    async load(more)
    {
        if (more.dataset.loading) {
            return;
        }

        more.dataset.loading = 'true';

        try {
            // The header asks for the rows alone, without the page around them (ListPage.php).
            const response = await fetch(more.dataset.url, { headers: { 'X-Next-Rows': '1' } });

            if (!response.ok) {
                throw new Error(`HTTP ${response.status}`);
            }

            const range = document.createRange();

            range.selectNodeContents(this.element);
            more.replaceWith(range.createContextualFragment(await response.text()));
        } catch {
            // Network lost (phone in a tunnel…): the row offers to try again.
            delete more.dataset.loading;
            more.querySelector('[data-infinite-scroll-target="status"]').textContent = 'Impossible de charger la suite.';
            more.querySelector('button').hidden = false;
        }
    }

    retry(event)
    {
        const more = event.currentTarget.closest('[data-infinite-scroll-target="more"]');

        event.currentTarget.hidden = true;
        more.querySelector('[data-infinite-scroll-target="status"]').textContent = 'Chargement…';

        this.load(more);
    }
}
