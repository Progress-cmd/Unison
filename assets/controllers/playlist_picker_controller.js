import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['dialog', 'frame'];

    open(event)
    {
        this.frameTarget.src = event.params.url;

        this.dialogTarget.showModal();
    }

    reset()
    {
        this.frameTarget.removeAttribute('src');
        this.frameTarget.innerHTML = '';
    }

    submit(event)
    {
        event.target.form.requestSubmit();
    }
}
