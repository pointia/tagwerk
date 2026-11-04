<script setup lang="ts">
/*
 * Pictures for the frame at the wall, from the phone's gallery. A PWA
 * cannot look into the gallery on its own — somebody picks, and choosing the
 * same folder again later only sends what is new.
 */
const { items, running, summary, add, retry, clear } = usePhotoUpload()

const pictures = ref<HTMLInputElement | null>(null)
const folder = ref<HTMLInputElement | null>(null)
const skipped = ref(0)

/*
 * Choosing a folder works in Chrome on Android; iOS offers the attribute
 * but only opens the photo picker, so the button stays away there.
 */
const folders = ref(false)

onMounted(() => {
  const apple = /iPad|iPhone|iPod/.test(navigator.userAgent)
    || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1)

  folders.value = !apple && 'webkitdirectory' in HTMLInputElement.prototype

  if (folder.value) {
    folder.value.webkitdirectory = true
  }
})

function picked(event: Event): void {
  const input = event.target as HTMLInputElement
  const chosen = input.files?.length ?? 0

  skipped.value = chosen - add(input.files ?? [])
  // Emptied, so the same selection can be picked again.
  input.value = ''
}

const progress = computed(() => summary.value.total === 0
  ? 0
  : Math.round(100 * (summary.value.total - summary.value.open) / summary.value.total))

const failed = computed(() => items.value.filter(item => item.state === 'failed'))
</script>

<template>
  <div class="space-y-4">
    <p class="text-base text-ink-2">
      {{ $t('photos.uploadIntro') }}
    </p>

    <div class="grid gap-2" :class="folders ? 'grid-cols-2' : 'grid-cols-1'">
      <button
        type="button"
        data-testid="pick-photos"
        class="flex min-h-14 items-center justify-center gap-2 rounded-2xl bg-accent px-4 text-lg font-semibold text-accent-ink"
        @click="pictures?.click()"
      >
        <TwIcon name="photo" :size="22" />
        {{ $t('tabs.photos') }}
      </button>

      <button
        v-if="folders"
        type="button"
        data-testid="pick-folder"
        class="flex min-h-14 items-center justify-center gap-2 rounded-2xl border-2 border-line px-4 text-lg font-semibold text-ink"
        @click="folder?.click()"
      >
        <TwIcon name="folder" :size="22" />
        {{ $t('photos.folder') }}
      </button>
    </div>

    <input
      ref="pictures"
      data-testid="photo-input"
      type="file"
      accept="image/*"
      multiple
      class="hidden"
      @change="picked"
    >
    <input
      ref="folder"
      type="file"
      multiple
      class="hidden"
      @change="picked"
    >

    <p class="text-sm text-ink-3">
      {{ $t('photos.againHint') }}
    </p>

    <section v-if="summary.total > 0" aria-labelledby="upload-title" class="rounded-3xl bg-surface p-5 shadow-card">
      <h2 id="upload-title" class="font-display text-xl font-semibold">
        {{ running ? $t('photos.uploading') : $t('photos.finished') }}
      </h2>

      <div
        class="mt-3 h-2.5 overflow-hidden rounded-full bg-surface-3"
        role="progressbar"
        :aria-valuenow="progress"
        aria-valuemin="0"
        aria-valuemax="100"
      >
        <div class="h-full rounded-full bg-accent transition-all" :style="{ width: `${progress}%` }" />
      </div>

      <p data-testid="upload-summary" class="mt-3 text-base text-ink-2" aria-live="polite">
        {{ $t('photos.uploaded', { n: summary.done }) }}
        <template v-if="summary.known > 0">
          · {{ $t('photos.known', { n: summary.known }) }}
        </template>
        <template v-if="summary.failed > 0">
          · {{ $t('photos.failed', { n: summary.failed }) }}
        </template>
        <template v-if="summary.open > 0">
          · {{ $t('photos.open', { n: summary.open }) }}
        </template>
      </p>

      <p v-if="running" class="mt-2 text-sm text-ink-3">
        {{ $t('photos.keepOpen') }}
      </p>

      <p v-if="skipped > 0" class="mt-2 text-sm text-ink-3">
        {{ $t('photos.skipped', skipped) }}
      </p>

      <ul v-if="failed.length > 0" class="mt-3 space-y-1 text-sm">
        <li v-for="item in failed.slice(0, 10)" :key="item.key" class="text-danger">
          {{ item.name }}: {{ item.message }}
        </li>
        <li v-if="failed.length > 10" class="text-ink-3">
          {{ $t('photos.andMore', { n: failed.length - 10 }) }}
        </li>
      </ul>

      <div v-if="!running" class="mt-4 flex gap-2">
        <button
          v-if="summary.failed > 0"
          type="button"
          class="min-h-11 flex-1 rounded-xl bg-surface-2 px-4 text-base font-semibold"
          @click="retry"
        >
          {{ $t('common.retry') }}
        </button>
        <button
          type="button"
          class="min-h-11 flex-1 rounded-xl px-4 text-base font-medium text-ink-2"
          @click="clear"
        >
          {{ $t('photos.clearList') }}
        </button>
      </div>
    </section>
  </div>
</template>
