import { Controller } from '@hotwired/stimulus';

// A few quick clicks on an element (« Unison » in the sidebar) set off a surprise: the event
// named in data-secret-clicks-event-value, on window. Quick: each click within a second of the
// one before.
const CLICKS = 5;

export default class extends Controller
{
    static values = { event: String };

    count()
    {
        const now = Date.now();

        this.clicks = now - (this.last ?? 0) < 1000 ? (this.clicks ?? 0) + 1 : 1;
        this.last = now;

        if (this.clicks === CLICKS) {
            this.clicks = 0;
            window.dispatchEvent(new CustomEvent(this.eventValue));
        }
    }
}
