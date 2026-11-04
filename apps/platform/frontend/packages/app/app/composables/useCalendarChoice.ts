export interface CalendarChoice {
  id: number
  summary: string
  color: string
  shown: boolean
}

/**
 * Which of the household's calendars this account shows. Stored at the
 * account on the server, so a new phone starts with the same choice.
 */
export function useCalendarChoice() {
  const { t } = useI18n()

  const calendars = useState<CalendarChoice[]>('calendar-choice', () => [])
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    try {
      const response = await $fetch<{ calendars: CalendarChoice[] }>('/api/calendars/choice', {
        baseURL: '/', credentials: 'same-origin',
      })

      calendars.value = response.calendars
      error.value = null
    }
    catch {
      error.value = t('calendars.loadFailed')
    }
  }

  /** Flips one calendar; the list shows the change at once and the server's answer after. */
  async function toggle(id: number): Promise<boolean> {
    const next = calendars.value.map(entry => entry.id === id ? { ...entry, shown: !entry.shown } : entry)
    const previous = calendars.value
    calendars.value = next

    try {
      const response = await $fetch<{ calendars: CalendarChoice[] }>('/api/calendars/choice', {
        method: 'PUT',
        body: { hidden: next.filter(entry => !entry.shown).map(entry => entry.id) },
        baseURL: '/', credentials: 'same-origin',
      })

      calendars.value = response.calendars
      error.value = null

      return true
    }
    catch {
      calendars.value = previous
      error.value = t('common.tryAgain')

      return false
    }
  }

  return { calendars, error, load, toggle }
}
