<script setup lang="ts">
import type { LibraryView, ManagedPhotoDto } from '~/composables/usePhotoLibrary'
import { everySelectable, stillThere, toggled } from '~/utils/photoSelection'
import { type LibraryZoom, LIBRARY_ZOOMS, groupPhotos, zoomed } from '~/utils/photoFrame'
import { formatIsoDate } from '../../../shared/app/utils/calendar'

/*
 * The uploaded photos as a phone's gallery: grouped by year, month or day,
 * zoomed with the segmented control or two fingers. A long press starts
 * selecting, taps then add or remove, and the bar on top offers what to do
 * with them. Deleting goes into the trash; the nightly run empties it after
 * thirty days.
 */
interface Props {
  view: LibraryView
  title: string
  /** Shows a back arrow, e.g. from an album to the collections. */
  back?: boolean
  /** The upload buttons in the header; only the library itself has them. */
  upload?: boolean
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  back: []
  trash: []
}>()

const { t } = useI18n()
const library = usePhotoLibrary()
const { photos, nextPage, loading, error, albums, load, more, change, favorite, addToAlbum, removeFromAlbum, createAlbum, loadAlbums } = library
const { running } = usePhotoUpload()
const { armed, tap } = useTwoStep()

const trash = computed(() => props.view.kind === 'trash')
const selected = ref<number[]>([])
const viewing = ref<number | null>(null)
const busy = ref(false)
const zoom = ref<LibraryZoom>('months')
const albumPicker = ref(false)

const selecting = computed(() => selected.value.length > 0)
const groups = computed(() => groupPhotos(photos.value, trash.value ? 'years' : zoom.value))

onMounted(() => {
  void load(props.view)
  void loadAlbums()
})

watch(() => JSON.stringify(props.view), () => {
  selected.value = []
  void load(props.view)
})

// What was just uploaded belongs in the grid.
watch(running, (now, before) => {
  if (before && !now && props.view.kind === 'all') {
    void load(props.view)
  }
})

watch(photos, (list) => {
  selected.value = stillThere(selected.value, list)
})

function heading(key: string): string {
  if (key === '') {
    return t('photos.library.undated')
  }

  if (key.length === 4) {
    return key
  }

  return key.length === 7
    ? formatIsoDate(`${key}-01`, { month: 'long', year: 'numeric' })
    : formatIsoDate(key, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

const COLUMNS: Record<LibraryZoom, string> = {
  years: 'grid-cols-6 sm:grid-cols-10',
  months: 'grid-cols-4 sm:grid-cols-6',
  days: 'grid-cols-3 sm:grid-cols-4',
}

/*
 * Long press: a timer that a lifted finger or a scroll cancels. The click
 * that follows a long press must not toggle the photo straight back.
 */
const HOLD_MS = 450
let hold: ReturnType<typeof setTimeout> | null = null
let held = false
let start = { x: 0, y: 0 }

function cancelHold(): void {
  if (hold !== null) {
    clearTimeout(hold)
  }

  hold = null
}

function pressed(photo: ManagedPhotoDto, event: PointerEvent): void {
  held = false
  start = { x: event.clientX, y: event.clientY }
  cancelHold()

  hold = setTimeout(() => {
    hold = null

    if (selectable(photo) && !selected.value.includes(photo.id)) {
      held = true
      selected.value = toggled(selected.value, { id: photo.id, can_delete: true })
      navigator.vibrate?.(15)
    }
  }, HOLD_MS)
}

function moved(event: PointerEvent): void {
  if (Math.hypot(event.clientX - start.x, event.clientY - start.y) > 10) {
    cancelHold()
  }
}

/*
 * Outside the trash anyone who may upload may also heart and sort, so every
 * photo is selectable there; deleting checks `can_delete` on its own.
 */
function selectable(photo: ManagedPhotoDto): boolean {
  return !trash.value || photo.can_delete
}

function tapped(photo: ManagedPhotoDto): void {
  if (held) {
    held = false

    return
  }

  if (selecting.value) {
    selected.value = toggled(selected.value, { id: photo.id, can_delete: selectable(photo) })

    return
  }

  viewing.value = photo.id
}

const chosen = computed(() => photos.value.filter(photo => selected.value.includes(photo.id)))
const allDeletable = computed(() => chosen.value.every(photo => photo.can_delete))
const allHearted = computed(() => chosen.value.length > 0 && chosen.value.every(photo => photo.favorite))

async function act(job: () => Promise<boolean>): Promise<void> {
  busy.value = true

  if (await job()) {
    selected.value = []
  }

  busy.value = false
}

async function intoAlbum(id: number | null, name?: string): Promise<void> {
  albumPicker.value = false
  const ids = [...selected.value]

  await act(async () => id === null ? (await createAlbum(name ?? '', ids)) !== null : addToAlbum(id, ids))
}

// Two fingers apart zoom in (bigger pictures), together zoom out.
const fingers = new Map<number, { x: number, y: number }>()
let spread: number | null = null

function distance(): number {
  const [a, b] = [...fingers.values()]

  return a && b ? Math.hypot(a.x - b.x, a.y - b.y) : 0
}

function fingerDown(event: PointerEvent): void {
  if (event.pointerType !== 'touch') {
    return
  }

  fingers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (fingers.size === 2) {
    cancelHold()
    spread = distance()
  }
}

function fingerMove(event: PointerEvent): void {
  if (!fingers.has(event.pointerId)) {
    return
  }

  fingers.set(event.pointerId, { x: event.clientX, y: event.clientY })

  if (spread !== null && fingers.size === 2) {
    const now = distance()

    if (Math.abs(now - spread) > 60) {
      zoom.value = zoomed(zoom.value, now > spread ? 1 : -1)
      spread = now
    }
  }
}

function fingerUp(event: PointerEvent): void {
  fingers.delete(event.pointerId)

  if (fingers.size < 2) {
    spread = null
  }
}

// Endless scrolling: the next page comes when the end of the grid shows.
const end = ref<HTMLElement | null>(null)
let observer: IntersectionObserver | null = null

onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    if (entries.some(entry => entry.isIntersecting)) {
      void more()
    }
  }, { rootMargin: '400px' })

  if (end.value) {
    observer.observe(end.value)
  }
})

onBeforeUnmount(() => {
  observer?.disconnect()
  cancelHold()
})

function label(photo: ManagedPhotoDto): string {
  const day = photo.taken_on ? formatIsoDate(photo.taken_on, { day: 'numeric', month: 'long', year: 'numeric' }) : null

  return [photo.caption ?? '', day ?? ''].filter(Boolean).join(' · ') || t('photos.photo')
}
</script>

<template>
  <section aria-labelledby="library-title" class="space-y-3">
    <!-- The bar swaps with the heading while something is selected. -->
    <div
      v-if="selecting"
      data-testid="selection-bar"
      class="sticky top-0 z-10 -mx-4 flex flex-wrap items-center gap-x-1 gap-y-2 bg-surface px-4 py-1.5 shadow-card"
    >
      <button
        type="button"
        data-testid="selection-cancel"
        :aria-label="$t('common.cancel')"
        class="-ml-2 flex size-11 items-center justify-center rounded-full text-ink-2"
        @click="selected = []"
      >
        <TwIcon name="close" />
      </button>
      <p class="mr-auto text-base font-semibold" aria-live="polite">
        {{ $t('photos.library.selected', { n: selected.length }) }}
      </p>
      <button
        type="button"
        data-testid="select-all"
        class="min-h-11 rounded-xl px-2 text-base font-medium text-ink-2"
        @click="selected = trash ? everySelectable(photos) : photos.map(photo => photo.id)"
      >
        {{ $t('photos.library.all') }}
      </button>

      <template v-if="trash">
        <button
          type="button"
          data-testid="selection-restore"
          :disabled="busy"
          class="flex min-h-11 items-center gap-1.5 rounded-xl bg-accent px-3 text-base font-semibold text-accent-ink disabled:opacity-60"
          @click="act(() => change('restore', selected))"
        >
          <TwIcon name="restore" :size="18" />
          {{ $t('photos.library.restore') }}
        </button>
        <button
          type="button"
          data-testid="selection-purge"
          :disabled="busy"
          class="flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-base font-semibold disabled:opacity-60"
          :class="armed === 'purge' ? 'bg-danger text-white' : 'text-danger'"
          @click="tap('purge', () => act(() => change('purge', selected)))"
        >
          <TwIcon name="delete" :size="18" />
          {{ armed === 'purge' ? $t('photos.library.purgeConfirm') : $t('photos.library.purge') }}
        </button>
      </template>
      <template v-else>
        <button
          type="button"
          data-testid="selection-heart"
          :disabled="busy"
          :aria-label="allHearted ? $t('photos.unfavorite') : $t('photos.favorite')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 disabled:opacity-60"
          :class="allHearted ? 'text-danger' : ''"
          @click="act(() => favorite(selected, !allHearted))"
        >
          <TwIcon name="heart" :fill="allHearted ? 'currentColor' : 'none'" />
        </button>
        <button
          v-if="props.view.kind === 'album'"
          type="button"
          data-testid="selection-unalbum"
          :disabled="busy"
          class="min-h-11 rounded-xl px-2 text-base font-semibold text-ink-2 disabled:opacity-60"
          @click="act(() => removeFromAlbum(selected))"
        >
          {{ $t('photos.albums.remove') }}
        </button>
        <button
          v-else
          type="button"
          data-testid="selection-album"
          :disabled="busy"
          :aria-label="$t('photos.albums.add')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 disabled:opacity-60"
          @click="albumPicker = true"
        >
          <TwIcon name="albums" />
        </button>
        <button
          v-if="allDeletable"
          type="button"
          data-testid="selection-delete"
          :disabled="busy"
          class="flex min-h-11 items-center gap-1.5 rounded-xl bg-danger px-3 text-base font-semibold text-white disabled:opacity-60"
          @click="act(() => change('delete', selected))"
        >
          <TwIcon name="delete" :size="18" />
          {{ $t('common.delete') }}
        </button>
      </template>
    </div>

    <div v-else class="flex min-h-11 flex-wrap items-center gap-2">
      <button
        v-if="props.back"
        type="button"
        data-testid="trash-back"
        :aria-label="$t('photos.library.back')"
        class="-ml-2 flex size-11 items-center justify-center rounded-full text-ink-2"
        @click="emit('back')"
      >
        <TwIcon name="back" />
      </button>
      <h2 id="library-title" class="mr-auto font-display text-3xl font-bold tracking-tight">
        {{ props.title }}
      </h2>

      <!-- Like the Photos app: years, months, everything. -->
      <div
        v-if="!trash && photos.length > 0"
        role="group"
        :aria-label="$t('photos.library.zoom')"
        class="flex rounded-xl bg-surface-2 p-0.5 text-sm font-semibold"
      >
        <button
          v-for="step in LIBRARY_ZOOMS"
          :key="step"
          type="button"
          data-testid="library-zoom"
          :data-value="step"
          :aria-pressed="zoom === step"
          class="min-h-9 rounded-[0.6rem] px-3 transition-colors"
          :class="zoom === step ? 'bg-surface text-ink shadow-card' : 'text-ink-2'"
          @click="zoom = step"
        >
          {{ $t(`photos.library.zooms.${step}`) }}
        </button>
      </div>

      <button
        v-if="props.view.kind === 'all'"
        type="button"
        data-testid="photo-trash"
        :aria-label="$t('photos.library.trash')"
        class="flex size-11 items-center justify-center rounded-full text-ink-2"
        @click="emit('trash')"
      >
        <TwIcon name="delete" :size="22" />
      </button>
    </div>

    <PhotoUpload v-if="props.upload" />

    <p class="text-sm text-ink-3">
      {{ trash ? $t('photos.library.trashHint') : $t('photos.library.holdHint') }}
    </p>

    <p v-if="error" role="alert" class="text-base text-danger">
      {{ error }}
    </p>

    <div
      data-testid="photo-grid"
      class="space-y-4"
      @pointerdown="fingerDown"
      @pointermove="fingerMove"
      @pointerup="fingerUp"
      @pointercancel="fingerUp"
    >
      <section v-for="group in groups" :key="group.key" :aria-label="heading(group.key)">
        <h3 class="sticky top-0 z-[5] -mx-1 bg-canvas/90 px-1 py-1.5 text-base font-bold backdrop-blur">
          {{ heading(group.key) }}
        </h3>
        <ul class="-mx-1 grid gap-0.5" :class="COLUMNS[trash ? 'days' : zoom]">
          <li v-for="photo in group.photos" :key="photo.id" class="relative aspect-square">
            <button
              type="button"
              data-testid="photo-tile"
              :data-id="photo.id"
              :aria-label="label(photo)"
              :aria-pressed="selecting ? selected.includes(photo.id) : undefined"
              class="relative block size-full touch-manipulation overflow-hidden bg-surface-2 select-none [-webkit-touch-callout:none]"
              :class="[selecting && !selectable(photo) ? 'opacity-40' : '', zoom === 'years' ? 'rounded-sm' : 'rounded-md']"
              @pointerdown="pressed(photo, $event)"
              @pointermove="moved"
              @pointerup="cancelHold"
              @pointercancel="cancelHold"
              @pointerleave="cancelHold"
              @contextmenu.prevent
              @click="tapped(photo)"
            >
              <img
                :src="photo.thumb"
                alt=""
                loading="lazy"
                draggable="false"
                class="size-full object-cover"
              >
              <span
                v-if="photo.favorite && zoom !== 'years'"
                aria-hidden="true"
                class="absolute bottom-1 left-1 text-white drop-shadow"
              >
                <TwIcon name="heart" :size="14" fill="currentColor" />
              </span>
              <span
                v-if="selected.includes(photo.id)"
                aria-hidden="true"
                class="absolute inset-0 border-4 border-accent bg-accent/15"
              />
              <span
                v-if="selecting && selectable(photo)"
                aria-hidden="true"
                class="absolute top-1.5 left-1.5 flex size-6 items-center justify-center rounded-full border-2"
                :class="selected.includes(photo.id) ? 'border-accent bg-accent text-accent-ink' : 'border-white bg-black/25'"
              >
                <TwIcon v-if="selected.includes(photo.id)" name="check" :size="14" />
              </span>
              <span
                v-if="photo.days_left !== null"
                data-testid="days-left"
                class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 to-transparent px-1.5 pt-4 pb-1 text-left text-xs font-semibold text-white"
              >
                {{ $t('photos.library.daysLeft', photo.days_left) }}
              </span>
            </button>
          </li>
        </ul>
      </section>
    </div>

    <div ref="end" aria-hidden="true" class="h-1" />

    <p v-if="loading" class="text-center text-sm text-ink-3">
      {{ $t('common.loadingShort') }}
    </p>
    <p v-else-if="photos.length === 0 && !error" class="py-6 text-center text-base text-ink-3">
      {{ trash ? $t('photos.library.trashEmpty') : props.view.kind === 'all' ? $t('photos.library.empty') : $t('photos.library.nothingHere') }}
    </p>
    <button
      v-else-if="nextPage !== null"
      type="button"
      class="min-h-11 w-full rounded-xl text-base font-medium text-ink-2"
      @click="more"
    >
      {{ $t('photos.library.more') }}
    </button>

    <PhotoViewer
      v-if="viewing !== null"
      v-model="viewing"
      :library="library"
      :trash="trash"
    />

    <PhotoAlbumPicker
      v-if="albumPicker"
      :albums="albums"
      @close="albumPicker = false"
      @pick="intoAlbum"
    />
  </section>
</template>
