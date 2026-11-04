export interface NotificationDto {
  id: string
  title: string
  body: string | null
  url: string | null
  read: boolean
  created_at: string | null
}

/**
 * An account's messages — the same list as the admin's bell. A wall has
 * none: nobody there would read them.
 */
export function useNotifications() {
  const items = useState<NotificationDto[]>('notifications', () => [])
  const unread = useState('notifications-unread', () => 0)
  const loaded = useState('notifications-loaded', () => false)

  async function load(): Promise<void> {
    try {
      const data = await $fetch<{ unread: number, items: NotificationDto[] }>('/api/notifications', { baseURL: '/' })

      items.value = data.items
      unread.value = data.unread
      loaded.value = true
    }
    catch {
      // Without a network the last list stays.
    }
  }

  async function markRead(): Promise<void> {
    if (unread.value === 0) {
      return
    }

    unread.value = 0
    items.value = items.value.map(item => ({ ...item, read: true }))
    await $fetch<unknown>('/api/notifications/read', { baseURL: '/', method: 'POST' }).catch(() => null)
  }

  return { items, unread, loaded, load, markRead }
}
