import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['dialog', 'frame', 'search', 'subtitle'];

    open(event)
    {
        this.frameTarget.src = event.params.url;

        this.subtitleTarget.textContent = event.params.title;

        this.dialogTarget.showModal();
    }

    reset()
    {
        this.frameTarget.removeAttribute('src');
        this.frameTarget.innerHTML = '';
        this.searchTarget.value = '';
    }

    submit(event)
    {
        event.target.form.requestSubmit();
    }

    filter()
    {
        const query = this.searchTarget.value.toLowerCase().trim();

        this.frameTarget.querySelectorAll('li[data-name]').forEach(item => {
            item.hidden = !item.dataset.name.includes(query);
        });
    }
}
