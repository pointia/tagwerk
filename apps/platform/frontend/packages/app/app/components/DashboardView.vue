<script setup lang="ts">
import type { Tile, Placement } from '~/utils/grid'
import type { AgendaEvent } from '~/utils/calendar'
import WidgetCalendar from './widgets/WidgetCalendar.vue'
import WidgetClock from './widgets/WidgetClock.vue'
import WidgetGeneric from './widgets/WidgetGeneric.vue'
import WidgetLists from './widgets/WidgetLists.vue'
import WidgetMeals from './widgets/WidgetMeals.vue'
import WidgetMembers from './widgets/WidgetMembers.vue'
import WidgetPhoto from './widgets/WidgetPhoto.vue'
import WidgetRewards from './widgets/WidgetRewards.vue'
import WidgetStars from './widgets/WidgetStars.vue'
import WidgetTasks from './widgets/WidgetTasks.vue'
import WidgetWeather from './widgets/WidgetWeather.vue'
import { COLUMNS, cellOffset, fits, resized, shifted, rows, stacked } from '~/utils/grid'

const { t } = useI18n()

const props = defineProps<{ timeZone: string, today: string }>()

/*
 * Tiles lead on: a person in the tasks tile to their tasks, an event to its
 * dialog. Not while arranging — then a tap means grabbing the tile.
 */
const emit = defineEmits<{ 'pick-event': [AgendaEvent], 'show-tasks': [number] }>()

const { tiles, offer, error, load, place, configure, configurePlugin, add, remove } = useDashboard()

/*
 * Mapped statically instead of resolved by name: that way the type checker
 * notices when a key from the server list has no component here — and not
 * only the tablet on the wall.
 */
const COMPONENTS = {
  calendar: WidgetCalendar,
  tasks: WidgetTasks,
  stars: WidgetStars,
  rewards: WidgetRewards,
  members: WidgetMembers,
  meals: WidgetMeals,
  lists: WidgetLists,
  weather: WidgetWeather,
  clock: WidgetClock,
  photo: WidgetPhoto,
} as const

type WidgetKey = keyof typeof COMPONENTS

function component(type: string) {
  return COMPONENTS[type as WidgetKey] ?? null
}

const label = computed(() => new Map(offer.value.map(entry => [entry.type, entry.label])))

// Plugin widgets have no component of their own; their content comes as data.
const generic = computed(() => new Set(offer.value.filter(entry => entry.generic).map(entry => entry.type)))

// A plugin's settings form, by widget type.
const fields = computed(() => new Map(offer.value.map(entry => [entry.type, entry.settings ?? []])))

onMounted(load)

/*
 * Twelve columns do not fit a phone: there the tiles stack in reading order,
 * each as tall as it is on the grid. Arranging happens where the grid fits.
 */
const narrow = useNarrow()
const shown = computed(() => (narrow.value ? stacked(tiles.value) : tiles.value))

/** Rows are 72 pixels at least on the grid; a stacked tile keeps that. */
const STACKED_ROW = 72

/*
 * On the grid rows stretch to fill the screen, stacked they do not: these
 * tiles need more rows on a phone than their grid size to show everything.
 */
const STACKED_MIN_ROWS: Record<string, number> = { weather: 3 }

function tileStyle(tile: Tile): Record<string, string> {
  if (narrow.value) {
    return { height: `${Math.max(tile.h, STACKED_MIN_ROWS[tile.type] ?? 0) * STACKED_ROW}px` }
  }

  return {
    gridColumn: `${placementOf(tile).x + 1} / span ${placementOf(tile).w}`,
    gridRow: `${placementOf(tile).y + 1} / span ${placementOf(tile).h}`,
  }
}

/*
 * No dragging without the switch: on a wall in the hallway every accidental
 * swipe would otherwise move half the dashboard.
 */
const editMode = ref(false)
const pickerOpen = ref(false)

const pinOpen = ref(false)
const pending = ref<(() => Promise<void>) | null>(null)

async function pencilTap(): Promise<void> {
  if (editMode.value) {
    editMode.value = false
    pickerOpen.value = false

    return
  }

  const snapshot = await $fetch<{ required: boolean, unlocked: boolean }>('/api/wall/pin', {
    credentials: 'same-origin',
  }).catch(() => ({ required: false, unlocked: true }))

  if (snapshot.required && !snapshot.unlocked) {
    pending.value = async () => {
      editMode.value = true
    }
    pinOpen.value = true

    return
  }

  editMode.value = true
}

async function afterUnlock(): Promise<void> {
  pinOpen.value = false

  const job = pending.value

  pending.value = null

  await job?.()
}

const raster = ref<HTMLElement | null>(null)

const rowCount = computed(() => rows(tiles.value))

/** The drag currently in progress. Null means nobody is dragging. */
const drag = ref<{
  id: number
  art: 'ziehen' | 'groesse'
  startX: number
  startY: number
  origin: Placement
  placement: Placement
} | null>(null)

/** The placement shown: the preview while dragging, otherwise the stored one. */
function placementOf(tile: Tile): Placement {
  return drag.value?.id === tile.id ? drag.value.placement : tile
}

function cellSize(): { width: number, height: number } {
  const rect = raster.value?.getBoundingClientRect()

  return {
    width: (rect?.width ?? 0) / COLUMNS,
    height: (rect?.height ?? 0) / rowCount.value,
  }
}

function grab(tile: Tile, art: 'ziehen' | 'groesse', event: PointerEvent): void {
  if (!editMode.value) {
    return
  }

  ;(event.currentTarget as HTMLElement).setPointerCapture(event.pointerId)

  drag.value = {
    id: tile.id,
    art,
    startX: event.clientX,
    startY: event.clientY,
    origin: { x: tile.x, y: tile.y, w: tile.w, h: tile.h },
    placement: { x: tile.x, y: tile.y, w: tile.w, h: tile.h },
  }
}

function move(event: PointerEvent): void {
  if (drag.value === null) {
    return
  }

  const { width, height } = cellSize()
  const dx = cellOffset(event.clientX - drag.value.startX, width)
  const dy = cellOffset(event.clientY - drag.value.startY, height)

  drag.value.placement = drag.value.art === 'ziehen'
    ? shifted(drag.value.origin, dx, dy)
    : resized(drag.value.origin, dx, dy)
}

async function release(): Promise<void> {
  const run = drag.value

  drag.value = null

  if (run === null) {
    return
  }

  const unchanged = run.placement.x === run.origin.x && run.placement.y === run.origin.y
    && run.placement.w === run.origin.w && run.placement.h === run.origin.h

  if (unchanged) {
    return
  }

  const tile = tiles.value.find(entry => entry.id === run.id)

  // Nothing is dropped where something already lies: the tile snaps back
  // instead of pushing another one aside.
  if (!tile || !fits(run.placement, tiles.value, run.id)) {
    error.value = t('dashboard.occupied')

    return
  }

  await mitPin(() => place(tile, run.placement))
}

async function mitPin(job: () => Promise<'ok' | 'pin' | 'fehler'>): Promise<void> {
  if (await job() === 'pin') {
    pending.value = async () => {
      await job()
    }
    pinOpen.value = true
  }
}

async function createTile(type: string): Promise<void> {
  pickerOpen.value = false

  await mitPin(() => add(type))
}

/** Only the calendar has a view to choose: hour grid or list. */
async function toggleCalendarView(tile: Tile): Promise<void> {
  const view = tile.settings.view === 'list' ? 'day' : 'list'

  await mitPin(() => configure(tile, { ...tile.settings, view }))
}

/* A photo tile draws from its own source: everything, favourites, albums or memories. */
const photoTile = ref<Tile | null>(null)

async function choosePhotoSource(settings: Record<string, unknown>): Promise<void> {
  const tile = photoTile.value
  photoTile.value = null

  if (tile) {
    await mitPin(() => configure(tile, settings))
  }
}

/* A plugin tile's settings, drawn from the fields it declares. */
const settingsTile = ref<Tile | null>(null)

async function savePluginSettings(settings: Record<string, unknown>): Promise<void> {
  const tile = settingsTile.value
  settingsTile.value = null

  if (tile) {
    await mitPin(() => configurePlugin(tile, settings))
  }
}

async function removeTile(id: number): Promise<void> {
  await mitPin(() => remove(id))
}
</script>

<template>
  <div class="relative flex h-full min-h-0 flex-col">
    <p v-if="error" role="alert" class="mb-2 text-base text-danger">
      {{ error }}
    </p>

    <!--
      Floating in the corner instead of a bar of its own: the row above the
      grid cost a whole strip of the wall for one button.
    -->
    <div v-if="!narrow" class="absolute right-3 bottom-3 z-10 flex items-center gap-2">
      <button
        v-if="editMode"
        type="button"
        data-testid="widget-picker"
        class="flex min-h-12 items-center gap-2 rounded-full bg-surface-2 px-5 text-base font-semibold shadow-floating"
        @click="pickerOpen = !pickerOpen"
      >
        <TwIcon name="plus" :size="18" />
        {{ $t('dashboard.tile') }}
      </button>

      <button
        type="button"
        data-testid="dashboard-edit"
        :aria-pressed="editMode"
        :aria-label="editMode ? $t('dashboard.editDone') : $t('dashboard.edit')"
        class="flex size-12 items-center justify-center rounded-full shadow-floating transition-colors"
        :class="editMode ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2'"
        @click="pencilTap"
      >
        <TwIcon :name="editMode ? 'check' : 'pencil'" :size="20" />
      </button>
    </div>

    <div
      v-if="pickerOpen"
      class="mb-2 flex flex-wrap gap-2 rounded-3xl bg-surface p-3 shadow-card"
    >
      <button
        v-for="entry in offer"
        :key="entry.type"
        type="button"
        data-testid="widget-offer"
        class="min-h-11 rounded-2xl bg-surface-2 px-4 text-base font-semibold"
        @click="createTile(entry.type)"
      >
        {{ entry.label }}
      </button>
    </div>

    <!-- No grid gap but padding per tile: otherwise the cell width while
         dragging would not match the computed one. -->
    <div
      ref="raster"
      data-testid="dashboard-grid"
      :class="narrow ? 'flex min-h-0 flex-1 flex-col overflow-y-auto' : 'grid min-h-0 flex-1 overflow-y-auto'"
      :style="narrow ? {} : {
        gridTemplateColumns: `repeat(${COLUMNS}, minmax(0, 1fr))`,
        gridAutoRows: `minmax(${STACKED_ROW}px, 1fr)`,
      }"
    >
      <div
        v-for="tile in shown"
        :key="tile.id"
        data-testid="dashboard-tile"
        :data-type="tile.type"
        class="shrink-0 p-1"
        :style="tileStyle(tile)"
      >
        <section
          class="relative flex h-full min-h-0 flex-col rounded-3xl bg-surface p-4 shadow-card transition-shadow"
          :class="[
            editMode ? 'cursor-grab ring-2 ring-accent/40' : '',
            drag?.id === tile.id ? 'shadow-floating' : '',
          ]"
          @pointerdown="grab(tile, 'ziehen', $event)"
          @pointermove="move"
          @pointerup="release"
          @pointercancel="release"
        >
          <h3 class="mb-2 shrink-0 truncate text-sm font-bold text-ink-3">
            {{ label.get(tile.type) ?? tile.type }}
          </h3>

          <div class="min-h-0 flex-1 overflow-hidden">
            <component
              :is="component(tile.type)"
              v-if="component(tile.type)"
              :time-zone="props.timeZone"
              :today="props.today"
              v-bind="tile.type === 'calendar' ? { settings: tile.settings } : tile.type === 'photo' ? { settings: tile.settings, tileId: tile.id, w: tile.w, h: tile.h } : {}"
              @pick-event="(event: AgendaEvent) => editMode || emit('pick-event', event)"
              @person="(id: number) => editMode || emit('show-tasks', id)"
            />
            <WidgetGeneric v-else-if="generic.has(tile.type)" :tile-id="tile.id" :editing="editMode" :settings="tile.settings" />
          </div>

          <template v-if="editMode">
            <button
              type="button"
              data-testid="tile-remove"
              :aria-label="$t('common.removeNamed', { name: label.get(tile.type) ?? tile.type })"
              class="absolute -top-1 -right-1 flex size-9 items-center justify-center rounded-full bg-danger text-surface shadow-card"
              @pointerdown.stop
              @click="removeTile(tile.id)"
            >
              <TwIcon name="close" :size="16" />
            </button>

            <button
              v-if="tile.type === 'photo'"
              type="button"
              data-testid="tile-source"
              class="absolute top-2 right-10 flex min-h-11 max-w-[60%] items-center gap-1.5 truncate rounded-2xl bg-surface-2 px-3 text-sm font-semibold"
              @pointerdown.stop
              @click="photoTile = tile"
            >
              <TwIcon name="albums" :size="16" />
              {{ $t('dashboard.photoSource') }}
            </button>

            <button
              v-if="(fields.get(tile.type) ?? []).length > 0"
              type="button"
              data-testid="tile-settings-open"
              class="absolute top-2 right-10 flex min-h-11 items-center gap-1.5 rounded-2xl bg-surface-2 px-3 text-sm font-semibold"
              @pointerdown.stop
              @click="settingsTile = tile"
            >
              {{ $t('dashboard.tileSettings') }}
            </button>

            <button
              v-if="tile.type === 'calendar'"
              type="button"
              data-testid="tile-view"
              class="absolute top-2 right-10 flex min-h-11 items-center rounded-2xl bg-surface-2 px-3 text-sm font-semibold"
              @pointerdown.stop
              @click="toggleCalendarView(tile)"
            >
              {{ tile.settings.view === 'list' ? $t('dashboard.asGrid') : $t('dashboard.asList') }}
            </button>

            <span
              data-testid="tile-handle"
              class="absolute right-1 bottom-1 size-7 cursor-se-resize rounded-tl-xl bg-accent/30"
              @pointerdown.stop="grab(tile, 'groesse', $event)"
              @pointermove="move"
              @pointerup="release"
              @pointercancel="release"
            />
          </template>
        </section>
      </div>

      <p v-if="narrow" data-testid="dashboard-arrange-hint" class="mt-3 mb-2 shrink-0 text-center text-sm text-ink-3">
        {{ $t('dashboard.arrangeWide') }}
      </p>
    </div>

    <PhotoTileSource
      v-if="photoTile"
      :settings="photoTile.settings"
      @close="photoTile = null"
      @save="choosePhotoSource"
    />

    <TileSettings
      v-if="settingsTile"
      :title="label.get(settingsTile.type) ?? settingsTile.type"
      :fields="fields.get(settingsTile.type) ?? []"
      :settings="settingsTile.settings"
      @close="settingsTile = null"
      @save="savePluginSettings"
    />

    <PinDialog
      v-if="pinOpen"
      @close="pinOpen = false"
      @unlocked="afterUnlock"
    />
  </div>
</template>
