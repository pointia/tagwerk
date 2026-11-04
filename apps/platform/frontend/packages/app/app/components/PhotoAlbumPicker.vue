<script setup lang="ts">
import type { AlbumDto } from '~/composables/usePhotoLibrary'

/* Into which album: an existing one, or a new one named right here. */
interface Props {
  albums: AlbumDto[]
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{
  close: []
  /** An album's id, or null with the name of a new one. */
  pick: [number | null, string?]
}>()

useEscape(() => emit('close'))

const name = ref('')

function create(): void {
  if (name.value.trim() !== '') {
    emit('pick', null, name.value.trim())
  }
}
</script>

<template>
  <div class="fixed inset-0 z-[60] flex items-end justify-center bg-scrim sm:items-center sm:p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="album-picker-title"
      data-testid="album-picker"
      class="max-h-[80vh] w-full max-w-md overflow-y-auto rounded-t-3xl bg-surface p-5 text-ink shadow-floating sm:rounded-3xl"
    >
      <h2 id="album-picker-title" class="font-display text-2xl font-semibold">
        {{ $t('photos.albums.add') }}
      </h2>

      <ul v-if="props.albums.length > 0" class="mt-4 divide-y divide-line">
        <li v-for="album in props.albums" :key="album.id">
          <button
            type="button"
            data-testid="album-pick"
            class="flex min-h-14 w-full items-center gap-3 text-left"
            @click="emit('pick', album.id)"
          >
            <span class="size-11 shrink-0 overflow-hidden rounded-lg bg-surface-2">
              <img v-if="album.cover" :src="album.cover" alt="" class="size-full object-cover">
            </span>
            <span class="min-w-0 flex-1">
              <span class="block truncate text-lg font-semibold">{{ album.name }}</span>
              <span class="block text-sm text-ink-2">{{ $t('photos.library.count', album.count) }}</span>
            </span>
          </button>
        </li>
      </ul>

      <form class="mt-4 flex gap-2" @submit.prevent="create">
        <input
          v-model="name"
          data-testid="album-name"
          maxlength="80"
          :aria-label="$t('photos.albums.newName')"
          :placeholder="$t('photos.albums.newName')"
          class="min-h-11 min-w-0 flex-1 rounded-xl border border-line bg-surface px-3 text-base"
        >
        <button
          type="submit"
          data-testid="album-create"
          :disabled="name.trim() === ''"
          class="min-h-11 rounded-xl bg-accent px-4 text-base font-semibold text-accent-ink disabled:opacity-50"
        >
          {{ $t('photos.albums.create') }}
        </button>
      </form>
    </div>
  </div>
</template>
