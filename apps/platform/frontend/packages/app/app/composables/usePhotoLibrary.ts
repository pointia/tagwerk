import { isOffline } from '../../../shared/app/utils/network'

export interface ManagedPhotoDto {
  id: number
  thumb: string
  url: string
  caption: string | null
  taken_on: string | null
  /** The household's day of the upload; stands in for a missing `taken_on`. */
  added_on: string | null
  favorite: boolean
  mine: boolean
  can_delete: boolean
  /** Only in the trash: whole days until the nightly run removes it. */
  days_left: number | null
}

export interface AlbumDto {
  id: number
  name: string
  count: number
  cover: string | null
}

/** What the grid shows: everything, the hearts, one album or the trash. */
export type LibraryView =
  | { kind: 'all' }
  | { kind: 'favorites' }
  | { kind: 'album', id: number }
  | { kind: 'trash' }

type Change = 'delete' | 'restore' | 'purge'

const request = { baseURL: '/', credentials: 'same-origin' } as const

/**
 * The uploaded photos, their albums and the trash, page by page.
 *
 * Deleting only moves into the trash (the wall stops showing it at once);
 * what may be changed is decided by the server and comes along per photo.
 */
export function usePhotoLibrary() {
  const { t } = useI18n()

  const photos = ref<ManagedPhotoDto[]>([])
  const nextPage = ref<number | null>(1)
  const loading = ref(false)
  const error = ref<string | null>(null)
  const albums = useState<AlbumDto[]>('photo-albums', () => [])

  let view: LibraryView = { kind: 'all' }

  /*
   * Bumped by every fresh load. A page that arrives for an older one —
   * the trash still loading while someone already went back — is dropped,
   * and the fresh load does not wait for it.
   */
  let generation = 0

  async function load(next: LibraryView): Promise<void> {
    view = next
    generation++
    photos.value = []
    nextPage.value = 1
    loading.value = false
    await more()
  }

  function address(): { url: string, params: Record<string, string | number> } {
    switch (view.kind) {
      case 'trash':
        return { url: '/api/photos/trash', params: {} }
      case 'favorites':
        return { url: '/api/photos', params: { favorites: 1 } }
      case 'album':
        return { url: '/api/photos', params: { album: view.id } }
      default:
        return { url: '/api/photos', params: {} }
    }
  }

  async function more(): Promise<void> {
    if (loading.value || nextPage.value === null) {
      return
    }

    loading.value = true
    const mine = generation
    const { url, params } = address()

    try {
      const data = await $fetch<{ photos: ManagedPhotoDto[], next_page: number | null }>(url, {
        params: { ...params, page: nextPage.value },
        ...request,
      })

      if (mine !== generation) {
        return
      }

      photos.value = [...photos.value, ...data.photos]
      nextPage.value = data.next_page
      error.value = null
    }
    catch {
      if (mine === generation) {
        error.value = t('photos.library.loadFailed')
      }
    }
    finally {
      if (mine === generation) {
        loading.value = false
      }
    }
  }

  /** Runs a write; on failure the error line says so and false comes back. */
  async function write(job: () => Promise<unknown>): Promise<boolean> {
    if (isOffline()) {
      error.value = t('network.offline')

      return false
    }

    try {
      await job()
      error.value = null

      return true
    }
    catch {
      error.value = t('common.failed')

      return false
    }
  }

  /** Changes the chosen photos, then shows the view afresh. */
  async function change(action: Change, ids: number[]): Promise<boolean> {
    const done = await write(() => $fetch<unknown>(`/api/photos/${action}`, { method: 'POST', body: { ids }, ...request }))

    if (done) {
      // Gone from this view at once; the reload fills up from the next page.
      photos.value = photos.value.filter(photo => !ids.includes(photo.id))
      await load(view)
    }

    return done
  }

  /** Heart on or off for each; the favourites view drops what lost its heart. */
  async function favorite(ids: number[], on: boolean): Promise<boolean> {
    const done = await write(() => Promise.all(ids.map(id => $fetch<unknown>(`/api/wall/photos/${id}/favorite`, {
      method: 'PUT',
      body: { favorite: on },
      ...request,
    }))))

    if (done) {
      photos.value = view.kind === 'favorites' && !on
        ? photos.value.filter(photo => !ids.includes(photo.id))
        : photos.value.map(photo => ids.includes(photo.id) ? { ...photo, favorite: on } : photo)
    }

    return done
  }

  async function caption(id: number, text: string): Promise<boolean> {
    let saved: string | null = null
    const done = await write(async () => {
      saved = (await $fetch<{ caption: string | null }>(`/api/photos/${id}`, { method: 'PATCH', body: { caption: text }, ...request })).caption
    })

    if (done) {
      photos.value = photos.value.map(photo => photo.id === id ? { ...photo, caption: saved } : photo)
    }

    return done
  }

  /** "An der Wand zeigen": every wall of the household, for a few minutes. */
  function cast(id: number): Promise<boolean> {
    return write(() => $fetch<unknown>(`/api/photos/${id}/show`, { method: 'POST', ...request }))
  }

  async function loadAlbums(): Promise<void> {
    try {
      albums.value = (await $fetch<{ albums: AlbumDto[] }>('/api/wall/photos/albums', request)).albums
    }
    catch {
      // The last list stays.
    }
  }

  /** A new album; with `ids` the chosen photos go straight into it. */
  async function createAlbum(name: string, ids: number[] = []): Promise<number | null> {
    let id: number | null = null

    await write(async () => {
      id = (await $fetch<{ id: number }>('/api/photos/albums', { method: 'POST', body: { name }, ...request })).id

      if (ids.length > 0) {
        await $fetch<unknown>(`/api/photos/albums/${id}/add`, { method: 'POST', body: { ids }, ...request })
      }
    })
    await loadAlbums()

    return id
  }

  async function renameAlbum(id: number, name: string): Promise<boolean> {
    const done = await write(() => $fetch<unknown>(`/api/photos/albums/${id}`, { method: 'PATCH', body: { name }, ...request }))
    await loadAlbums()

    return done
  }

  async function deleteAlbum(id: number): Promise<boolean> {
    const done = await write(() => $fetch<unknown>(`/api/photos/albums/${id}`, { method: 'DELETE', ...request }))
    await loadAlbums()

    return done
  }

  async function addToAlbum(id: number, ids: number[]): Promise<boolean> {
    const done = await write(() => $fetch<unknown>(`/api/photos/albums/${id}/add`, { method: 'POST', body: { ids }, ...request }))
    await loadAlbums()

    return done
  }

  /** Out of the album shown; the photos themselves stay. */
  async function removeFromAlbum(ids: number[]): Promise<boolean> {
    if (view.kind !== 'album') {
      return false
    }

    const album = view.id
    const done = await write(() => $fetch<unknown>(`/api/photos/albums/${album}/remove`, { method: 'POST', body: { ids }, ...request }))

    if (done) {
      photos.value = photos.value.filter(photo => !ids.includes(photo.id))
      await loadAlbums()
    }

    return done
  }

  return {
    photos, nextPage, loading, error, albums,
    load, more, change, favorite, caption, cast,
    loadAlbums, createAlbum, renameAlbum, deleteAlbum, addToAlbum, removeFromAlbum,
  }
}
