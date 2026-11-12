import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['dialog', 'trackInput'];

    open(event)
    {
        const track = event.params.trackId;

        this.trackInputTargets.forEach(element => {
            element.value = track;
        });

        this.dialogTarget.showModal();
    }


}