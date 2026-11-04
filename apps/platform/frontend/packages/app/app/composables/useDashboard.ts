import type { Tile, Placement } from '~/utils/grid'
import { isOffline, requiresPin } from '../../../shared/app/utils/network'
import { COLUMNS, freeCell } from '~/utils/grid'

/** A field of a plugin tile's settings form; the server checks the answer. */
export interface SettingField {
  kind: 'text' | 'number' | 'select' | 'toggle' | 'secret'
  key: string
  label: string
  required: boolean
  options: { value: string, label: string }[]
}

export interface WidgetOffer {
  type: string
  label: string
  description: string
  feature: string | null
  w: number
  h: number
  /** From a plugin: the content comes as data, drawing happens here. */
  generic: boolean
  /** The plugin's settings form; empty means none. */
  settings: SettingField[]
}

/**
 * "ok" means saved, "pin" means only the unlock is missing.
 *
 * Not exported: the outcome of writing tasks carries the same name, and two
 * auto-imports of the same name cancel each other out.
 */
type Outcome = 'ok' | 'pin' | 'fehler'

interface DashboardDto {
  columns: number
  widgets: Tile[]
  available: WidgetOffer[]
}

/**
 * The home page.
 *
 * Every paired device may read it; rearranging happens behind the parent
 * PIN. A moved tile is seen afterwards by everyone walking past the tablet —
 * that is the same kind of decision as a new task.
 */
export function useDashboard() {
  const { t } = useI18n()

  const tiles = useState<Tile[]>('dashboard-tiles', () => [])
  const offer = useState<WidgetOffer[]>('dashboard-offer', () => [])
  const error = ref<string | null>(null)

  async function load(): Promise<void> {
    try {
      const data = await $fetch<DashboardDto>('/api/wall/dashboard', { credentials: 'same-origin' })

      tiles.value = data.widgets
      offer.value = data.available
      error.value = null
    }
    catch {
      // As everywhere on the wall: the last loaded state stays.
      error.value = t('dashboard.loadFailed')
    }
  }

  async function write(job: () => Promise<unknown>): Promise<Outcome> {
    if (isOffline()) {
      error.value = t('network.offline')

      return 'fehler'
    }

    try {
      await job()
      error.value = null

      return 'ok'
    }
    catch (exception: unknown) {
      if (requiresPin(exception)) {
        return 'pin'
      }

      error.value = (exception as { data?: { message?: string } }).data?.message
        ?? t('dashboard.saveFailed')

      return 'fehler'
    }
  }

  /** The new placement of a tile. It only applies once the server accepts it. */
  async function place(tile: Tile, placement: Placement): Promise<Outcome> {
    const outcome = await write(() => $fetch<unknown>(`/api/wall/dashboard/${tile.id}`, {
      method: 'PATCH',
      // Without settings: the server keeps them, secrets included.
      body: placement,
      credentials: 'same-origin',
    }))

    if (outcome === 'ok') {
      tiles.value = tiles.value.map(entry => entry.id === tile.id
        ? { ...entry, ...placement }
        : entry)
    }

    return outcome
  }

  async function configure(tile: Tile, settings: Record<string, unknown>): Promise<Outcome> {
    const outcome = await write(() => $fetch<unknown>(`/api/wall/dashboard/${tile.id}`, {
      method: 'PATCH',
      body: { x: tile.x, y: tile.y, w: tile.w, h: tile.h, settings },
      credentials: 'same-origin',
    }))

    if (outcome === 'ok') {
      tiles.value = tiles.value.map(entry => entry.id === tile.id ? { ...entry, settings } : entry)
    }

    return outcome
  }

  /* A plugin form: the server's answer holds what may be shown (secrets as set or not). */
  async function configurePlugin(tile: Tile, settings: Record<string, unknown>): Promise<Outcome> {
    let shown: Record<string, unknown> = tile.settings
    const outcome = await write(async () => {
      const response = await $fetch<{ widget: { settings: Record<string, unknown> | null } }>(`/api/wall/dashboard/${tile.id}`, {
        method: 'PATCH',
        body: { x: tile.x, y: tile.y, w: tile.w, h: tile.h, settings },
        credentials: 'same-origin',
      })

      shown = response.widget.settings ?? {}
    })

    if (outcome === 'ok') {
      tiles.value = tiles.value.map(entry => entry.id === tile.id ? { ...entry, settings: shown } : entry)
    }

    return outcome
  }

  async function add(type: string): Promise<Outcome> {
    const widget = offer.value.find(entry => entry.type === type)

    if (!widget) {
      return 'fehler'
    }

    const rank = freeCell(tiles.value, Math.min(widget.w, COLUMNS), widget.h)

    if (rank === null) {
      error.value = t('dashboard.full')

      return 'fehler'
    }

    const outcome = await write(async () => {
      await $fetch('/api/wall/dashboard', {
        method: 'POST',
        body: { type, ...rank },
        credentials: 'same-origin',
      })
    })

    if (outcome === 'ok') {
      await load()
    }

    return outcome
  }

  async function remove(id: number): Promise<Outcome> {
    const outcome = await write(() => $fetch<unknown>(`/api/wall/dashboard/${id}`, {
      method: 'DELETE',
      credentials: 'same-origin',
    }))

    if (outcome === 'ok') {
      tiles.value = tiles.value.filter(entry => entry.id !== id)
    }

    return outcome
  }

  return { tiles, offer, error, load, place, configure, configurePlugin, add, remove }
}
