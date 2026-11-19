import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['dialog', 'frame', 'search', 'subtitle'];

    open(event)
    {
        this.frameTarget.src = event.params.url;

        this.subtitleTarget.textContent = event.params.title;

        this.dialogTarget.showModal();

        this.followKeyboard();
    }

    reset()
    {
        this.stopFollowingKeyboard();

        this.frameTarget.removeAttribute('src');
        this.frameTarget.innerHTML = '';
        this.searchTarget.value = '';
    }

    submit(event)
    {
        event.target.form.requestSubmit();
    }

    // On a phone, the keyboard covers the bottom of the screen without moving the page:
    // the sheet, stuck to the bottom, would hide under it. The visual viewport is the part
    // still visible above the keyboard: lift the sheet by the difference.
    followKeyboard()
    {
        const viewport = window.visualViewport;

        if (!viewport) {
            return;
        }

        this.onViewportChange = () => {
            const keyboard = Math.max(0, window.innerHeight - viewport.height - viewport.offsetTop);

            this.dialogTarget.style.setProperty('--keyboard', `${keyboard}px`);
            this.dialogTarget.style.setProperty('--visible', `${viewport.height}px`);
        };

        viewport.addEventListener('resize', this.onViewportChange);
        viewport.addEventListener('scroll', this.onViewportChange);

        this.onViewportChange();
    }

    stopFollowingKeyboard()
    {
        if (!this.onViewportChange) {
            return;
        }

        window.visualViewport.removeEventListener('resize', this.onViewportChange);
        window.visualViewport.removeEventListener('scroll', this.onViewportChange);
        this.onViewportChange = null;

        this.dialogTarget.style.removeProperty('--keyboard');
        this.dialogTarget.style.removeProperty('--visible');
    }

    filter()
    {
        const query = this.searchTarget.value.toLowerCase().trim();

        this.frameTarget.querySelectorAll('li[data-name]').forEach(item => {
            item.hidden = !item.dataset.name.includes(query);
        });
    }
}
