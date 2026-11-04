<script setup lang="ts">
const { t } = useI18n()

/*
 * A tile from a plugin.
 *
 * The plugin only sends data — a figure, a list, a picture, a table, a bar,
 * pairs or buttons — and this tile draws it in the wall's look. Texts stay
 * text: a plugin cannot inject anything here.
 */
type Content =
  | { kind: 'metric', value: string, caption: string | null }
  | { kind: 'list', rows: { text: string, detail: string | null }[] }
  | { kind: 'image', url: string, caption: string | null }
  | { kind: 'table', header: string[], rows: string[][] }
  | { kind: 'progress', value: number, max: number, caption: string | null }
  | { kind: 'pairs', rows: { label: string, value: string }[] }
  | { kind: 'actions', buttons: { name: string, label: string }[], caption: string | null }

const props = defineProps<{ tileId: number, editing?: boolean, settings?: Record<string, unknown> }>()

const { withPin } = usePinGate()
const { busy, run } = useBusy()
const tapped = ref<string | null>(null)

/** The server keeps the content for a minute; asking more often would gain nothing. */
const GAP_MS = 5 * 60 * 1000

const content = ref<Content | null>(null)
const hint = ref<string | null>(null)

async function load(): Promise<void> {
  try {
    const response = await $fetch<{ content: Content }>(`/api/wall/dashboard/${props.tileId}/content`, {
      credentials: 'same-origin',
    })

    content.value = response.content
    hint.value = null
  }
  catch {
    // Keep the last state: on the wall something old is better than nothing.
    hint.value = t('dashboard.unavailable')
  }
}

/* A guarded action asks the wall for the PIN (423) and repeats after unlocking. */
async function act(name: string): Promise<void> {
  tapped.value = name

  await run(async () => {
    try {
      const response = await withPin(() => $fetch<{ content: Content }>(`/api/wall/dashboard/${props.tileId}/action/${name}`, {
        method: 'POST',
        credentials: 'same-origin',
      }))

      content.value = response.content
      hint.value = null
    }
    catch {
      hint.value = t('dashboard.unavailable')
    }
  })
}

let clock: ReturnType<typeof setInterval> | null = null

onMounted(() => {
  load()
  clock = setInterval(load, GAP_MS)
})

// New settings mean new content: the server keys its cache by them.
watch(() => props.settings, load, { deep: true })

onBeforeUnmount(() => {
  if (clock !== null) {
    clearInterval(clock)
  }
})
</script>

<template>
  <div class="flex h-full flex-col" data-testid="widget-generic">
    <div v-if="content?.kind === 'metric'" class="flex flex-1 flex-col justify-center">
      <span class="block font-display text-5xl leading-none font-semibold tabular-nums">
        {{ content.value }}
      </span>
      <span v-if="content.caption" class="mt-2 block truncate text-base text-ink-2">
        {{ content.caption }}
      </span>
    </div>

    <ul v-else-if="content?.kind === 'list'" class="flex flex-col gap-1.5 overflow-hidden">
      <li
        v-for="(row, index) in content.rows"
        :key="index"
        class="flex items-baseline justify-between gap-3 text-base"
      >
        <span class="min-w-0 truncate">{{ row.text }}</span>
        <span v-if="row.detail" class="shrink-0 text-ink-3">{{ row.detail }}</span>
      </li>
    </ul>

    <figure v-else-if="content?.kind === 'image'" class="flex h-full min-h-0 flex-col gap-1">
      <img :src="content.url" :alt="content.caption ?? ''" class="min-h-0 flex-1 rounded-2xl object-cover">
      <figcaption v-if="content.caption" class="truncate text-sm text-ink-2">
        {{ content.caption }}
      </figcaption>
    </figure>

    <div v-else-if="content?.kind === 'table'" class="relative min-h-0 overflow-hidden">
      <table class="w-full text-left text-base">
        <thead>
          <tr class="text-sm text-ink-3">
            <th v-for="(column, index) in content.header" :key="index" scope="col" class="pb-1 font-semibold">
              {{ column }}
            </th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, index) in content.rows" :key="index" class="border-t border-line">
            <td v-for="(cell, column) in row" :key="column" class="max-w-0 truncate py-1 pr-2">
              {{ cell }}
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <div v-else-if="content?.kind === 'progress'" class="flex flex-1 flex-col justify-center gap-2">
      <div
        role="progressbar"
        :aria-valuenow="content.value"
        aria-valuemin="0"
        :aria-valuemax="content.max"
        :aria-label="content.caption ?? undefined"
        class="h-4 overflow-hidden rounded-full bg-surface-2"
      >
        <div class="h-full rounded-full bg-accent" :style="{ width: `${(content.value / content.max) * 100}%` }" />
      </div>
      <span v-if="content.caption" class="truncate text-base text-ink-2">{{ content.caption }}</span>
    </div>

    <dl v-else-if="content?.kind === 'pairs'" class="flex flex-col gap-1.5 overflow-hidden text-base">
      <div v-for="(row, index) in content.rows" :key="index" class="flex items-baseline justify-between gap-3">
        <dt class="min-w-0 truncate text-ink-2">
          {{ row.label }}
        </dt>
        <dd class="shrink-0 font-semibold tabular-nums">
          {{ row.value }}
        </dd>
      </div>
    </dl>

    <div v-else-if="content?.kind === 'actions'" class="flex flex-col gap-2 overflow-hidden">
      <p v-if="content.caption" class="truncate text-base text-ink-2">
        {{ content.caption }}
      </p>
      <div class="flex flex-wrap gap-2">
        <button
          v-for="button in content.buttons"
          :key="button.name"
          type="button"
          data-testid="widget-action"
          :disabled="props.editing"
          class="flex min-h-11 items-center gap-2 rounded-2xl bg-surface-2 px-4 text-base font-semibold"
          @click="act(button.name)"
        >
          {{ button.label }}
          <TwPending :busy="busy && tapped === button.name" />
        </button>
      </div>
      <p v-if="hint" class="text-sm text-ink-3">
        {{ hint }}
      </p>
    </div>

    <!-- No "error": on a wall in the hallway nobody can do anything about it. -->
    <p v-if="hint && !content" class="text-base text-ink-3">
      {{ hint }}
    </p>
    <p v-else-if="!content" class="text-base text-ink-3">
      {{ $t('common.loadingShort') }}
    </p>
  </div>
</template>
