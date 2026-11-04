<script setup lang="ts">
import type { Lesson } from '../utils/timetable'
import { clockTime } from '../utils/timetable'

/**
 * A lesson is cancelled — or something else takes place.
 *
 * The interface's only write path, and therefore standalone: it is opened
 * from the timetable and from the calendar band. Saving does not happen
 * here; the calling view knows what to reload afterwards.
 */
const props = defineProps<{ lesson: Lesson }>()

const emit = defineEmits<{
  close: []
  cancel: []
  replace: [string]
  revert: []
}>()

useEscape(() => emit('close'))

const replacementSubject = ref('')

function replaceAndClose(): void {
  if (replacementSubject.value.trim() !== '') {
    emit('replace', replacementSubject.value.trim())
  }
}
</script>

<template>
  <div
    class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-6"
    @click.self="emit('close')"
  >
    <div
      role="dialog"
      aria-modal="true"
      :aria-label="props.lesson.title"
      class="flex w-full max-w-md flex-col rounded-3xl bg-surface p-6"
    >
      <h2 class="text-2xl font-semibold text-ink">
        {{ props.lesson.title }}
      </h2>
      <p class="mt-1 text-base text-ink-3">
        {{ clockTime(props.lesson.starts_at) }}–{{ clockTime(props.lesson.ends_at) }}
      </p>

      <template v-if="props.lesson.status === 'normal'">
        <button
          type="button"
          data-testid="lesson-cancel"
          class="mt-5 min-h-13 rounded-2xl bg-surface-2 text-lg font-semibold text-ink transition-colors hover:brightness-95"
          @click="emit('cancel')"
        >
          {{ $t('lesson.cancelled') }}
        </button>

        <label class="mt-4 text-base font-medium text-ink-2">
          {{ $t('lesson.instead') }}
          <input
            v-model="replacementSubject"
            type="text"
            data-testid="lesson-replacement-subject"
            :placeholder="$t('lesson.insteadPlaceholder')"
            class="mt-1 min-h-13 w-full rounded-2xl border-2 border-line bg-surface px-4 text-lg text-ink outline-none focus:border-accent"
          >
        </label>

        <button
          type="button"
          data-testid="lesson-replace"
          class="mt-2 min-h-13 rounded-2xl bg-accent text-lg font-semibold text-accent-ink disabled:opacity-50"
          :disabled="replacementSubject.trim() === ''"
          @click="replaceAndClose"
        >
          {{ $t('lesson.apply') }}
        </button>
      </template>

      <button
        v-else
        type="button"
        data-testid="lesson-revert"
        class="mt-5 min-h-13 rounded-2xl bg-surface-2 text-lg font-semibold text-ink transition-colors hover:brightness-95"
        @click="emit('revert')"
      >
        {{ $t('lesson.asPlanned') }}
      </button>

      <button
        type="button"
        class="mt-2 min-h-12 rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
        @click="emit('close')"
      >
        {{ $t('common.close') }}
      </button>
    </div>
  </div>
</template>
