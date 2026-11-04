<script setup lang="ts">
import { daypartText } from '../utils/task'
import type { OccurrenceDto } from '../composables/useTasks'
import { formatIsoDate } from '../utils/calendar'

/*
 * What a task is, in full — and the one place to check it off or take it
 * back. A tap on the row opens it, the button here confirms: a brush in
 * passing ticks nothing, and nobody loses stars by accident.
 *
 * On the wall deliberately without editing: changing tasks belongs on the
 * phone or in the admin, not on a wall that children walk past. What a
 * parent does need there is skipping: a holiday, and the task cannot be
 * done — ticked without stars, the day bonus still within reach.
 */
interface Props {
  entry: OccurrenceDto
  /** The phone's extra for someone who may manage tasks. */
  canEdit?: boolean
  /** Tick off without stars — a parent's call, behind the PIN on the wall. */
  canSkip?: boolean
  /** False on the phone when the server refuses this tick. */
  canComplete?: boolean
  /** A past day: it shows what happened and says why there is no button. */
  past?: boolean
}

const props: Props = withDefaults(defineProps<Props>(), { canEdit: false, canSkip: false, canComplete: true, past: false })

const emit = defineEmits<{ close: [], complete: [], skip: [], edit: [] }>()

useEscape(() => emit('close'))

/*
 * The finger that opened the dialog must not tick it too: a double tap on
 * the row would land its second half on the button below. The button
 * is enabled only after a moment.
 */
const ARMING_MS = 250
const armed = ref(false)
let arming: ReturnType<typeof setTimeout> | undefined

onMounted(() => {
  arming = setTimeout(() => {
    armed.value = true
  }, ARMING_MS)
})

onBeforeUnmount(() => clearTimeout(arming))

const facts = computed(() => [
  daypartText(props.entry),
  props.entry.recurrence,
  props.entry.origin,
].filter(Boolean).join(' · '))
</script>

<template>
  <div class="fixed inset-0 z-30 flex items-center justify-center bg-scrim p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="task-details-title"
      data-testid="task-details"
      class="w-full max-w-lg rounded-3xl bg-surface p-7 shadow-floating"
    >
      <div class="flex items-start gap-4">
        <span v-if="entry.icon" aria-hidden="true" class="text-5xl leading-none"><TwEmoji :icon="entry.icon" /></span>

        <div class="min-w-0 flex-1">
          <h2 id="task-details-title" class="font-display text-3xl font-semibold break-words">
            {{ entry.title }}
          </h2>

          <p class="mt-1 text-lg text-ink-2">
            {{ formatIsoDate(entry.date, { weekday: 'long', day: 'numeric', month: 'long' }) }}
          </p>
        </div>
      </div>

      <p v-if="entry.notes" class="mt-5 text-lg whitespace-pre-line break-words">
        {{ entry.notes }}
      </p>

      <dl class="mt-5 space-y-2 text-lg">
        <div v-if="entry.person_name" class="flex items-center gap-3">
          <dt class="sr-only">{{ $t('tasks.assignee') }}</dt>
          <TwAvatar :name="entry.person_name" :color="entry.color" :avatar="entry.avatar" :size="32" />
          <dd class="font-medium">{{ entry.person_name }}</dd>
        </div>

        <div v-if="entry.points > 0" class="flex items-center gap-3">
          <dt class="sr-only">{{ $t('tasks.stars') }}</dt>
          <dd class="font-medium"><TwStar /> {{ $t('task.stars', entry.points) }}</dd>
        </div>

        <div v-if="facts">
          <dt class="sr-only">{{ $t('task.when') }}</dt>
          <dd class="text-ink-2">{{ facts }}</dd>
        </div>

        <div v-if="entry.done_by">
          <dt class="sr-only">{{ $t('tasks.done') }}</dt>
          <dd class="text-ink-2">{{ $t('tasks.doneByCap', { name: entry.done_by }) }}</dd>
        </div>

        <div v-else-if="entry.done && entry.skipped">
          <dt class="sr-only">{{ $t('tasks.done') }}</dt>
          <dd data-testid="task-skipped" class="text-ink-2">{{ $t('tasks.skippedNote') }}</dd>
        </div>
      </dl>

      <button
        v-if="props.canComplete"
        type="button"
        data-testid="task-details-complete"
        class="mt-7 flex min-h-14 w-full items-center justify-center gap-2 rounded-2xl text-lg font-semibold transition-colors"
        :class="entry.done ? 'border-2 border-line text-ink hover:bg-surface-2' : 'bg-accent text-accent-ink'"
        :disabled="!armed"
        @click="emit('complete')"
      >
        <TwIcon :name="entry.done ? 'refresh' : 'check'" :size="22" />
        {{ entry.done ? $t('tasks.undo') : $t('tasks.done') }}
      </button>
      <p v-if="props.canComplete && entry.done && !entry.skipped && entry.points > 0" data-testid="undo-stars" class="mt-2 text-center text-base text-ink-3">
        {{ $t('tasks.undoStars', entry.points) }}
      </p>

      <p v-if="props.past" data-testid="past-task" class="mt-7 rounded-2xl bg-surface-2 px-4 py-3 text-center text-base text-ink-2">
        {{ $t('tasks.pastDay') }}
      </p>

      <button
        v-if="props.canSkip && !entry.done"
        type="button"
        data-testid="task-details-skip"
        class="mt-2 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-line text-lg font-semibold transition-colors hover:bg-surface-2"
        :disabled="!armed"
        @click="emit('skip')"
      >
        <TwIcon name="skip" :size="20" />
        {{ $t('tasks.skip') }}
      </button>
      <p v-if="props.canSkip && !entry.done" class="mt-1 text-center text-base text-ink-3">
        {{ $t('tasks.skipHint') }}
      </p>

      <button
        v-if="props.canEdit"
        type="button"
        data-testid="task-details-edit"
        class="mt-2 flex min-h-12 w-full items-center justify-center gap-2 rounded-2xl border-2 border-line text-lg font-semibold transition-colors hover:bg-surface-2"
        @click="emit('edit')"
      >
        <TwIcon name="pencil" :size="20" />
        {{ $t('task.edit') }}
      </button>

      <button
        type="button"
        class="mt-2 min-h-12 w-full rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
        @click="emit('close')"
      >
        {{ $t('common.close') }}
      </button>
    </div>
  </div>
</template>
