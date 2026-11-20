import { Controller } from '@hotwired/stimulus';

// The "Options du titre" sheet (templates/shared/_track_options.html.twig), one for the whole page.
// Opened by the ⋮ button of a track: the button, or one of its parents, carries the track
// as JSON in data-track (same object as the player's queue items).
export default class extends Controller
{
    static targets = ['dialog', 'cover', 'title', 'artists', 'queueAction', 'playlist', 'links', 'linkTemplate'];

    open(event)
    {
        this.track = JSON.parse(event.currentTarget.closest('[data-track]').dataset.track);

        this.coverTarget.src = this.track.cover ?? '';
        this.coverTarget.hidden = !this.track.cover;
        this.titleTarget.textContent = this.track.title;
        this.artistsTarget.textContent = this.track.artists;

        // From the player, the track is the one playing: queuing it makes no sense.
        this.queueActionTargets.forEach(item => { item.hidden = Boolean(event.params.playing); });

        // "Ajouter à une playlist" opens the existing playlist sheet with this track.
        this.playlistTarget.dataset.playlistPickerUrlParam = this.track.playlistsUrl;
        this.playlistTarget.dataset.playlistPickerTitleParam = this.track.title;

        this.renderLinks();

        this.dialogTarget.showModal();

        // showModal() focuses the first action, drawn with a ring as if chosen: the sheet takes
        // the focus instead. Tab still goes to the first action.
        this.dialogTarget.focus();
    }

    close()
    {
        this.dialogTarget.close();
    }

    // A tap on the dark area around the sheet closes it, like "Fermer".
    closeOnBackdrop(event)
    {
        if (event.target === this.dialogTarget) {
            this.close();
        }
    }

    playNext()
    {
        this.dispatch('play-next', { detail: { track: this.track } });
        this.close();
    }

    addToQueue()
    {
        this.dispatch('add-to-queue', { detail: { track: this.track } });
        this.close();
    }

    // "Voir l'album" and one "Voir <artiste>" per artist.
    renderLinks()
    {
        const links = [];

        if (this.track.album) {
            links.push(this.createLink(this.track.album.url, `Voir l'album « ${this.track.album.title} »`, 'album'));
        }

        for (const artist of this.track.artistLinks ?? []) {
            links.push(this.createLink(artist.url, `Voir ${artist.name}`, 'artist'));
        }

        this.linksTarget.replaceChildren(...links);
        this.linksTarget.hidden = links.length === 0;
    }

    createLink(url, label, kind)
    {
        const item = this.linkTemplateTarget.content.cloneNode(true);
        const link = item.querySelector('a');

        link.href = url;
        link.querySelector('span').textContent = label;
        // An <svg> has no .hidden property: the attribute itself has to go.
        link.querySelector(`[data-icon="${kind}"]`).removeAttribute('hidden');

        return item;
    }
}
