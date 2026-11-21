export default class SoundAnalyser
{
    constructor(audio, barCount)
    {
        this.audio = audio;
        this.barCount = barCount;
    }

    wake()
    {
        if (!this.connected) {
            this.connect();
        }

        this.context?.resume();
    }

    connect()
    {
        this.connected = true;

        // On a phone, no analyser at all. Listening to a copy of the sound (captureStream) makes
        // Chrome hide the system notification and block the next track with the screen off;
        // a mere AudioContext next to the <audio> makes Firefox crackle in background.
        // The bars keep the track's waveform instead of the live spectrum.
        if (!matchMedia('(pointer: fine)').matches) {
            return;
        }

        const canCopy = Boolean(this.audio.captureStream);

        // 'playback': larger audio buffers, fewer crackles (Bluetooth) at the cost of latency we don't need.
        this.context = new AudioContext({ latencyHint: 'playback' });

        this.analyser = this.context.createAnalyser();
        this.analyser.fftSize = 1024;
        this.analyser.smoothingTimeConstant = 0.72;

        this.spectrum = new Uint8Array(this.analyser.frequencyBinCount);
        this.bands = this.createBands();

        if (canCopy) {
            // The <audio> keeps playing on its own; the analyser only listens to a copy of the sound.
            // A new track gets a new copy: listen again each time one starts.
            this.audio.addEventListener('playing', () => this.listen());
            this.listen();
        } else {
            // No copy possible (Firefox before 149, Safari): the old way, the sound goes through the
            // analyser to the speakers. The audio output can't be chosen then (audio_settings_controller.js).
            const source = this.context.createMediaElementSource(this.audio);

            source.connect(this.analyser);
            this.analyser.connect(this.context.destination);
        }
    }

    // Plugs the analyser into the copy of the track being played.
    listen()
    {
        const [track] = this.audio.captureStream().getAudioTracks();

        if (!track || track === this.track) {
            return;
        }

        this.source?.disconnect();
        this.source = this.context.createMediaStreamSource(new MediaStream([track]));
        this.source.connect(this.analyser);
        this.track = track;
    }


    createBands()
    {
        const first = 2;
        const last = Math.floor(this.spectrum.length * 0.42);

        return Array.from({ length: this.barCount + 1 }, (_, i) => Math.round(first * Math.pow(last / first, i / this.barCount)));
    }

    levels()
    {
        if (!this.analyser) {
            return [];
        }
        this.analyser.getByteFrequencyData(this.spectrum);

        return this.bands.slice(0, -1).map((start, i) => this.levelOf(start, this.bands[i + 1], i));
    }
    
    levelOf(start, end, i)
    {
        const peak = Math.max(...this.spectrum.subarray(start, Math.max(start + 1, end)));
        const gain = 0.95 + (i / this.barCount) * 0.55;

        return Math.min(1, Math.pow(peak / 255, 1.8) * gain);
    }
}