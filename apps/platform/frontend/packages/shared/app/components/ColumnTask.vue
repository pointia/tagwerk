<script setup lang="ts">
import { daypartText } from '../utils/task'
import { tint } from '../utils/tint'
import type { OccurrenceDto } from '../composables/useTasks'

/**
 * One task in the day view — in a person's column as in the "Für alle"
 * strip. A tap opens it; checking off is confirmed there.
 */
interface Props {
  entry: OccurrenceDto
  /** The owner's colour: the open card is tinted with it, the done mark filled. */
  color: string
}

const props: Props = defineProps<Props>()

const emit = defineEmits<{ open: [] }>()
</script>

<template>
  <li
    data-testid="column-task"
    class="flex items-center gap-2 rounded-2xl bg-surface p-2 transition-opacity"
    :class="props.entry.done ? 'opacity-60' : ''"
    :style="props.entry.done ? undefined : { background: tint(props.color + '38') }"
  >
    <button
      type="button"
      data-testid="column-open-task"
      :aria-label="props.entry.done ? $t('tasks.doneNamed', { name: props.entry.title }) : props.entry.title"
      class="flex min-h-12 min-w-0 flex-1 items-center gap-2.5 text-left"
      @click="emit('open')"
    >
      <!-- Icon on the first line, not in the middle of a long title. -->
      <span class="flex min-w-0 flex-1 items-start gap-2.5">
        <span v-if="props.entry.icon" aria-hidden="true" class="shrink-0 text-2xl leading-7"><TwEmoji :icon="props.entry.icon" /></span>

        <span class="min-w-0 flex-1">
          <span
            class="block text-lg leading-7 font-medium break-words"
            :class="props.entry.done ? 'text-ink-3 line-through' : ''"
          >{{ props.entry.title }}</span>

          <span v-if="daypartText(props.entry) || props.entry.recurrence" class="block text-xs text-ink-3">
            {{ [daypartText(props.entry), props.entry.recurrence].filter(Boolean).join(' · ') }}
          </span>
        </span>
      </span>

      <span
        v-if="props.entry.points > 0"
        class="shrink-0 text-sm font-semibold tabular-nums"
        :class="props.entry.done ? 'text-ink-3' : 'text-ink-2'"
        :aria-label="$t('task.stars', props.entry.points)"
      >
        <TwStar />{{ props.entry.points }}
      </span>

      <!-- A mark, not a control: undoing goes through the task too. -->
      <span
        v-if="props.entry.done"
        data-testid="done-mark"
        aria-hidden="true"
        class="flex size-8 shrink-0 items-center justify-center rounded-full text-white"
        :style="{ backgroundColor: props.color }"
      >
        <TwIcon name="check" :size="18" />
      </span>
    </button>
  </li>
</template>
