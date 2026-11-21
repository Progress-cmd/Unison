import { Controller } from '@hotwired/stimulus';

// Secret words of the search bar (import page): sending one of them sets off a little surprise,
// and the search still runs (« Butterfly » is also a song). On sending only: the page keeps the
// last search, coming back to it later sets nothing off.
const WORDS = {
    butterfly: 'unison:butterflies',
    butterflies: 'unison:butterflies',
    papillon: 'unison:butterflies',
    papillons: 'unison:butterflies',
    neige: 'unison:snow',
    snow: 'unison:snow',
    'cœur': 'unison:hearts',
    'cœurs': 'unison:hearts',
    coeur: 'unison:hearts',
    coeurs: 'unison:hearts',
    amour: 'unison:hearts',
    love: 'unison:hearts',
    heart: 'unison:hearts',
    hearts: 'unison:hearts',
    luciole: 'unison:fireflies',
    lucioles: 'unison:fireflies',
    firefly: 'unison:fireflies',
    fireflies: 'unison:fireflies',
    vinyle: 'unison:vinyl',
    vinyles: 'unison:vinyl',
    vinyl: 'unison:vinyl',
};

export default class extends Controller
{
    static targets = ['input'];

    check()
    {
        const word = this.inputTarget.value.trim().toLowerCase();

        if (WORDS[word]) {
            window.dispatchEvent(new CustomEvent(WORDS[word]));
        }
    }
}
