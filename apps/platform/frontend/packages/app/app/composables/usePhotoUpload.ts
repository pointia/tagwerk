import { EDGE, EXIF_BYTES, exifDate, fitWithin, hex, isPicture, nameDate } from '~/utils/photoPrep'

export type UploadState = 'waiting' | 'uploading' | 'done' | 'known' | 'failed'

export interface UploadItem {
  key: number
  name: string
  state: UploadState
  message: string | null
}

/** Originals asked about per request: one question instead of fifty uploads. */
const CHUNK = 50

/*
 * Outside the component: switching tabs must not stop a folder half way.
 * The files themselves stay out of useState — they are handles, not data.
 */
const files = new Map<number, File>()
let nextKey = 1
let lock: WakeLockSentinel | null = null

export function usePhotoUpload() {
  const { t } = useI18n()

  const items = useState<UploadItem[]>('photo-upload', () => [])
  const running = useState('photo-upload-running', () => false)

  const count = (state: UploadState): number => items.value.filter(item => item.state === state).length

  const summary = computed(() => ({
    total: items.value.length,
    done: count('done'),
    known: count('known'),
    failed: count('failed'),
    open: count('waiting') + count('uploading'),
  }))

  function set(key: number, state: UploadState, message: string | null = null): void {
    items.value = items.value.map(item => item.key === key ? { ...item, state, message } : item)
  }

  /** Takes a selection — single pictures or a whole folder — and starts. */
  function add(selection: FileList | File[]): number {
    const pictures = [...selection].filter(isPicture)

    for (const file of pictures) {
      const key = nextKey++
      files.set(key, file)
      items.value = [...items.value, { key, name: file.webkitRelativePath || file.name, state: 'waiting', message: null }]
    }

    void run()

    return pictures.length
  }

  function retry(): void {
    items.value = items.value.map(item => item.state === 'failed' ? { ...item, state: 'waiting', message: null } : item)
    void run()
  }

  /** Clears what is finished; failed ones stay until retried or cleared. */
  function clear(): void {
    for (const item of items.value) {
      files.delete(item.key)
    }

    items.value = []
  }

  async function run(): Promise<void> {
    if (running.value) {
      return
    }

    running.value = true
    await keepAwake()

    try {
      let batch = waiting()

      while (batch.length > 0) {
        const prints = new Map<number, string>()

        for (const item of batch) {
          prints.set(item.key, await fingerprint(files.get(item.key)!))
        }

        const known = await ask([...prints.values()])

        for (const item of batch) {
          if (known.has(prints.get(item.key)!)) {
            set(item.key, 'known')
            files.delete(item.key)
            continue
          }

          await send(item.key, prints.get(item.key)!)
        }

        batch = waiting()
      }
    }
    catch {
      // Signed out or the server gone: what is left waits for another try.
      items.value = items.value.map(item => item.state === 'waiting' || item.state === 'uploading'
        ? { ...item, state: 'failed', message: t('photos.noConnection') }
        : item)
    }
    finally {
      running.value = false
      await lock?.release().catch(() => undefined)
      lock = null
    }
  }

  function waiting(): UploadItem[] {
    return items.value.filter(item => item.state === 'waiting').slice(0, CHUNK)
  }

  async function ask(fingerprints: string[]): Promise<Set<string>> {
    const answer = await $fetch<{ known: string[] }>('/api/photos/known', {
      method: 'POST',
      body: { fingerprints },
      baseURL: '/',
      credentials: 'same-origin',
    })

    return new Set(answer.known)
  }

  async function send(key: number, print: string): Promise<void> {
    const file = files.get(key)!
    set(key, 'uploading')

    let picture: Blob

    try {
      picture = await shrink(file)
    }
    catch {
      // HEIC in a browser without HEIC, or a broken file.
      set(key, 'failed', t('photos.format'))

      return
    }

    const body = new FormData()
    body.append('photo', picture, 'photo.jpg')
    body.append('fingerprint', print)

    const day = await takenOn(file)

    if (day !== null) {
      body.append('taken_on', day)
    }

    try {
      const answer = await $fetch<{ duplicate: boolean }>('/api/photos', {
        method: 'POST',
        body,
        baseURL: '/',
        credentials: 'same-origin',
      })

      set(key, answer.duplicate ? 'known' : 'done')
      files.delete(key)
    }
    catch (error) {
      set(key, 'failed', t(reason((error as { statusCode?: number }).statusCode)))
    }
  }

  return { items, running, summary, add, retry, clear }
}

async function fingerprint(file: File): Promise<string> {
  return hex(await crypto.subtle.digest('SHA-256', await file.arrayBuffer()))
}

async function takenOn(file: File): Promise<string | null> {
  const head = await file.slice(0, EXIF_BYTES).arrayBuffer()

  return exifDate(head) ?? nameDate(file.name)
}

/*
 * Drawn anew at most EDGE wide: a phone photo of 5 MB arrives as a few
 * hundred kilobytes. `from-image` turns portrait shots upright, since the
 * rotation lives in EXIF, and EXIF does not survive the canvas.
 */
async function shrink(file: File): Promise<Blob> {
  const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' })
  const size = fitWithin(bitmap.width, bitmap.height, EDGE)
  const canvas = document.createElement('canvas')
  canvas.width = size.width
  canvas.height = size.height
  canvas.getContext('2d')!.drawImage(bitmap, 0, 0, size.width, size.height)
  bitmap.close()

  return await new Promise<Blob>((resolve, reject) => canvas.toBlob(
    blob => blob ? resolve(blob) : reject(new Error('encode')),
    'image/jpeg',
    0.85,
  ))
}

/*
 * The screen stays on while pictures go up: a phone that locks itself
 * pauses the page, and with it the upload.
 */
async function keepAwake(): Promise<void> {
  try {
    lock = await navigator.wakeLock?.request('screen') ?? null
  }
  catch {
    // Not offered or refused (battery saver): then it just goes on as long as it can.
  }
}

/** The message key for a failed upload. */
function reason(status: number | undefined): string {
  switch (status) {
    case undefined:
      return 'photos.noConnection'
    case 413:
      return 'photos.tooLarge'
    case 422:
      return 'photos.unreadable'
    default:
      return 'photos.uploadFailed'
  }
}
