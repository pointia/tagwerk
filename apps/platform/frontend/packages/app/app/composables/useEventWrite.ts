import type { AgendaEvent } from '~/utils/calendar'
import { isOffline } from '../../../shared/app/utils/network'

export interface EventInput {
  calendar_id?: number
  title: string
  description?: string | null
  location?: string | null
  starts_at: string
  ends_at: string
  all_day: boolean
  people: number[]
}

/** The first field message of a validation; null when none came along. */
function explainFirst(exception: unknown): string | null {
  const data = (exception as { data?: { message?: string, errors?: Record<string, string[]> } }).data
  const first = Object.values(data?.errors ?? {})[0]?.[0]

  return first ?? data?.message ?? null
}

/**
 * Writing events, on the wall and on the phone.
 *
 * Offline is deliberately blocked instead of queued: a silently swallowed
 * input would be worse than a rejected one -- nobody types an event twice
 * that they believe is saved.
 */
export function useEventWrite() {
  const { t } = useI18n()

  const { withPin } = usePinGate()

  const busy = ref(false)
  const error = ref<string | null>(null)

  /** On a conflict, the state actually stored at the provider. */
  const foreignState = ref<AgendaEvent | null>(null)

  function reset(): void {
    error.value = null
    foreignState.value = null
  }

  async function send<T>(path: string, method: 'POST' | 'PATCH' | 'DELETE', body?: EventInput): Promise<T | null> {
    reset()

    if (isOffline()) {
      error.value = t('network.offline')

      return null
    }

    busy.value = true

    try {
      return await withPin(() => $fetch<T>(path, {
        method: method,
        body: body,
        credentials: 'same-origin',
      }))
    }
    catch (exception: unknown) {
      const status = (exception as { status?: number }).status

      if (status === 409) {
        const data = (exception as { data?: { event?: AgendaEvent } }).data

        foreignState.value = data?.event ?? null
        error.value = t('event.conflict')

        return null
      }

      /*
       * On 422 what the server says counts. "Die Eingabe ist unvollständig"
       * would send the household in the wrong direction when really an
       * account has expired — and that is exactly the message the server
       * writes.
       */
      error.value = status === 422
        ? explainFirst(exception) ?? t('event.incomplete')
        : t('event.noAnswer')

      return null
    }
    finally {
      busy.value = false
    }
  }

  return {
    busy,
    error,
    foreignState,
    reset,

    create: (input: EventInput) => send<AgendaEvent>('/api/wall/events', 'POST', input),
    update: (id: number, input: EventInput) => send<AgendaEvent>(`/api/wall/events/${id}`, 'PATCH', input),
    destroy: (id: number) => send<null>(`/api/wall/events/${id}`, 'DELETE'),
  }
}
