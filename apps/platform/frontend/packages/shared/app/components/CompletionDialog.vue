<script setup lang="ts">
import type { AgendaPerson } from '../utils/person'
import type { OccurrenceDto } from '../composables/useTasks'

/**
 * Who did that?
 *
 * The question comes **after** checking off, not before. Nobody expected to
 * pick who they are first: you tap the task you did and then answer a
 * question. If an assignee is set, the answer is a single tap — everything
 * else lies one level deeper.
 */
const props = defineProps<{
  entry: OccurrenceDto
  people: AgendaPerson[]
}>()

const emit = defineEmits<{
  close: []
  confirm: [number | null]
}>()

useEscape(() => emit('close'))

/** Without an assignee there is no "you" — then the selection is open right away. */
const pickerOpen = ref(props.entry.person_id === null)

const assignee = computed(() => props.people.find(person => person.id === props.entry.person_id) ?? null)
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="completed-title"
      class="w-full max-w-lg rounded-3xl bg-surface p-7 shadow-floating"
    >
      <h2 id="completed-title" class="font-display text-2xl font-semibold">
        <TwEmoji v-if="entry.icon" :icon="entry.icon" /> {{ entry.title }}
      </h2>

      <template v-if="!pickerOpen && assignee">
        <p class="mt-2 text-lg text-ink-2">
          {{ $t('completion.byAssignee', { name: assignee.name }) }}
        </p>

        <div class="mt-6 flex flex-col gap-3">
          <button
            type="button"
            data-testid="completed-yes"
            class="flex min-h-14 items-center justify-center gap-3 rounded-2xl bg-accent px-5 text-xl font-semibold text-accent-ink transition-opacity hover:opacity-90"
            @click="emit('confirm', assignee.id)"
          >
            <TwAvatar :name="assignee.name" :color="assignee.color" :avatar="assignee.avatar" :size="32" />
            {{ $t('completion.yes', { name: assignee.name }) }}
          </button>

          <button
            type="button"
            data-testid="completed-someone-else"
            class="min-h-13 rounded-2xl bg-surface-2 px-5 text-lg font-semibold text-ink-2 transition-colors hover:text-ink"
            @click="pickerOpen = true"
          >
            {{ $t('completion.someoneElse') }}
          </button>
        </div>
      </template>

      <template v-else>
        <p class="mt-2 text-lg text-ink-2">
          {{ $t('completion.who') }}
        </p>

        <div class="mt-6 flex flex-wrap gap-2.5">
          <button
            v-for="person in people"
            :key="person.id"
            type="button"
            data-testid="completed-person"
            class="flex min-h-14 items-center gap-2.5 rounded-2xl px-5 text-lg font-semibold transition-colors"
            :style="{
              backgroundColor: (person.color ?? '#8a8276') + '2e',
              boxShadow: `inset 0 0 0 2px ${person.color ?? '#8a8276'}`,
            }"
            @click="emit('confirm', person.id)"
          >
            <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="32" />
            {{ person.name }}
          </button>
        </div>

        <!--
          Checking off without a name stays possible: the family does some
          tasks together, and then any attribution is made up. There are no
          stars for it — that is why it sits quietly at the bottom.
        -->
        <button
          type="button"
          data-testid="completed-anonymous"
          class="mt-5 min-h-12 rounded-2xl px-4 text-base font-medium text-ink-3 transition-colors hover:text-ink-2"
          @click="emit('confirm', null)"
        >
          {{ $t('completion.withoutName') }}
        </button>
      </template>

      <div class="mt-6 flex justify-end">
        <button
          type="button"
          class="min-h-12 rounded-2xl px-4 text-base font-semibold text-ink-2 transition-colors hover:text-ink"
          @click="emit('close')"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </div>
  </div>
</template>
