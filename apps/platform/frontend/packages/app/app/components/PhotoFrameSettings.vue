<script setup lang="ts">
import type { Preferences } from '../../../shared/app/composables/usePreferences'
import { PHOTO_STYLES, type PhotoStyle } from '~/utils/photoFrame'

/*
 * How this device shows the household's pictures. Every choice applies at
 * once, like the other settings; the layouts are drawn as small sketches,
 * because "Mosaik" says less than a glance at one.
 */
interface Props {
  preferences: Preferences
  /** Only a tablet rests, so only a tablet picks a resting layout. */
  device: boolean
}

const props: Props = defineProps<Props>()

type Choice = 'photo_style' | 'photo_rest_style' | 'photo_source' | 'photo_interval' | 'photo_fit' | 'photo_motion' | 'photo_caption' | 'photo_clock'

const emit = defineEmits<{
  set: [Choice, string]
  albums: [number[]]
}>()

const { t } = useI18n()

const STYLE_LABELS = computed((): Record<PhotoStyle, { label: string, hint: string }> => ({
  single: { label: t('photos.styles.single'), hint: t('photos.styles.singleHint') },
  duo: { label: t('photos.styles.duo'), hint: t('photos.styles.duoHint') },
  mosaic: { label: t('photos.styles.mosaic'), hint: t('photos.styles.mosaicHint') },
  ambient: { label: t('photos.styles.ambient'), hint: t('photos.styles.ambientHint') },
  memories: { label: t('photos.styles.memories'), hint: t('photos.styles.memoriesHint') },
}))

const SOURCES = computed(() => [
  { value: 'all', label: t('photos.sources.all') },
  { value: 'favorites', label: t('photos.sources.favorites') },
  { value: 'albums', label: t('photos.sources.albums') },
])

const INTERVALS = computed(() => [
  { value: '10', label: t('device.seconds', { n: 10 }) },
  { value: '30', label: t('device.seconds', { n: 30 }) },
  { value: '60', label: t('device.minutes', { n: 1 }) },
  { value: '300', label: t('device.minutes', { n: 5 }) },
])

const FITS = computed(() => [
  { value: 'fit', label: t('photos.fit.fit') },
  { value: 'fill', label: t('photos.fit.fill') },
])

const TOGGLES = computed((): { key: Choice, on: string, label: string }[] => [
  { key: 'photo_motion', on: 'on', label: t('photos.settings.motion') },
  { key: 'photo_caption', on: 'on', label: t('photos.settings.caption') },
  { key: 'photo_clock', on: 'on', label: t('photos.settings.clock') },
])

interface AlbumDto { id: number, name: string, count: number, cover: string | null }

const albums = ref<AlbumDto[]>([])

onMounted(async () => {
  try {
    albums.value = (await $fetch<{ albums: AlbumDto[] }>('/api/wall/photos/albums', { baseURL: '/', credentials: 'same-origin' })).albums
  }
  catch {
    // Without the list the album choice stays empty; everything else works.
  }
})

function toggleAlbum(id: number): void {
  const chosen = props.preferences.photo_albums

  emit('albums', chosen.includes(id) ? chosen.filter(entry => entry !== id) : [...chosen, id])
}
</script>

<template>
  <div class="space-y-4">
    <section class="rounded-3xl bg-surface p-4 shadow-card" aria-labelledby="photo-style">
      <h3 id="photo-style" class="mb-3 text-lg font-semibold">{{ $t('photos.settings.layout') }}</h3>

      <div role="group" aria-labelledby="photo-style" class="grid grid-cols-2 gap-2 sm:grid-cols-3">
        <button
          v-for="style in PHOTO_STYLES"
          :key="style"
          type="button"
          data-testid="preference"
          data-cell="photo_style"
          :data-value="style"
          :aria-pressed="preferences.photo_style === style"
          class="rounded-2xl p-2 text-left transition-colors"
          :class="preferences.photo_style === style ? 'bg-accent-soft ring-2 ring-accent' : 'bg-surface-2 hover:bg-surface-3'"
          @click="emit('set', 'photo_style', style)"
        >
          <PhotoStyleSketch :style-key="style" />
          <span class="mt-2 block text-base font-semibold" :class="preferences.photo_style === style ? 'text-accent' : ''">
            {{ STYLE_LABELS[style].label }}
          </span>
          <span class="block text-sm leading-snug text-ink-2">{{ STYLE_LABELS[style].hint }}</span>
        </button>
      </div>
    </section>

    <section v-if="props.device" class="rounded-3xl bg-surface p-4 shadow-card" aria-labelledby="photo-rest-style">
      <h3 id="photo-rest-style" class="text-lg font-semibold">{{ $t('photos.settings.restLayout') }}</h3>
      <p class="mt-0.5 mb-3 text-sm text-ink-2">{{ $t('photos.settings.restHint') }}</p>
      <ChoiceChips
        :model-value="preferences.photo_rest_style"
        :options="[{ value: 'same', label: $t('photos.settings.same') }, ...PHOTO_STYLES.map(style => ({ value: style, label: STYLE_LABELS[style].label }))]"
        labelled-by="photo-rest-style"
        test-id="preference"
        cell="photo_rest_style"
        @update:model-value="emit('set', 'photo_rest_style', $event)"
      />
    </section>

    <section class="rounded-3xl bg-surface p-4 shadow-card" aria-labelledby="photo-source">
      <h3 id="photo-source" class="mb-3 text-lg font-semibold">{{ $t('photos.settings.source') }}</h3>
      <ChoiceChips
        :model-value="preferences.photo_source"
        :options="SOURCES"
        labelled-by="photo-source"
        test-id="preference"
        cell="photo_source"
        @update:model-value="emit('set', 'photo_source', $event)"
      />

      <template v-if="preferences.photo_source === 'albums'">
        <p v-if="albums.length === 0" class="mt-3 text-sm text-ink-2">
          {{ $t('photos.settings.noAlbums') }}
        </p>
        <ul v-else class="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3" :aria-label="$t('photos.sources.albums')">
          <li v-for="album in albums" :key="album.id">
            <button
              type="button"
              data-testid="photo-album-choice"
              :data-value="album.id"
              :aria-pressed="preferences.photo_albums.includes(album.id)"
              class="relative block aspect-[4/3] w-full overflow-hidden rounded-2xl bg-surface-3 text-left text-white"
              :class="preferences.photo_albums.includes(album.id) ? 'ring-4 ring-accent' : ''"
              @click="toggleAlbum(album.id)"
            >
              <img v-if="album.cover" :src="album.cover" alt="" class="absolute inset-0 size-full object-cover">
              <span class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/70 to-transparent px-2.5 pt-6 pb-2">
                <span class="block truncate text-base font-semibold">{{ album.name }}</span>
                <span class="block text-xs text-white/80">{{ $t('photos.library.count', album.count) }}</span>
              </span>
              <span
                v-if="preferences.photo_albums.includes(album.id)"
                class="absolute top-2 right-2 flex size-7 items-center justify-center rounded-full bg-accent text-accent-ink"
                aria-hidden="true"
              >
                <TwIcon name="check" :size="16" />
              </span>
            </button>
          </li>
        </ul>
      </template>
    </section>

    <section class="rounded-3xl bg-surface p-4 shadow-card" aria-labelledby="photo-interval">
      <h3 id="photo-interval" class="mb-3 text-lg font-semibold">{{ $t('photos.settings.interval') }}</h3>
      <ChoiceChips
        :model-value="preferences.photo_interval"
        :options="INTERVALS"
        labelled-by="photo-interval"
        test-id="preference"
        cell="photo_interval"
        @update:model-value="emit('set', 'photo_interval', $event)"
      />

      <h3 id="photo-fit" class="mt-5 mb-3 text-lg font-semibold">{{ $t('photos.settings.fit') }}</h3>
      <ChoiceChips
        :model-value="preferences.photo_fit"
        :options="FITS"
        labelled-by="photo-fit"
        test-id="preference"
        cell="photo_fit"
        @update:model-value="emit('set', 'photo_fit', $event)"
      />
    </section>

    <ul class="divide-y divide-line overflow-hidden rounded-3xl bg-surface shadow-card">
      <li v-for="toggle in TOGGLES" :key="toggle.key">
        <button
          type="button"
          role="switch"
          data-testid="preference"
          :data-cell="toggle.key"
          :aria-checked="preferences[toggle.key] === 'on'"
          class="flex min-h-14 w-full items-center gap-4 px-4 py-3 text-left text-lg font-semibold"
          @click="emit('set', toggle.key, preferences[toggle.key] === 'on' ? 'off' : 'on')"
        >
          <span class="flex-1">{{ toggle.label }}</span>
          <SwitchKnob :on="preferences[toggle.key] === 'on'" />
        </button>
      </li>
    </ul>
  </div>
</template>
