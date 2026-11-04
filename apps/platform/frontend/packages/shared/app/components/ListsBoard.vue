<script setup lang="ts" generic="Item extends { id: number, title: string, quantity: string | null, done: boolean }">
import { openFirst } from '../utils/task'

interface Checklist {
  id: number
  name: string
  color: string | null
  open_count: number
  items: Item[]
}

interface Props {
  lists: Checklist[]
  error?: string | null
}

const props: Props = withDefaults(defineProps<Props>(), { error: null })

// Read only for an account that may not change lists.
const { can } = useActor()
const canEdit = computed(() => can('lists.edit'))

const emit = defineEmits<{
  add: [number, string]
  toggle: [Item]
  remove: [Item]
  clear: [number]
}>()

const { armed, tap } = useTwoStep()

/** One draft per list — otherwise the input would move along while typing. */
const draft = ref<Record<number, string>>({})

function submit(listId: number): void {
  const text = (draft.value[listId] ?? '').trim()

  if (text === '') {
    return
  }

  emit('add', listId, text)
  draft.value[listId] = ''
}

function hasDone(list: Checklist): boolean {
  return list.items.some((item: Item) => item.done)
}
</script>

<template>
  <!-- Side by side on the wall, one below the other on a narrow phone. -->
  <div class="flex flex-col sm:h-full">
    <p
      v-if="props.error"
      role="alert"
      class="mb-4 flex shrink-0 items-center gap-3 rounded-2xl bg-warning px-5 py-3 text-base font-medium text-warning-ink"
    >
      <TwIcon name="warning" :size="20" />
      {{ props.error }}
    </p>

    <div
      v-if="props.lists.length === 0"
      class="flex flex-col items-center rounded-3xl border-2 border-dashed border-line px-6 py-14 text-center sm:py-20"
    >
      <TwIcon name="lists" :size="44" class="text-ink-3" />
      <p class="mt-4 font-display text-2xl font-semibold sm:text-3xl">
        {{ $t('lists.none') }}
      </p>
      <p class="mt-1 text-lg text-ink-3">
        {{ $t('lists.noneHint') }}
      </p>
    </div>

    <div v-else class="grid gap-5 pb-1 sm:min-h-0 sm:flex-1 sm:auto-cols-[minmax(22rem,1fr)] sm:grid-flow-col sm:overflow-x-auto">
      <section
        v-for="list in props.lists"
        :key="list.id"
        data-testid="list"
        :aria-labelledby="`liste-${list.id}`"
        class="flex flex-col overflow-hidden rounded-3xl bg-surface shadow-card sm:min-h-0"
      >
        <header class="flex shrink-0 items-center gap-3 px-5 pt-5 pb-3">
          <span class="size-3.5 shrink-0 rounded-full" :style="{ backgroundColor: list.color ?? '#8a8276' }" />

          <h2 :id="`liste-${list.id}`" class="min-w-0 flex-1 truncate font-display text-2xl font-semibold">
            {{ list.name }}
          </h2>

          <span class="shrink-0 rounded-full bg-surface-2 px-3 py-1 text-sm font-bold text-ink-2 tabular-nums">
            {{ $t('tasks.openCount', { n: list.open_count }) }}
          </span>
        </header>

        <ul class="space-y-0.5 px-3 sm:min-h-0 sm:flex-1 sm:overflow-y-auto">
          <li
            v-for="item in openFirst(list.items)"
            :key="item.id"
            data-testid="item"
            class="flex items-center gap-1"
          >
            <button
              type="button"
              :aria-pressed="item.done"
              :disabled="!canEdit"
              class="flex min-h-13 min-w-0 flex-1 items-center gap-3 rounded-2xl px-2 text-left transition-colors hover:bg-surface-2/60 disabled:cursor-default disabled:hover:bg-transparent"
              @click="emit('toggle', item)"
            >
              <span
                class="flex size-7 shrink-0 items-center justify-center rounded-lg border-2 transition-colors"
                :class="item.done ? 'border-accent bg-accent text-accent-ink' : 'border-surface-3'"
              >
                <TwIcon v-if="item.done" name="check" :size="18" />
              </span>

              <span class="min-w-0 flex-1 text-lg" :class="item.done ? 'text-ink-3 line-through' : ''">
                {{ item.title }}
                <span v-if="item.quantity" class="text-base text-ink-3">· {{ item.quantity }}</span>
              </span>
            </button>

            <button
              v-if="canEdit"
              type="button"
              :aria-label="armed === `artikel-${item.id}` ? $t('common.removeNamedConfirm', { name: item.title }) : $t('common.removeNamed', { name: item.title })"
              class="flex min-h-11 min-w-11 shrink-0 items-center justify-center gap-1.5 rounded-xl text-sm font-bold transition-colors"
              :class="armed === `artikel-${item.id}`
                ? 'bg-danger px-3 text-surface'
                : 'text-ink-3 hover:bg-danger-soft hover:text-danger'"
              @click="tap(`artikel-${item.id}`, () => emit('remove', item))"
            >
              <TwIcon name="delete" :size="20" />
              <span v-if="armed === `artikel-${item.id}`">{{ $t('common.removeAsk') }}</span>
            </button>
          </li>

          <li v-if="list.items.length === 0" class="px-3 py-6 text-base text-ink-3">
            {{ $t('lists.empty') }}
          </li>
        </ul>

        <footer v-if="canEdit" class="shrink-0 space-y-2 border-t border-line p-3">
          <form class="flex gap-2" @submit.prevent="submit(list.id)">
            <input
              v-model="draft[list.id]"
              data-testid="item-input"
              :aria-label="$t('lists.addTo', { name: list.name })"
              :placeholder="$t('lists.addPlaceholder')"
              class="min-h-12 min-w-0 flex-1 rounded-2xl border-2 border-line bg-canvas px-4 text-lg text-ink outline-none transition-colors placeholder:text-ink-3 focus:border-accent"
            >

            <button
              type="submit"
              :aria-label="$t('lists.add')"
              class="flex size-12 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-ink"
            >
              <TwIcon name="plus" />
            </button>
          </form>

          <button
            v-if="hasDone(list)"
            type="button"
            data-testid="clear-done"
            class="min-h-11 w-full rounded-2xl px-4 text-base font-semibold transition-colors"
            :class="armed === `leeren-${list.id}` ? 'bg-danger text-surface' : 'text-ink-2 hover:bg-surface-2'"
            @click="tap(`leeren-${list.id}`, () => emit('clear', list.id))"
          >
            {{ armed === `leeren-${list.id}` ? $t('lists.clearDoneConfirm') : $t('lists.clearDone') }}
          </button>
        </footer>
      </section>
    </div>
  </div>
</template>
