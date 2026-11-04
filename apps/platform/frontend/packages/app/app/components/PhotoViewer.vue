<script setup lang="ts">
import type { usePhotoLibrary } from '~/composables/usePhotoLibrary'
import { wrapped } from '~/utils/photoFrame'
import { formatIsoDate } from '../../../shared/app/utils/calendar'

/*
 * One photo, large, like a photo app's detail: swipe or the arrow keys to
 * the next, below the actions — heart, album, show it on the wall, delete —
 * and the caption, which a tap turns into a field.
 */
interface Props {
  library: ReturnType<typeof usePhotoLibrary>
  trash: boolean
}

const props: Props = defineProps<Props>()
const viewing = defineModel<number | null>({ required: true })

const { t } = useI18n()
const { photos, albums, favorite, caption, cast, change, addToAlbum, createAlbum } = props.library

const index = computed(() => photos.value.findIndex(photo => photo.id === viewing.value))
const shown = computed(() => photos.value[index.value] ?? null)

// Gone from the list (deleted): the viewer closes with it.
watch(shown, (photo) => {
  if (photo === null) {
    viewing.value = null
  }
})

function step(by: number): void {
  editing.value = false
  viewing.value = photos.value[wrapped(index.value, by, photos.value.length)]?.id ?? null
}

function close(): void {
  viewing.value = null
}

useEscape(close)

function key(event: KeyboardEvent): void {
  if (editing.value) {
    return
  }

  if (event.key === 'ArrowRight') {
    step(1)
  }
  else if (event.key === 'ArrowLeft') {
    step(-1)
  }
}

onMounted(() => window.addEventListener('keydown', key))
onBeforeUnmount(() => window.removeEventListener('keydown', key))

let startX: number | null = null

function down(event: PointerEvent): void {
  startX = event.clientX
}

function up(event: PointerEvent): void {
  if (startX !== null && Math.abs(event.clientX - startX) > 60) {
    step(event.clientX < startX ? 1 : -1)
  }

  startX = null
}

const day = computed(() => shown.value?.taken_on
  ? formatIsoDate(shown.value.taken_on, { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
  : null)

// The caption, typed in place.
const editing = ref(false)
const draft = ref('')

function edit(): void {
  if (!shown.value?.can_delete) {
    return
  }

  draft.value = shown.value.caption ?? ''
  editing.value = true
}

async function saveCaption(): Promise<void> {
  if (shown.value && await caption(shown.value.id, draft.value)) {
    editing.value = false
  }
}

const { busy, run } = useBusy()
const notice = ref<string | null>(null)

function showOnWall(): void {
  const photo = shown.value

  if (photo) {
    void run(async () => {
      notice.value = await cast(photo.id) ? t('photos.cast.sent') : null
      setTimeout(() => {
        notice.value = null
      }, 4_000)
    })
  }
}

const albumPicker = ref(false)

async function intoAlbum(id: number | null, name?: string): Promise<void> {
  albumPicker.value = false
  const photo = shown.value

  if (photo) {
    const done = id === null ? (await createAlbum(name ?? '', [photo.id])) !== null : await addToAlbum(id, [photo.id])
    notice.value = done ? t('photos.albums.added') : null
  }
}

const { armed, tap } = useTwoStep()
</script>

<template>
  <div
    v-if="shown"
    role="dialog"
    aria-modal="true"
    :aria-label="shown.caption ?? day ?? $t('photos.photo')"
    data-testid="photo-viewer"
    class="fixed inset-0 z-50 flex flex-col bg-black text-white"
  >
    <div class="flex shrink-0 items-center gap-2 px-2 pt-[calc(env(safe-area-inset-top)+0.5rem)] pb-1">
      <button
        type="button"
        :aria-label="$t('common.close')"
        class="flex size-12 items-center justify-center rounded-full hover:bg-white/10"
        @click="close"
      >
        <TwIcon name="close" />
      </button>
      <p class="min-w-0 flex-1 truncate text-center text-sm text-white/75">
        {{ day }}
      </p>
      <span class="w-12 text-right text-sm text-white/60 tabular-nums">{{ index + 1 }}/{{ photos.length }}</span>
    </div>

    <div class="relative min-h-0 flex-1 touch-pan-y" @pointerdown="down" @pointerup="up">
      <Transition name="viewer" mode="out-in">
        <img :key="shown.id" :src="shown.url" :alt="shown.caption ?? $t('photos.photo')" draggable="false" class="size-full object-contain select-none">
      </Transition>
      <button
        type="button"
        :aria-label="$t('photos.previous')"
        class="absolute inset-y-0 left-0 hidden w-16 items-center justify-center opacity-0 transition-opacity hover:opacity-100 sm:flex"
        @click="step(-1)"
      >
        <TwIcon name="back" :size="32" />
      </button>
      <button
        type="button"
        :aria-label="$t('photos.next')"
        class="absolute inset-y-0 right-0 hidden w-16 items-center justify-center opacity-0 transition-opacity hover:opacity-100 sm:flex"
        @click="step(1)"
      >
        <TwIcon name="forward" :size="32" />
      </button>

      <p
        v-if="notice"
        role="status"
        data-testid="viewer-notice"
        class="absolute top-4 left-1/2 -translate-x-1/2 rounded-full bg-white/15 px-4 py-2 text-base font-medium backdrop-blur-md"
      >
        {{ notice }}
      </p>
    </div>

    <div class="shrink-0 px-4 pt-2">
      <form v-if="editing" class="flex gap-2" @submit.prevent="saveCaption">
        <input
          v-model="draft"
          data-testid="caption-input"
          maxlength="255"
          :aria-label="$t('photos.captionLabel')"
          :placeholder="$t('photos.captionLabel')"
          class="min-h-11 min-w-0 flex-1 rounded-xl bg-white/15 px-3 text-base text-white placeholder:text-white/50"
          autofocus
        >
        <button type="submit" data-testid="caption-save" class="min-h-11 rounded-xl bg-white px-4 text-base font-semibold text-black">
          {{ $t('common.save') }}
        </button>
      </form>
      <button
        v-else
        type="button"
        data-testid="caption"
        class="min-h-11 w-full text-center text-base"
        :class="shown.caption ? 'text-white' : 'text-white/50'"
        :disabled="!shown.can_delete || props.trash"
        @click="edit"
      >
        {{ shown.caption ?? (shown.can_delete && !props.trash ? $t('photos.addCaption') : '') }}
      </button>
    </div>

    <div v-if="!props.trash" class="flex shrink-0 justify-around px-2 pt-1 pb-[calc(env(safe-area-inset-bottom)+0.75rem)]">
      <button
        type="button"
        data-testid="viewer-heart"
        :aria-pressed="shown.favorite"
        class="flex min-h-14 min-w-16 flex-col items-center justify-center gap-0.5 rounded-xl text-xs"
        :class="shown.favorite ? 'text-danger' : ''"
        @click="favorite([shown.id], !shown.favorite)"
      >
        <TwIcon name="heart" :fill="shown.favorite ? 'currentColor' : 'none'" />
        {{ shown.favorite ? $t('photos.unfavorite') : $t('photos.favorite') }}
      </button>
      <button
        type="button"
        data-testid="viewer-cast"
        :disabled="busy"
        class="flex min-h-14 min-w-16 flex-col items-center justify-center gap-0.5 rounded-xl text-xs disabled:opacity-60"
        @click="showOnWall"
      >
        <TwIcon name="cast" />
        {{ $t('photos.cast.action') }}
      </button>
      <button
        type="button"
        data-testid="viewer-album"
        class="flex min-h-14 min-w-16 flex-col items-center justify-center gap-0.5 rounded-xl text-xs"
        @click="albumPicker = true"
      >
        <TwIcon name="albums" />
        {{ $t('photos.albums.add') }}
      </button>
      <button
        v-if="shown.can_delete"
        type="button"
        data-testid="viewer-delete"
        class="flex min-h-14 min-w-16 flex-col items-center justify-center gap-0.5 rounded-xl text-xs"
        :class="armed === 'delete' ? 'text-danger' : ''"
        @click="tap('delete', () => change('delete', [shown!.id]))"
      >
        <TwIcon name="delete" />
        {{ armed === 'delete' ? $t('photos.library.deleteConfirm') : $t('common.delete') }}
      </button>
    </div>
    <div v-else class="pb-[calc(env(safe-area-inset-bottom)+0.75rem)]" />

    <PhotoAlbumPicker
      v-if="albumPicker"
      :albums="albums"
      @close="albumPicker = false"
      @pick="intoAlbum"
    />
  </div>
</template>

<style scoped>
.viewer-enter-active,
.viewer-leave-active {
  transition: opacity 0.18s ease;
}

.viewer-enter-from,
.viewer-leave-to {
  opacity: 0;
}
</style>
