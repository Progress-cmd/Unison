import { Controller } from '@hotwired/stimulus';

export default class extends Controller
{
    static targets = ['input', 'list', 'row'];

    // url => null while loading, false if not found, otherwise the title.
    titles = new Map();

    connect()
    {
        this.render();
    }

    disconnect()
    {
        clearTimeout(this.timer);
    }

    // --- Actions, called from the HTML ---

    refresh()
    {
        clearTimeout(this.timer);
        this.timer = setTimeout(() => this.render(), 400);
    }

    // --- Internal helpers ---

    render()
    {
        const urls = this.urls();

        urls.filter(url => this.needsTitle(url)).forEach(url => this.load(url));

        this.listTarget.replaceChildren(...urls.map(url => this.createRow(url)));
    }

    urls()
    {
        const lines = this.inputTarget.value
            .split('\n')
            .map(line => line.trim())
            .filter(line => line);

        return [...new Set(lines)];
    }

    isYouTube(url)
    {
        return /^https:\/\/((www|music)\.)?(youtube\.com|youtu\.be)\//.test(url);
    }

    isPlaylist(url)
    {
        return url.includes('/playlist') || (url.includes('list=') && !url.includes('v='));
    }

    needsTitle(url)
    {
        return this.isYouTube(url) && !this.titles.has(url);
    }

    async load(url)
    {
        this.titles.set(url, null);
        this.titles.set(url, await this.fetchTitle(url));

        this.render();
    }

    async fetchTitle(url)
    {
        try {
            const response = await fetch(`https://www.youtube.com/oembed?format=json&url=${encodeURIComponent(url)}`);

            if (!response.ok) {
                return false;
            }

            const data = await response.json();

            return data.title;
        } catch {
            return false;
        }
    }

    describe(url)
    {
        if (!this.isYouTube(url)) {
            return ['invalid', 'Pas un lien YouTube'];
        }
        const title = this.titles.get(url);

        if (null === title) {
            return ['loading', url];
        }
        if (this.isPlaylist(url)) {
            return ['playlist', title || 'Playlist'];
        }
        if (false === title) {
            return ['unknown', 'Titre introuvable'];
        }
        return ['ok', title];
    }

    createRow(url)
    {
        const [state, text] = this.describe(url);
        const row = this.rowTarget.content.cloneNode(true);

        row.querySelector('li').dataset.state = state;
        row.querySelector('.import-url').textContent = text;

        return row;
    }
}
