export interface PhotoDto {
  source: string
  id: string
  url: string
  caption: string | null
  taken_on: string | null
  /** Only uploaded photos carry a heart; null for a folder's pictures. */
  favorite: boolean | null
}

/** Which pictures a frame draws: the device's choice or a tile's. */
export interface PhotoQuery {
  from: string
  albums: number[]
}

/**
 * The photo frame.
 *
 * A whole batch is fetched and paged through on the device: a kiosk asking
 * every 30 seconds would make 2880 requests a day — and stand still at every
 * network gap. This way the frame keeps running as long as the browser still
 * has the pictures cached.
 *
 * Shuffling happens on the server. Only when the batch is through does a new
 * one come — so no picture repeats while there are still unseen ones. Every
 * frame on screen (the area, a dashboard tile) has its own `scope`: they
 * may draw from different albums.
 */
export function usePhotos(scope = 'frame') {
  const batch = useState<PhotoDto[]>(`photo-batch-${scope}`, () => [])
  const position = useState<number>(`photo-position-${scope}`, () => 0)
  const loaded = useState<boolean>(`photos-loaded-${scope}`, () => false)
  const query = useState<string>(`photo-query-${scope}`, () => '')

  // Width over height per url, learnt while preloading — the duo pairs portraits.
  const ratios = useState<Record<string, number>>('photo-ratios', () => ({}))

  const image = computed<PhotoDto | null>(() => batch.value[position.value] ?? null)

  /*
   * The next pictures are fetched ahead, so the crossfade blends into a
   * loaded picture instead of into an empty frame.
   */
  function preload(from: number, count = 2): void {
    if (!import.meta.client) {
      return
    }

    for (const upcoming of batch.value.slice(from, from + count)) {
      if (ratios.value[upcoming.url] !== undefined) {
        continue
      }

      const picture = new Image()
      picture.onload = () => {
        if (picture.naturalHeight > 0) {
          ratios.value = { ...ratios.value, [upcoming.url]: picture.naturalWidth / picture.naturalHeight }
        }
      }
      picture.src = upcoming.url
    }
  }

  async function load(chosen: PhotoQuery = { from: 'all', albums: [] }): Promise<void> {
    query.value = JSON.stringify(chosen)

    try {
      const data = await $fetch<{ photos: PhotoDto[] }>('/api/wall/photos', {
        params: { 'limit': 40, 'from': chosen.from, 'albums[]': chosen.albums },
        baseURL: '/',
        credentials: 'same-origin',
      })

      batch.value = data.photos
      position.value = 0
      preload(0, 4)
    }
    catch {
      // Without a network the old batch stays: a picture is better than an
      // error message on a wall where nobody can do anything about it.
    }
    finally {
      loaded.value = true
    }
  }

  /** Loads only when nothing is there yet or the choice changed. */
  async function ensure(chosen: PhotoQuery): Promise<void> {
    if (batch.value.length === 0 || query.value !== JSON.stringify(chosen)) {
      await load(chosen)
    }
  }

  /** Forward by `by` pictures; past the end a fresh batch comes. */
  async function next(by = 1): Promise<void> {
    const nextIndex = position.value + by

    if (batch.value.length === 0 || nextIndex >= batch.value.length) {
      await load(query.value === '' ? undefined : JSON.parse(query.value) as PhotoQuery)

      return
    }

    position.value = nextIndex
    preload(nextIndex + 1, 3)
  }

  /** Back within the batch; at its start nothing older is left. */
  function previous(by = 1): void {
    position.value = Math.max(0, position.value - by)
  }

  /** The heart changed on the server: every frame showing it follows. */
  function marked(url: string, favorite: boolean): void {
    batch.value = batch.value.map(photo => photo.url === url ? { ...photo, favorite } : photo)
  }

  return { image, batch, position, loaded, ratios, load, ensure, next, previous, preload, marked }
}
