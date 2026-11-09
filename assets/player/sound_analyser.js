export default class SoundAnalyser
{
    constructor(audio, barCount)
    {
        this.audio = audio;
        this.barCount = barCount;
    }

    wake()
    {
        if (!this.context) {
            this.connect();
        }

        this.context.resume();
    }

    connect()
    {
        this.context = new AudioContext();

        const source = this.context.createMediaElementSource(this.audio);

        this.analyser = this.context.createAnalyser();
        this.analyser.fftSize = 1024;
        this.analyser.smoothingTimeConstant = 0.72;

        source.connect(this.analyser);
        this.analyser.connect(this.context.destination);

        this.spectrum = new Uint8Array(this.analyser.frequencyBinCount);

        this.bands = this.createBands();
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