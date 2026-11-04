import type { PhotoDto } from '~/composables/usePhotos'

export interface MemoryDto {
  key: string
  /** `on_this_day`: the same days `years` ago; `days`: a busy run of days. */
  kind: 'on_this_day' | 'days'
  years: number | null
  from: string
  until: string
  count: number
  photos: PhotoDto[]
}

/**
 * Stories from the pictures' dates, made on the server. Fetched once per
 * start and again when the wall asks for it; a failed fetch keeps the last.
 */
export function usePhotoMemories() {
  const memories = useState<MemoryDto[]>('photo-memories', () => [])
  const loaded = useState<boolean>('photo-memories-loaded', () => false)

  async function load(): Promise<void> {
    try {
      const data = await $fetch<{ memories: MemoryDto[] }>('/api/wall/photos/memories', {
        baseURL: '/',
        credentials: 'same-origin',
      })

      memories.value = data.memories
    }
    catch {
      // The frame then shows plain pictures.
    }
    finally {
      loaded.value = true
    }
  }

  return { memories, loaded, load }
}
