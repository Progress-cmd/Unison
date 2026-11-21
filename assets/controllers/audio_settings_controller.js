import { Controller } from '@hotwired/stimulus';

const OUTPUT_KEY = 'unison.audioOutput';
const DEFAULT_NAME = 'Sortie par défaut du système';

// « Paramètres audio » (templates/playback/_player.html.twig): the volume in percent, and the
// audio output (speakers, headphones…) on the computers whose browser lets a page choose it.
// On the same element as player_controller: the player stays from page to page (Turbo), so
// does the output chosen; it's also remembered for the next visit.
export default class extends Controller
{
    static targets = [
        'audio',
        'dialog',
        'volumeField',
        'volume',
        'volumeValue',
        'volumeNote',
        'outputName',
        'list',
        'pick',
        'reveal',
        'reset',
        'outputNote',
    ];

    // The output in use when it isn't the system's: {id, label}.
    current = null;

    connect()
    {
        this.restoreOutput();
    }

    open()
    {
        this.renderVolume();
        this.renderOutput();
        this.dialogTarget.showModal();
    }

    // --- Volume ---

    setVolume(event)
    {
        this.audioTarget.volume = event.target.value;
    }

    // The volume changed (here, the slider of the bar, or restored at the start).
    volumeChanged()
    {
        if (this.dialogTarget.open) {
            this.renderVolume();
        }
    }

    renderVolume()
    {
        const fixed = this.volumeIsFixed();

        this.volumeFieldTarget.hidden = fixed;
        this.volumeNoteTarget.hidden = !fixed;
        this.volumeTarget.value = this.audioTarget.volume;
        this.volumeValueTarget.textContent = `${Math.round(this.audioTarget.volume * 100)} %`;
    }

    // iPhone and iPad: a page can't change the volume (only the device's buttons do).
    volumeIsFixed()
    {
        const probe = document.createElement('audio');
        probe.volume = 0.5;

        return probe.volume !== 0.5;
    }

    // --- Output ---

    // How this browser lets the output be chosen:
    // 'system': phone or tablet, or no setSinkId(): the system sends the sound where it wants;
    // 'unsupported': the sound goes through the analyser (old browsers without captureStream,
    //     see sound_analyser.js), where choosing the <audio>'s output would change nothing;
    // 'picker': Firefox opens its own list, without asking anything else;
    // 'list': Chrome lists the outputs, by name once the microphone is allowed.
    get outputMode()
    {
        if (typeof this.audioTarget.setSinkId !== 'function' || !matchMedia('(pointer: fine)').matches) {
            return 'system';
        }

        if (!('captureStream' in HTMLMediaElement.prototype)) {
            return 'unsupported';
        }

        return navigator.mediaDevices?.selectAudioOutput ? 'picker' : 'list';
    }

    async renderOutput()
    {
        const mode = this.outputMode;

        this.outputNameTarget.textContent = this.current?.label || DEFAULT_NAME;
        this.listTarget.hidden = true;
        this.pickTarget.hidden = mode !== 'picker';
        this.revealTarget.hidden = true;
        this.resetTarget.hidden = !this.current;
        this.outputNoteTarget.textContent = {
            system: "Sur un téléphone ou une tablette, le son sort là où le système l'envoie : le casque ou l'enceinte Bluetooth se choisissent dans ses réglages.",
            unsupported: 'Ce navigateur ne permet pas de choisir la sortie depuis une page : celle du système est utilisée.',
            picker: '',
            list: '',
        }[mode];

        if (mode === 'list') {
            await this.fillList();
        }
    }

    // Chrome: the outputs, if their names are known. They are only once the microphone has been
    // allowed; until then, a button to ask for it.
    async fillList()
    {
        const outputs = (await navigator.mediaDevices.enumerateDevices())
            .filter(device => device.kind === 'audiooutput' && device.label);

        if (outputs.length === 0) {
            this.revealTarget.hidden = false;
            this.outputNoteTarget.textContent = "Chrome ne donne le nom des sorties qu'une fois le micro autorisé. Le micro n'est jamais écouté : l'autorisation sert seulement à lire la liste.";

            return;
        }

        // textContent: the names come from the system, never read as HTML.
        this.listTarget.replaceChildren(...outputs.map(device => {
            const option = document.createElement('option');
            option.value = device.deviceId;
            option.textContent = device.label;

            return option;
        }));
        this.listTarget.value = this.audioTarget.sinkId || 'default';
        this.listTarget.hidden = false;
        this.resetTarget.hidden = true;
        this.outputNoteTarget.textContent = '';
    }

    async reveal()
    {
        try {
            const stream = await navigator.mediaDevices.getUserMedia({ audio: true });
            stream.getTracks().forEach(track => track.stop());
        } catch {
            this.outputNoteTarget.textContent = 'Micro refusé : la sortie par défaut du système reste utilisée.';

            return;
        }

        await this.fillList();
    }

    // Firefox: its own list of outputs.
    async pick()
    {
        try {
            const device = await navigator.mediaDevices.selectAudioOutput();
            await this.useOutput(device.deviceId, device.label);
        } catch {
            // Closed without choosing: nothing changes.
        }
    }

    async choose(event)
    {
        const option = event.target.selectedOptions[0];

        // Chrome's « default » entry is the system's choice: nothing to remember.
        await this.useOutput(option.value === 'default' ? '' : option.value, option.textContent);
    }

    async resetOutput()
    {
        await this.useOutput('', null);
    }

    async useOutput(id, label)
    {
        try {
            await this.audioTarget.setSinkId(id);
        } catch {
            this.outputNoteTarget.textContent = "Cette sortie n'est plus disponible.";

            return;
        }

        this.current = id ? { id, label } : null;
        this.save(this.current);
        this.outputNameTarget.textContent = this.current?.label || DEFAULT_NAME;
        this.resetTarget.hidden = !this.current || this.outputMode === 'list';
        this.outputNoteTarget.textContent = '';
    }

    // The output chosen last time, if it's still there and the browser still allows it
    // (Firefox may want it chosen again): otherwise the system's, without a word.
    async restoreOutput()
    {
        const saved = this.saved();

        if (!saved || !['picker', 'list'].includes(this.outputMode)) {
            return;
        }

        try {
            await this.audioTarget.setSinkId(saved.id);
            this.current = saved;
        } catch {
            await this.restoreByName(saved);
        }
    }

    // The browser gives the outputs new ids now and then (Chrome, when its data is cleared):
    // the same output is then found by its name, if the names are known.
    async restoreByName(saved)
    {
        const devices = await navigator.mediaDevices.enumerateDevices().catch(() => []);
        const same = devices.find(device => device.kind === 'audiooutput' && device.label && device.label === saved.label);

        if (!same) {
            return;
        }

        try {
            await this.audioTarget.setSinkId(same.deviceId);
            this.current = { id: same.deviceId, label: same.label };
            this.save(this.current);
        } catch {
            // Still refused: the system's output stays.
        }
    }

    saved()
    {
        try {
            return JSON.parse(localStorage.getItem(OUTPUT_KEY));
        } catch {
            return null;
        }
    }

    save(output)
    {
        try {
            if (output) {
                localStorage.setItem(OUTPUT_KEY, JSON.stringify(output));
            } else {
                localStorage.removeItem(OUTPUT_KEY);
            }
        } catch {
            // Private browsing, storage blocked: the choice lasts for this visit only.
        }
    }
}
