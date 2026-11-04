import { isOffline, requiresPin, serverMessage } from '../../../shared/app/utils/network'
import type { PendingDto, RewardDto } from '../../../shared/app/utils/points'

export type { PendingDto, RewardDto }

export interface Badge {
  key: string
  icon: string
  name: string
}

export interface SavingsGoal {
  reward: string
  cost: number
  missing: number
  share: number
}

export interface PointPerson {
  id: number
  name: string
  color: string | null
  /** URL of the picture; null means the initial is enough. */
  avatar: string | null
  balance: number
  week_points: number
  month_points: number
  /** Consecutive days with at least one completion. */
  streak: number
  /** 0 means no goal set. */
  weekly_goal: number
  goal_reached: boolean
  badges: Badge[]
  saving: SavingsGoal | null
}

export interface PointEntryDto {
  id: number
  person_id: number
  person: string | null
  /** URL of the picture; null means the initial is enough. */
  avatar: string | null
  points: number
  reason: string
  created_at: string | null
}

/** Which bookings the list shows; null means every one. */
export interface BookingFilter {
  person: number | null
  sign: 'plus' | 'minus' | null
}

interface PointsResponse {
  people: PointPerson[]
  entries: PointEntryDto[]
  has_more: boolean
}

type PointsState = [PointsResponse, { rewards: RewardDto[], pending: PendingDto[] }, { required: boolean, unlocked: boolean }]

/*
 * One load at a time, shared: the shell, the view and the dashboard tiles all
 * ask at the same moment, and on a phone each copy costs a round trip.
 */
let inFlight: Promise<PointsState> | null = null

export function usePoints() {
  const { t } = useI18n()

  const people = useState<PointPerson[]>('points-people', () => [])
  const bookings = useState<PointEntryDto[]>('point-bookings', () => [])
  const rewards = useState<RewardDto[]>('rewards', () => [])
  const pending = useState<PendingDto[]>('redemptions', () => [])

  // The history comes page by page; how far it was paged is remembered.
  const more = useState<boolean>('points-more', () => false)
  const page = useState<number>('points-page', () => 1)

  // Kept across reloads (realtime, after a booking), so the list stays filtered.
  const filter = useState<BookingFilter>('points-filter', () => ({ person: null, sign: null }))

  function query(nextPage: number): Record<string, string | number> {
    return {
      page: nextPage,
      ...(filter.value.person !== null ? { person: filter.value.person } : {}),
      ...(filter.value.sign !== null ? { sign: filter.value.sign } : {}),
    }
  }

  const { entry } = useEntry()

  // Before the first answer, no people means "not known yet", not "nobody".
  const loaded = useState('points-loaded', () => false)
  const pinRequired = useState<boolean>('pin-required', () => false)
  const unlocked = useState<boolean>('pin-unlocked', () => false)
  const error = ref<string | null>(null)
  // Whether the last write failed on the PIN, not on anything else.
  const locked = ref(false)

  async function load(): Promise<void> {
    try {
      inFlight ??= Promise.all([
        $fetch<PointsResponse>('/api/wall/points', {
          params: query(1),
          credentials: 'same-origin',
        }),
        $fetch<{ rewards: RewardDto[], pending: PendingDto[] }>('/api/wall/rewards', {
          credentials: 'same-origin',
        }),
        // The PIN stands in for a sign-in: an account never has one to ask.
        entry.value === 'user'
          ? Promise.resolve({ required: false, unlocked: false })
          : $fetch<{ required: boolean, unlocked: boolean }>('/api/wall/pin', {
              credentials: 'same-origin',
            }),
      ]).finally(() => {
        inFlight = null
      })

      const [points, reward, pin] = await inFlight

      people.value = points.people
      bookings.value = points.entries
      more.value = points.has_more
      page.value = 1
      rewards.value = reward.rewards
      pending.value = reward.pending
      pinRequired.value = pin.required
      unlocked.value = pin.unlocked
      loaded.value = true
      error.value = null
    }
    catch {
      error.value = t('points.loadFailed')
    }
  }

  /** Appends the next page of the history instead of replacing it. */
  async function loadMore(): Promise<void> {
    const nextIndex = page.value + 1

    try {
      const response = await $fetch<PointsResponse>('/api/wall/points', {
        params: query(nextIndex),
        credentials: 'same-origin',
      })

      bookings.value = [...bookings.value, ...response.entries]
      more.value = response.has_more
      page.value = nextIndex
    }
    catch {
      error.value = t('points.olderFailed')
    }
  }

  /**
   * Returns whether the action went through.
   *
   * With `false` and `pinRequired` set only the unlock is missing — the view
   * then asks for the PIN and calls the same action again.
   */
  async function write(path: string, body: Record<string, unknown> = {}): Promise<boolean> {
    locked.value = false

    if (isOffline()) {
      error.value = t('network.offline')

      return false
    }

    try {
      await $fetch(path, { method: 'POST', body: body, credentials: 'same-origin' })
      await load()
      error.value = null

      return true
    }
    catch (exception) {
      if (requiresPin(exception)) {
        unlocked.value = false
        pinRequired.value = true
        locked.value = true

        return false
      }

      /*
       * The server says why ("3 more stars are needed"); a generic line hides
       * it. Decided meanwhile elsewhere: the list shown is stale, so it
       * reloads — and keeps the reason.
       */
      const message = serverMessage(exception) ?? t('common.failed')

      await load()
      error.value = message

      return false
    }
  }

  function requests(rewardId: number, personId: number): Promise<boolean> {
    return write(`/api/wall/rewards/${rewardId}/request`, { person_id: personId })
  }

  function approve(redemptionId: number): Promise<boolean> {
    return write(`/api/wall/redemptions/${redemptionId}/approve`)
  }

  function reject(redemptionId: number): Promise<boolean> {
    return write(`/api/wall/redemptions/${redemptionId}/reject`)
  }

  function book(personId: number, points: number, reason: string): Promise<boolean> {
    return write('/api/wall/points', { person_id: personId, points: points, reason: reason })
  }

  function gift(fromId: number, toId: number, points: number, note: string): Promise<boolean> {
    return write('/api/wall/points/gift', { from_person_id: fromId, to_person_id: toId, points: points, note: note || null })
  }

  async function unlock(pin: string): Promise<boolean> {
    try {
      await $fetch('/api/wall/pin', {
        method: 'POST',
        body: { pin },
        credentials: 'same-origin',
      })

      unlocked.value = true
      error.value = null

      return true
    }
    catch {
      return false
    }
  }

  async function finish(): Promise<void> {
    await $fetch('/api/wall/pin', { method: 'DELETE', credentials: 'same-origin' }).catch(() => {})
    unlocked.value = false
  }

  /** A new filter starts the list over at the newest booking. */
  async function filterBookings(next: BookingFilter): Promise<void> {
    filter.value = next
    await load()
  }

  return {
    people,
    loaded,
    bookings,
    more,
    loadMore,
    filter,
    filterBookings,
    rewards,
    pending,
    pinRequired,
    unlocked,
    locked,
    error,
    load,
    requests,
    approve,
    reject,
    book,
    gift,
    unlock,
    finish,
  }
}
