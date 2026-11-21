import { Controller } from '@hotwired/stimulus';

// Dashboard columns (templates/admin/_charts.html.twig): the value and the period of the column
// under the pointer, in a tooltip above the chart. The whole column is the target, gaps
// included, so a thin bar doesn't have to be aimed at. A tap shows it too (phone).
export default class extends Controller
{
    static targets = ['tooltip'];

    show(event)
    {
        const column = event.target.closest('[data-chart-label]');

        if (!column || column === this.active) {
            return;
        }

        this.active?.classList.remove('is-active');
        this.active = column;
        column.classList.add('is-active');

        const [value, label] = this.tooltipTarget.children;

        // textContent: the labels are data, never HTML.
        value.textContent = column.dataset.chartValue;
        label.textContent = column.dataset.chartLabel;
        this.tooltipTarget.hidden = false;

        // Centred on the column, but kept inside the chart at both ends.
        const plot = this.tooltipTarget.parentElement.getBoundingClientRect();
        const box = column.getBoundingClientRect();
        const width = this.tooltipTarget.offsetWidth;
        const left = box.left - plot.left + box.width / 2 - width / 2;

        this.tooltipTarget.style.left = `${Math.min(Math.max(left, 0), plot.width - width)}px`;
    }

    hide()
    {
        this.active?.classList.remove('is-active');
        this.active = null;
        this.tooltipTarget.hidden = true;
    }
}
