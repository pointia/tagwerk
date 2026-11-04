<script setup lang="ts">
/*
 * What a photo tile on the start page shows: all pictures, the favourites,
 * chosen albums or the memories. Saved with the tile, not the device — two
 * tiles side by side may show "Oma & Opa" and "Urlaub".
 */
interface Props {
  settings: Record<string, unknown>
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  save: [Record<string, unknown>]
}>()

useEscape(() => emit('close'))

const { t } = useI18n()

interface AlbumDto { id: number, name: string }

const albums = ref<AlbumDto[]>([])
const choice = ref<string>(props.settings.memories === true ? 'memories' : typeof props.settings.from === 'string' ? props.settings.from : 'all')
const chosen = ref<number[]>(Array.isArray(props.settings.albums) ? props.settings.albums.filter((id): id is number => typeof id === 'number') : [])

onMounted(async () => {
  try {
    albums.value = (await $fetch<{ albums: AlbumDto[] }>('/api/wall/photos/albums', { baseURL: '/', credentials: 'same-origin' })).albums
  }
  catch {
    // The album choice then stays empty.
  }
})

const OPTIONS = computed(() => [
  { value: 'all', label: t('photos.sources.all') },
  { value: 'favorites', label: t('photos.sources.favorites') },
  { value: 'albums', label: t('photos.sources.albums') },
  { value: 'memories', label: t('photos.styles.memories') },
])

function toggle(id: number): void {
  chosen.value = chosen.value.includes(id) ? chosen.value.filter(entry => entry !== id) : [...chosen.value, id]
}

function save(): void {
  emit('save', choice.value === 'memories'
    ? { memories: true }
    : { from: choice.value, albums: choice.value === 'albums' ? chosen.value : [] })
}
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="photo-tile-title"
      data-testid="photo-tile-source"
      class="w-full max-w-lg rounded-3xl bg-surface p-6 shadow-floating"
    >
      <h2 id="photo-tile-title" class="font-display text-2xl font-semibold">
        {{ $t('dashboard.photoSource') }}
      </h2>

      <ChoiceChips
        v-model="choice"
        class="mt-4"
        :options="OPTIONS"
        labelled-by="photo-tile-title"
        test-id="photo-tile-choice"
      />

      <div v-if="choice === 'albums'" class="mt-4 flex flex-wrap gap-1.5" role="group" :aria-label="$t('photos.sources.albums')">
        <p v-if="albums.length === 0" class="text-sm text-ink-2">
          {{ $t('photos.settings.noAlbums') }}
        </p>
        <button
          v-for="album in albums"
          :key="album.id"
          type="button"
          data-testid="photo-tile-album"
          :aria-pressed="chosen.includes(album.id)"
          class="min-h-11 rounded-xl px-4 text-base font-semibold transition-colors"
          :class="chosen.includes(album.id) ? 'bg-accent-soft text-accent' : 'bg-surface-2 text-ink-2'"
          @click="toggle(album.id)"
        >
          {{ album.name }}
        </button>
      </div>

      <div class="mt-6 flex justify-end gap-2">
        <button type="button" class="min-h-11 rounded-xl px-4 text-base font-semibold text-ink-2" @click="emit('close')">
          {{ $t('common.cancel') }}
        </button>
        <button
          type="button"
          data-testid="photo-tile-save"
          class="min-h-11 rounded-xl bg-accent px-5 text-base font-semibold text-accent-ink"
          @click="save"
        >
          {{ $t('common.save') }}
        </button>
      </div>
    </div>
  </div>
</template>
