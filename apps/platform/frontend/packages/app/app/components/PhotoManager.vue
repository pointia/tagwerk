<script setup lang="ts">
import type { LibraryView } from '~/composables/usePhotoLibrary'
import { dayRange } from '~/utils/photoFrame'
import { intlLocale } from '../../../shared/app/utils/intlLocale'

/*
 * The household's pictures, laid out like a photo app: the library, "Für
 * euch" with the memories, and the collections — favourites, albums,
 * trash. Where the width allows a sidebar lists them (like Photos on an
 * iPad); on a phone they are tabs along the bottom.
 */
type Tab = 'library' | 'memories' | 'collections'

const { t } = useI18n()
const narrow = useNarrow()
const { albums, loadAlbums, renameAlbum, deleteAlbum } = usePhotoLibrary()
const { memories, loaded: memoriesLoaded, load: loadMemories } = usePhotoMemories()

const tab = ref<Tab>('library')
const view = ref<LibraryView>({ kind: 'all' })

onMounted(() => {
  void loadAlbums()

  if (!memoriesLoaded.value) {
    void loadMemories()
  }
})

function open(next: LibraryView): void {
  view.value = next
  tab.value = next.kind === 'all' ? 'library' : 'collections'
}

const title = computed(() => {
  switch (view.value.kind) {
    case 'favorites':
      return t('photos.sources.favorites')
    case 'trash':
      return t('photos.library.trash')
    case 'album': {
      const id = view.value.id

      return albums.value.find(album => album.id === id)?.name ?? ''
    }
    default:
      return t('photos.library.title')
  }
})

/* The grid shows on the library tab, and in collections once one is open. */
const showsGrid = computed(() => tab.value === 'library' || (tab.value === 'collections' && view.value.kind !== 'all'))

const album = computed(() => view.value.kind === 'album' ? albums.value.find(entry => entry.id === (view.value as { id: number }).id) ?? null : null)

const renaming = ref(false)
const albumName = ref('')
const { armed, tap } = useTwoStep()

function startRename(): void {
  albumName.value = album.value?.name ?? ''
  renaming.value = true
}

async function saveRename(): Promise<void> {
  if (album.value && albumName.value.trim() !== '' && await renameAlbum(album.value.id, albumName.value.trim())) {
    renaming.value = false
  }
}

async function removeAlbum(): Promise<void> {
  if (album.value && await deleteAlbum(album.value.id)) {
    open({ kind: 'all' })
    tab.value = 'collections'
  }
}

/* The trash opens from the library and leads back there; the rest from the collections. */
function goBack(): void {
  const from = view.value.kind
  open({ kind: 'all' })
  tab.value = from === 'trash' ? 'library' : 'collections'
}

function memoryTitle(from: string, until: string): string {
  return dayRange(from, until, intlLocale.value)
}

/* A memory opens as a slideshow over everything, like the wall's frame. */
const playing = ref<string | null>(null)
useEscape(() => {
  playing.value = null
}, () => playing.value !== null)

const TABS = computed((): { value: Tab, icon: 'photo' | 'memories' | 'albums', label: string }[] => [
  { value: 'library', icon: 'photo', label: t('photos.library.title') },
  { value: 'memories', icon: 'memories', label: t('photos.forYou') },
  { value: 'collections', icon: 'albums', label: t('photos.collections') },
])
</script>

<template>
  <div class="flex min-h-full" :class="narrow ? 'flex-col' : ''">
    <!-- Sidebar where it fits. -->
    <nav
      v-if="!narrow"
      :aria-label="$t('photos.collections')"
      data-testid="photo-sidebar"
      class="sticky top-0 flex max-h-full w-60 shrink-0 flex-col gap-0.5 self-start border-r border-line px-3 py-4 text-base"
    >
      <button
        v-for="entry in [
          { view: { kind: 'all' } as LibraryView, icon: 'photo' as const, label: $t('photos.library.title') },
          { view: { kind: 'favorites' } as LibraryView, icon: 'heart' as const, label: $t('photos.sources.favorites') },
        ]"
        :key="entry.view.kind"
        type="button"
        data-testid="photo-collection"
        :data-value="entry.view.kind"
        :aria-current="tab !== 'memories' && view.kind === entry.view.kind ? 'page' : undefined"
        class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-left font-medium"
        :class="tab !== 'memories' && view.kind === entry.view.kind ? 'bg-accent text-accent-ink' : 'hover:bg-surface-2'"
        @click="open(entry.view)"
      >
        <TwIcon :name="entry.icon" :size="20" />
        {{ entry.label }}
      </button>
      <button
        type="button"
        data-testid="photo-collection"
        data-value="memories"
        :aria-current="tab === 'memories' ? 'page' : undefined"
        class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-left font-medium"
        :class="tab === 'memories' ? 'bg-accent text-accent-ink' : 'hover:bg-surface-2'"
        @click="tab = 'memories'"
      >
        <TwIcon name="memories" :size="20" />
        {{ $t('photos.forYou') }}
      </button>

      <h3 class="mt-5 mb-1 px-3 text-xs font-bold tracking-wide text-ink-3 uppercase">
        {{ $t('photos.sources.albums') }}
      </h3>
      <button
        v-for="entry in albums"
        :key="entry.id"
        type="button"
        data-testid="photo-collection"
        data-value="album"
        :aria-current="tab !== 'memories' && view.kind === 'album' && view.id === entry.id ? 'page' : undefined"
        class="flex min-h-11 items-center gap-3 rounded-xl px-3 text-left font-medium"
        :class="tab !== 'memories' && view.kind === 'album' && view.id === entry.id ? 'bg-accent text-accent-ink' : 'hover:bg-surface-2'"
        @click="open({ kind: 'album', id: entry.id })"
      >
        <span class="size-6 shrink-0 overflow-hidden rounded-md bg-surface-3">
          <img v-if="entry.cover" :src="entry.cover" alt="" class="size-full object-cover">
        </span>
        <span class="min-w-0 flex-1 truncate">{{ entry.name }}</span>
      </button>
      <p v-if="albums.length === 0" class="px-3 text-sm text-ink-3">
        {{ $t('photos.albums.none') }}
      </p>

      <button
        type="button"
        data-testid="photo-collection"
        data-value="trash"
        :aria-current="tab !== 'memories' && view.kind === 'trash' ? 'page' : undefined"
        class="mt-5 flex min-h-11 items-center gap-3 rounded-xl px-3 text-left font-medium"
        :class="tab !== 'memories' && view.kind === 'trash' ? 'bg-accent text-accent-ink' : 'hover:bg-surface-2'"
        @click="open({ kind: 'trash' })"
      >
        <TwIcon name="delete" :size="20" />
        {{ $t('photos.library.trash') }}
      </button>
    </nav>

    <div class="min-w-0 flex-1 px-4 pt-2 pb-28 sm:px-6">
      <!-- Für euch: memories as large cards. -->
      <section v-if="tab === 'memories'" aria-labelledby="memories-title" data-testid="photo-memories" class="space-y-4">
        <h2 id="memories-title" class="font-display text-3xl font-bold tracking-tight">
          {{ $t('photos.forYou') }}
        </h2>
        <p v-if="memoriesLoaded && memories.length === 0" class="text-base text-ink-2">
          {{ $t('photos.memories.none') }}
        </p>
        <ul class="grid gap-3 sm:grid-cols-2">
          <li v-for="memory in memories" :key="memory.key">
            <button
              type="button"
              data-testid="memory-card"
              class="relative block aspect-[4/3] w-full overflow-hidden rounded-3xl bg-surface-3 text-left text-white shadow-card"
              @click="playing = memory.key"
            >
              <img v-if="memory.photos[0]" :src="memory.photos[0].url" alt="" loading="lazy" class="absolute inset-0 size-full object-cover">
              <span class="absolute inset-x-0 bottom-0 bg-linear-to-t from-black/75 via-black/30 to-transparent px-5 pt-16 pb-4">
                <span class="block text-xs font-semibold tracking-[0.18em] text-white/85 uppercase">
                  {{ memory.kind === 'on_this_day' ? $t('photos.memories.yearsAgo', memory.years ?? 1) : $t('photos.memories.days') }}
                </span>
                <span class="mt-1 block font-display text-2xl font-bold">{{ memoryTitle(memory.from, memory.until) }}</span>
                <span class="block text-sm text-white/80">{{ $t('photos.memories.count', memory.count) }}</span>
              </span>
            </button>
          </li>
        </ul>
      </section>

      <!-- Sammlungen on a phone: what the sidebar lists elsewhere. -->
      <section v-else-if="tab === 'collections' && !showsGrid" aria-labelledby="collections-title" class="space-y-5">
        <h2 id="collections-title" class="font-display text-3xl font-bold tracking-tight">
          {{ $t('photos.collections') }}
        </h2>

        <div>
          <h3 class="mb-2 text-lg font-bold">{{ $t('photos.sources.albums') }}</h3>
          <p v-if="albums.length === 0" class="text-base text-ink-2">{{ $t('photos.albums.none') }}</p>
          <ul class="grid grid-cols-2 gap-3 sm:grid-cols-3">
            <li v-for="entry in albums" :key="entry.id">
              <button type="button" data-testid="album-card" class="block w-full text-left" @click="open({ kind: 'album', id: entry.id })">
                <span class="block aspect-square overflow-hidden rounded-2xl bg-surface-3">
                  <img v-if="entry.cover" :src="entry.cover" alt="" loading="lazy" class="size-full object-cover">
                </span>
                <span class="mt-1.5 block truncate text-base font-semibold">{{ entry.name }}</span>
                <span class="block text-sm text-ink-2">{{ $t('photos.library.count', entry.count) }}</span>
              </button>
            </li>
          </ul>
        </div>

        <ul class="divide-y divide-line overflow-hidden rounded-3xl bg-surface shadow-card">
          <li>
            <button type="button" data-testid="photo-collection" data-value="favorites" class="flex min-h-14 w-full items-center gap-4 px-4 text-left text-lg font-semibold" @click="open({ kind: 'favorites' })">
              <TwIcon name="heart" :size="22" class="text-danger" />
              <span class="flex-1">{{ $t('photos.sources.favorites') }}</span>
              <TwIcon name="forward" :size="20" class="text-ink-3" />
            </button>
          </li>
          <li>
            <button type="button" data-testid="photo-collection" data-value="trash" class="flex min-h-14 w-full items-center gap-4 px-4 text-left text-lg font-semibold" @click="open({ kind: 'trash' })">
              <TwIcon name="delete" :size="22" class="text-ink-2" />
              <span class="flex-1">{{ $t('photos.library.trash') }}</span>
              <TwIcon name="forward" :size="20" class="text-ink-3" />
            </button>
          </li>
        </ul>
      </section>

      <template v-else>
        <!-- An album's own actions: rename, delete (the photos stay). -->
        <div v-if="album" class="mb-2 flex flex-wrap items-center justify-end gap-2">
          <form v-if="renaming" class="flex flex-1 gap-2" @submit.prevent="saveRename">
            <input
              v-model="albumName"
              data-testid="album-rename-input"
              maxlength="80"
              :aria-label="$t('photos.albums.newName')"
              class="min-h-11 min-w-0 flex-1 rounded-xl border border-line bg-surface px-3 text-base"
            >
            <button type="submit" class="min-h-11 rounded-xl bg-accent px-4 text-base font-semibold text-accent-ink">
              {{ $t('common.save') }}
            </button>
          </form>
          <template v-else>
            <button type="button" data-testid="album-rename" class="flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-base font-semibold text-ink-2 hover:bg-surface-2" @click="startRename">
              <TwIcon name="pencil" :size="18" />
              {{ $t('photos.albums.rename') }}
            </button>
            <button
              type="button"
              data-testid="album-delete"
              class="flex min-h-11 items-center gap-1.5 rounded-xl px-3 text-base font-semibold"
              :class="armed === 'album' ? 'bg-danger text-white' : 'text-danger hover:bg-surface-2'"
              @click="tap('album', removeAlbum)"
            >
              <TwIcon name="delete" :size="18" />
              {{ armed === 'album' ? $t('photos.albums.deleteConfirm') : $t('photos.albums.delete') }}
            </button>
          </template>
        </div>

        <PhotoLibrary
          :view="view"
          :title="title"
          :back="view.kind !== 'all' && narrow"
          :upload="view.kind === 'all'"
          @back="goBack"
          @trash="open({ kind: 'trash' })"
        />
      </template>
    </div>

    <!-- Tabs along the bottom on a phone, like the Photos app. -->
    <nav
      v-if="narrow"
      :aria-label="$t('photos.collections')"
      class="fixed inset-x-0 bottom-0 z-20 flex justify-around border-t border-line bg-surface/90 pt-1.5 pb-[calc(env(safe-area-inset-bottom)+0.5rem)] backdrop-blur-md"
    >
      <button
        v-for="entry in TABS"
        :key="entry.value"
        type="button"
        data-testid="photo-tab"
        :data-value="entry.value"
        :aria-current="tab === entry.value ? 'page' : undefined"
        class="flex min-h-12 min-w-20 flex-col items-center justify-center gap-0.5 text-xs font-semibold"
        :class="tab === entry.value ? 'text-accent' : 'text-ink-3'"
        @click="tab = entry.value; if (entry.value === 'library') view = { kind: 'all' }"
      >
        <TwIcon :name="entry.icon" :size="22" />
        {{ entry.label }}
      </button>
    </nav>

    <!-- A memory as a slideshow, the frame's own story layout. -->
    <div
      v-if="playing"
      role="dialog"
      aria-modal="true"
      :aria-label="$t('photos.forYou')"
      data-testid="memory-player"
      class="fixed inset-0 z-50 bg-black"
    >
      <PhotoFrame
        layout="memories"
        :start-memory="playing"
        :query="{ from: 'all', albums: [] }"
        :interval-ms="5000"
        fit="fit"
        motion
        caption
        interactive
        bleed
      />
      <button
        type="button"
        :aria-label="$t('common.close')"
        class="absolute top-[calc(env(safe-area-inset-top)+1rem)] right-4 flex size-11 items-center justify-center rounded-full bg-black/45 text-white backdrop-blur"
        @click="playing = null"
      >
        <TwIcon name="close" />
      </button>
    </div>
  </div>
</template>
