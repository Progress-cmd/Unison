// Downloads whole tracks ahead of time, so they play from the phone's memory.
// With the screen off, Android cuts the network of background apps: a track streamed
// piece by piece then fails half-way. A track already in memory doesn't need the network.
export default class TrackCache
{
    // url → { controller, objectUrl }: objectUrl stays null until the download is complete.
    entries = new Map();

    // The local copy of url, or null if it isn't completely downloaded yet.
    get(url)
    {
        return this.entries.get(url)?.objectUrl ?? null;
    }

    // Starts downloading url, unless it is already downloaded or on its way.
    // A failed download is forgotten, so the next call tries again.
    prefetch(url)
    {
        if (this.entries.has(url)) {
            return;
        }

        const entry = { controller: new AbortController(), objectUrl: null };

        this.entries.set(url, entry);

        fetch(url, { signal: entry.controller.signal })
            .then(response => {
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }

                return response.blob();
            })
            .then(blob => {
                entry.objectUrl = URL.createObjectURL(blob);
            })
            .catch(() => {
                if (this.entries.get(url) === entry) {
                    this.entries.delete(url);
                }
            });
    }

    // Keeps only these urls (the current track and the next one): the others are
    // stopped if still downloading, and their memory is given back.
    keep(urls)
    {
        for (const [url, entry] of this.entries) {
            if (urls.includes(url)) {
                continue;
            }

            entry.controller.abort();

            if (entry.objectUrl) {
                URL.revokeObjectURL(entry.objectUrl);
            }

            this.entries.delete(url);
        }
    }
}
