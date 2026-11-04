import { isOffline } from '../../../shared/app/utils/network'

export interface ListItemDto {
  id: number
  list_id: number
  title: string
  quantity: string | null
  done: boolean
  position: number
}

export interface ChecklistDto {
  id: number
  name: string
  kind: 'shopping' | 'packing' | 'generic'
  color: string | null
  open_count: number
  items: ListItemDto[]
}

export function useLists() {
  const { t } = useI18n()

  const { withPin } = usePinGate()

  const lists = useState<ChecklistDto[]>('lists', () => [])
  // Before the first answer, no lists means "not known yet", not "none".
  const loaded = useState('lists-loaded', () => false)
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    try {
      const data = await $fetch<{ lists: ChecklistDto[] }>('/api/wall/lists', {
        credentials: 'same-origin',
      })

      lists.value = data.lists
      loaded.value = true
      error.value = null
    }
    catch {
      // As with the calendar: the last loaded state stays.
      error.value = t('lists.loadFailed')
    }
  }

  async function write(job: () => Promise<unknown>): Promise<boolean> {
    if (isOffline()) {
      error.value = t('network.offline')

      return false
    }

    try {
      await withPin(job)
      error.value = null

      return true
    }
    catch {
      error.value = t('common.changeNotSaved')

      return false
    }
  }

  async function create(listId: number, title: string, quantity: string | null): Promise<void> {
    const success = await write(() => $fetch<unknown>(`/api/wall/lists/${listId}/items`, {
      method: 'POST',
      body: { title, quantity },
      credentials: 'same-origin',
    }))

    if (success) {
      await load()
    }
  }

  /**
   * Checking off takes effect immediately and is reverted on failure.
   *
   * At the wall people stand in front and wait: a delay of half a second
   * reads there as a broken button, and then they tap a second time.
   */
  async function toggle(item: ListItemDto): Promise<void> {
    const before = item.done

    item.done = !before

    const success = await write(() => $fetch<unknown>(`/api/wall/items/${item.id}`, {
      method: 'PATCH',
      body: { done: item.done },
      credentials: 'same-origin',
    }))

    if (!success) {
      item.done = before

      return
    }

    await load()
  }

  async function remove(item: ListItemDto): Promise<void> {
    const success = await write(() => $fetch<unknown>(`/api/wall/items/${item.id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
    }))

    if (success) {
      await load()
    }
  }

  async function cleanUp(listId: number): Promise<void> {
    const success = await write(() => $fetch<unknown>(`/api/wall/lists/${listId}/done`, {
      method: 'DELETE',
      credentials: 'same-origin',
    }))

    if (success) {
      await load()
    }
  }

  return { lists, loaded, error, load, create, toggle, remove, cleanUp }
}
