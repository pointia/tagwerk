<script setup lang="ts">
import type { PhotoDto, PhotoQuery } from '~/composables/usePhotos'
import { dayRange, duoCount, nextTile, photoStyle, wrapped } from '~/utils/photoFrame'
import { formatIsoDate } from '~/utils/calendar'
import { intlLocale } from '../../../shared/app/utils/intlLocale'

export interface FrameEvent {
  id: number
  title: string
  /** HH:MM, or null for an all-day event. */
  time: string | null
  color: string | null
}

interface Props {
  /** `single`, `duo`, `mosaic`, `ambient` or `memories`. */
  layout: string
  query: PhotoQuery
  intervalMs: number
  fit?: string
  motion?: boolean
  caption?: boolean
  /** The time and day; the ambient layout always shows them beside the picture. */
  clock?: string | null
  date?: string | null
  /** The time over the picture too (other layouts). */
  overlayClock?: boolean
  /** Resting mode: edge to edge, no rounded corners, larger type. */
  bleed?: boolean
  /** Swipe, tap for controls, double tap for a heart. Off at rest and in a tile. */
  interactive?: boolean
  /** A dashboard tile: small, no overlays, a plain line when empty. */
  compact?: boolean
  /** Separate batch per frame on screen. */
  scope?: string
  /** For the ambient layout: today's coming events. */
  upcoming?: FrameEvent[]
  /** The memory to begin with, by key; the first one otherwise. */
  startMemory?: string | null
}

const props: Props = defineProps<Props>()

const { t } = useI18n()
const { image, batch, position, loaded, ratios, ensure, next, previous, marked } = usePhotos(props.scope ?? 'frame')
const { memories, loaded: memoriesLoaded, load: loadMemories } = usePhotoMemories()
const { weather, load: loadWeather } = useWeather()

/* A memories frame without memories shows plain pictures instead of nothing. */
const layout = computed(() => {
  const chosen = photoStyle(props.layout)

  return chosen === 'memories' && memoriesLoaded.value && memories.value.length === 0 ? 'single' : chosen
})

// --- What is on screen -----------------------------------------------------

const urls = computed(() => batch.value.map(photo => photo.url))

const duo = computed<PhotoDto[]>(() => batch.value.slice(position.value, position.value + duoCount(urls.value, position.value, ratios.value)))

const MOSAIC_TILES = 5
const tiles = ref<PhotoDto[]>([])
let lastTile: number | null = null

function fillMosaic(): void {
  tiles.value = batch.value.slice(0, MOSAIC_TILES)
  position.value = Math.min(MOSAIC_TILES - 1, Math.max(0, batch.value.length - 1))
}

const memoryIndex = ref(0)
/** -1 is the title card. */
const memoryPhoto = ref(-1)
const memory = computed(() => memories.value[memoryIndex.value] ?? null)

const current = computed<PhotoDto | null>(() => {
  if (layout.value === 'memories') {
    return memory.value?.photos[Math.max(0, memoryPhoto.value)] ?? null
  }

  return layout.value === 'mosaic' ? null : image.value
})

const memoryKicker = computed(() => memory.value?.kind === 'on_this_day'
  ? t('photos.memories.yearsAgo', memory.value.years ?? 1)
  : t('photos.memories.days'))

const memoryTitle = computed(() => memory.value ? dayRange(memory.value.from, memory.value.until, intlLocale.value) : '')

const takenOn = computed(() => current.value?.taken_on
  ? formatIsoDate(current.value.taken_on, { day: 'numeric', month: 'long', year: 'numeric' })
  : null)

// --- Time ------------------------------------------------------------------

const paused = ref(false)
let timer: ReturnType<typeof setTimeout> | null = null

function stop(): void {
  if (timer !== null) {
    clearTimeout(timer)
    timer = null
  }
}

/** How long the current view stays. */
function stay(): number {
  if (layout.value === 'mosaic') {
    // One tile at a time, so the mosaic moves more often than a single picture.
    return Math.max(5_000, props.intervalMs / 3)
  }

  if (layout.value === 'memories') {
    return memoryPhoto.value === -1 ? 5_000 : Math.min(props.intervalMs, 7_000)
  }

  return props.intervalMs
}

function schedule(): void {
  stop()

  if (!paused.value) {
    timer = setTimeout(() => void forward(), stay())
  }
}

async function forward(): Promise<void> {
  if (layout.value === 'mosaic') {
    if (tiles.value.length < MOSAIC_TILES) {
      fillMosaic()
    }
    else {
      await next()
      lastTile = nextTile(tiles.value.length, lastTile)

      if (image.value) {
        tiles.value = tiles.value.map((tile, index) => index === lastTile ? image.value! : tile)
      }
    }
  }
  else if (layout.value === 'memories') {
    const photos = memory.value?.photos.length ?? 0

    if (memoryPhoto.value + 1 < photos) {
      memoryPhoto.value++
    }
    else {
      memoryIndex.value = wrapped(memoryIndex.value, 1, memories.value.length)
      memoryPhoto.value = -1
    }
  }
  else {
    await next(layout.value === 'duo' ? duo.value.length || 1 : 1)
  }

  schedule()
}

function back(): void {
  if (layout.value === 'memories') {
    memoryPhoto.value = Math.max(-1, memoryPhoto.value - 1)
  }
  else if (layout.value === 'duo') {
    previous(duoCount(urls.value, Math.max(0, position.value - 2), ratios.value))
  }
  else if (layout.value !== 'mosaic') {
    previous()
  }

  schedule()
}

function togglePause(): void {
  paused.value = !paused.value
  schedule()
}

async function start(): Promise<void> {
  await ensure(props.query)

  if (layout.value === 'mosaic' || photoStyle(props.layout) === 'mosaic') {
    fillMosaic()
  }

  if (photoStyle(props.layout) === 'memories' && !memoriesLoaded.value) {
    await loadMemories()
  }

  if (props.startMemory) {
    memoryIndex.value = Math.max(0, memories.value.findIndex(entry => entry.key === props.startMemory))
  }

  if (layout.value === 'ambient' && weather.value === null) {
    void loadWeather()
  }

  schedule()
}

onMounted(start)
onBeforeUnmount(() => {
  stop()
  hideControlsLater(0)
})

watch(() => [props.layout, JSON.stringify(props.query)], () => {
  memoryIndex.value = 0
  memoryPhoto.value = -1
  tiles.value = []
  void start()
})

watch(() => props.intervalMs, schedule)

// --- Touch -----------------------------------------------------------------

const controls = ref(false)
let controlsTimer: ReturnType<typeof setTimeout> | null = null

function hideControlsLater(ms = 4_000): void {
  if (controlsTimer !== null) {
    clearTimeout(controlsTimer)
  }

  controlsTimer = ms === 0
    ? null
    : setTimeout(() => {
        controls.value = false
      }, ms)
}

function showControls(): void {
  controls.value = true
  hideControlsLater()
}

const burst = ref<string | null>(null)

async function heart(url: string | undefined): Promise<void> {
  const photo = [...batch.value, ...tiles.value, ...(memory.value?.photos ?? [])].find(entry => entry.url === url)

  // A folder's pictures carry no heart.
  if (!photo || photo.favorite === null) {
    return
  }

  const favorite = !photo.favorite
  burst.value = favorite ? photo.url : null
  setTimeout(() => {
    burst.value = null
  }, 900)

  try {
    await $fetch<unknown>(`/api/wall/photos/${photo.id}/favorite`, {
      method: 'PUT',
      body: { favorite },
      baseURL: '/',
      credentials: 'same-origin',
    })

    marked(photo.url, favorite)
    tiles.value = tiles.value.map(tile => tile.url === photo.url ? { ...tile, favorite } : tile)
    photo.favorite = favorite
  }
  catch {
    burst.value = null
  }
}

let down: { x: number, y: number } | null = null
let lastTap = 0
let tapTimer: ReturnType<typeof setTimeout> | null = null

function pressed(event: PointerEvent): void {
  down = { x: event.clientX, y: event.clientY }
}

function released(event: PointerEvent): void {
  if (!props.interactive || down === null || (event.target as HTMLElement).closest('button')) {
    down = null

    return
  }

  const dx = event.clientX - down.x
  const dy = event.clientY - down.y
  down = null

  if (Math.abs(dx) > 60 && Math.abs(dx) > Math.abs(dy)) {
    void (dx < 0 ? forward() : back())

    return
  }

  if (Math.hypot(dx, dy) > 10) {
    return
  }

  // A double tap hearts the picture under the finger; a single one shows the controls.
  const url = (event.target as HTMLElement).closest<HTMLElement>('[data-photo]')?.dataset.photo
  const now = Date.now()

  if (tapTimer !== null) {
    clearTimeout(tapTimer)
    tapTimer = null
  }

  if (now - lastTap < 300) {
    lastTap = 0
    void heart(url)

    return
  }

  lastTap = now
  tapTimer = setTimeout(() => {
    tapTimer = null
    showControls()
  }, 300)
}

function key(event: KeyboardEvent): void {
  if (!props.interactive) {
    return
  }

  if (event.key === 'ArrowRight') {
    void forward()
  }
  else if (event.key === 'ArrowLeft') {
    back()
  }
  else if (event.key === ' ') {
    event.preventDefault()
    togglePause()
  }
}

const empty = computed(() => loaded.value && batch.value.length === 0)
</script>

<template>
  <p v-if="empty && props.compact" class="text-base text-ink-3">
    {{ $t('photos.none') }}
  </p>

  <div
    v-else-if="empty"
    class="flex h-full flex-col items-center justify-center rounded-3xl border-2 border-dashed border-line p-10 text-center"
  >
    <TwIcon name="photo" :size="44" class="text-ink-3" />
    <p class="mt-4 font-display text-3xl font-semibold">
      {{ $t('photos.none') }}
    </p>
    <p class="mt-2 max-w-lg text-lg text-ink-2">
      {{ $t('photos.noneHint') }}
    </p>
  </div>

  <div
    v-else
    data-testid="photo-frame"
    :data-layout="layout"
    :tabindex="props.interactive ? 0 : undefined"
    :aria-label="props.interactive ? $t('photos.frameHint') : undefined"
    class="relative size-full touch-pan-y overflow-hidden bg-black text-white select-none"
    :class="props.bleed ? '' : props.compact ? 'rounded-2xl' : 'rounded-3xl'"
    @pointerdown="pressed"
    @pointerup="released"
    @keydown="key"
  >
    <!-- Single and memories: one picture, crossfading. -->
    <Transition v-if="layout === 'single' || layout === 'memories'" name="photo-fade">
      <div v-if="current" :key="current.url" class="absolute inset-0" :data-photo="current.url">
        <PhotoPicture :url="current.url" :alt="current.caption ?? $t('photos.photo')" :fit="props.fit" :motion="props.motion" />
      </div>
    </Transition>

    <!-- Duo: two portraits side by side, a landscape alone. -->
    <Transition v-else-if="layout === 'duo'" name="photo-fade">
      <div :key="duo.map(photo => photo.url).join('|')" class="absolute inset-0 flex gap-1">
        <div
          v-for="photo in duo"
          :key="photo.url"
          data-testid="photo-duo"
          class="relative min-w-0 flex-1"
          :data-photo="photo.url"
        >
          <PhotoPicture :url="photo.url" :alt="photo.caption ?? $t('photos.photo')" :fit="duo.length > 1 ? 'fill' : props.fit" :motion="props.motion" />
        </div>
      </div>
    </Transition>

    <!-- Mosaic: one large tile and four small, one changing at a time. -->
    <div
      v-else-if="layout === 'mosaic'"
      class="absolute inset-0 grid gap-1"
      :class="props.compact ? 'grid-cols-3 grid-rows-2' : 'grid-cols-2 grid-rows-3 sm:grid-cols-4 sm:grid-rows-2'"
    >
      <div
        v-for="(photo, index) in tiles"
        :key="index"
        data-testid="photo-mosaic-tile"
        class="relative overflow-hidden"
        :class="index === 0 ? (props.compact ? 'col-span-2 row-span-2' : 'col-span-2 sm:row-span-2') : props.compact && index > 2 ? 'hidden' : ''"
        :data-photo="photo.url"
      >
        <Transition name="photo-fade">
          <PhotoPicture :key="photo.url" :url="photo.url" :alt="photo.caption ?? $t('photos.photo')" fit="fill" :motion="props.motion && index === 0" />
        </Transition>
      </div>
    </div>

    <!-- Ambient: the picture beside the time, the weather and today. -->
    <div v-else-if="layout === 'ambient'" class="absolute inset-0 grid grid-rows-[1fr_auto] sm:grid-cols-[minmax(0,1.6fr)_minmax(16rem,1fr)] sm:grid-rows-1">
      <div class="relative min-h-0 overflow-hidden">
        <Transition name="photo-fade">
          <div v-if="current" :key="current.url" class="absolute inset-0" :data-photo="current.url">
            <PhotoPicture :url="current.url" :alt="current.caption ?? $t('photos.photo')" fit="fill" :motion="props.motion" />
          </div>
        </Transition>
      </div>

      <aside data-testid="photo-ambient" class="flex flex-col gap-5 bg-black/85 p-6 sm:justify-center sm:p-8">
        <div>
          <p class="font-display leading-none font-extralight tabular-nums" :class="props.bleed ? 'text-8xl' : 'text-6xl'">
            {{ props.clock }}
          </p>
          <p class="mt-2 text-lg text-white/80">
            {{ props.date }}
            <template v-if="weather">
              · <span aria-hidden="true">{{ weather.symbol }}</span> {{ weather.current }}°
            </template>
          </p>
        </div>

        <ul v-if="(props.upcoming ?? []).length > 0" class="space-y-3">
          <li
            v-for="event in props.upcoming"
            :key="event.id"
            class="border-l-4 pl-3"
            :style="{ borderColor: event.color ?? 'currentColor' }"
          >
            <span class="block text-sm text-white/60 tabular-nums">{{ event.time ?? $t('photos.allDay') }}</span>
            <span class="block truncate text-lg font-medium">{{ event.title }}</span>
          </li>
        </ul>
        <p v-else class="text-base text-white/60">
          {{ $t('photos.nothingToday') }}
        </p>
      </aside>
    </div>

    <!-- The memory's title card over its first picture. -->
    <Transition name="photo-fade">
      <div
        v-if="layout === 'memories' && memory && memoryPhoto === -1"
        data-testid="photo-memory-title"
        class="absolute inset-0 flex flex-col items-center justify-center bg-black/35 px-6 text-center"
      >
        <p class="text-sm font-semibold tracking-[0.2em] text-white/85 uppercase">
          {{ memoryKicker }}
        </p>
        <p class="mt-2 font-display font-bold tracking-tight" :class="props.compact ? 'text-2xl' : 'text-5xl'">
          {{ memoryTitle }}
        </p>
        <p class="mt-2 text-base text-white/80">
          {{ $t('photos.memories.count', memory.count) }}
        </p>
      </div>
    </Transition>

    <!-- Story progress: one segment per memory. -->
    <div v-if="layout === 'memories' && memories.length > 1 && !props.compact" class="absolute inset-x-4 top-4 flex gap-1" aria-hidden="true">
      <span
        v-for="(entry, index) in memories"
        :key="entry.key"
        class="h-1 flex-1 rounded-full"
        :class="index <= memoryIndex ? 'bg-white' : 'bg-white/35'"
      />
    </div>

    <!-- Caption, date and time over the picture. -->
    <div
      v-if="!props.compact && layout !== 'ambient' && layout !== 'mosaic' && ((props.caption && current && (current.caption || takenOn) && memoryPhoto !== -1) || (props.overlayClock && props.clock))"
      class="pointer-events-none absolute inset-x-0 bottom-0 flex items-end justify-between gap-6 bg-linear-to-t from-black/60 to-transparent"
      :class="props.bleed ? 'px-10 pt-16 pb-8' : 'px-6 pt-10 pb-4'"
    >
      <p
        v-if="props.caption && current && (current.caption || takenOn) && !(layout === 'memories' && memoryPhoto === -1)"
        data-testid="photo-caption"
        class="min-w-0 text-lg font-medium"
      >
        {{ current.caption }}
        <span v-if="takenOn" class="block text-base font-normal text-white/80">{{ takenOn }}</span>
      </p>
      <span v-else />

      <p v-if="props.overlayClock && props.clock" data-testid="photo-clock" class="shrink-0 text-right">
        <span class="block font-display leading-none font-extralight tabular-nums" :class="props.bleed ? 'text-8xl' : 'text-6xl'">{{ props.clock }}</span>
        <span v-if="props.date" class="mt-2 block text-xl text-white/85">{{ props.date }}</span>
      </p>
    </div>

    <!-- A heart after a double tap. -->
    <div v-if="burst" class="pointer-events-none absolute inset-0 flex items-center justify-center" aria-hidden="true">
      <span class="tw-pop text-white drop-shadow-lg"><TwIcon name="heart" :size="120" fill="currentColor" /></span>
    </div>

    <span
      v-if="paused && props.interactive"
      data-testid="photo-paused"
      class="pointer-events-none absolute top-4 right-4 flex size-10 items-center justify-center rounded-full bg-black/45 backdrop-blur"
    >
      <TwIcon name="pause" :size="20" />
      <span class="sr-only">{{ $t('photos.paused') }}</span>
    </span>

    <!-- Controls after a tap, like a photo app's: back, pause, on, heart. -->
    <Transition name="photo-controls">
      <div
        v-if="props.interactive && (controls || paused)"
        data-testid="photo-controls"
        class="absolute bottom-6 left-1/2 flex -translate-x-1/2 items-center gap-1 rounded-full bg-black/45 p-1.5 backdrop-blur-md"
        @pointerdown.stop
        @click="showControls"
      >
        <button
          v-if="layout !== 'mosaic'"
          type="button"
          data-testid="photo-previous"
          :aria-label="$t('photos.previous')"
          class="flex size-11 items-center justify-center rounded-full hover:bg-white/15"
          @click="back"
        >
          <TwIcon name="back" />
        </button>
        <button
          type="button"
          data-testid="photo-pause"
          :aria-label="paused ? $t('photos.play') : $t('photos.pause')"
          class="flex size-11 items-center justify-center rounded-full hover:bg-white/15"
          @click="togglePause"
        >
          <TwIcon :name="paused ? 'play' : 'pause'" />
        </button>
        <button
          type="button"
          data-testid="photo-next"
          :aria-label="$t('photos.next')"
          class="flex size-11 items-center justify-center rounded-full hover:bg-white/15"
          @click="forward"
        >
          <TwIcon name="forward" />
        </button>
        <button
          v-if="current && current.favorite !== null"
          type="button"
          data-testid="photo-heart"
          :aria-label="current.favorite ? $t('photos.unfavorite') : $t('photos.favorite')"
          :aria-pressed="current.favorite"
          class="flex size-11 items-center justify-center rounded-full hover:bg-white/15"
          :class="current.favorite ? 'text-danger' : ''"
          @click="heart(current.url)"
        >
          <TwIcon name="heart" :fill="current.favorite ? 'currentColor' : 'none'" />
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.photo-fade-enter-active,
.photo-fade-leave-active {
  transition: opacity 1.4s ease;
}

.photo-fade-enter-from,
.photo-fade-leave-to {
  opacity: 0;
}

.photo-controls-enter-active,
.photo-controls-leave-active {
  transition: opacity 0.25s ease, transform 0.25s ease;
}

.photo-controls-enter-from,
.photo-controls-leave-to {
  opacity: 0;
  transform: translate(-50%, 0.5rem);
}
</style>
