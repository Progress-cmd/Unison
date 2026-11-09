import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    connect()
    {
        this.timer = setTimeout(() => this.element.closest('turbo-frame').reload(), 5000);
    }

    disconnect()
    {
        clearTimeout(this.timer);
    }
}
