<script setup lang="ts">
import type { AgendaPerson } from '../utils/person'
import type { Progress } from '../composables/useProgress'

const { t } = useI18n()

/*
 * One bar, two jobs: who is how far along — and whose events do I want to
 * see right now. Separately that would be two rows of avatars on top of each
 * other that show the same thing and mean different things.
 */
const props = withDefaults(defineProps<{
  people: AgendaPerson[]
  /** Selected people; `null` stands for the family chip. Empty means all. */
  selection: (number | null)[]
  progress: Progress[]
  /** Whether the range has events without a person — otherwise no family chip. */
  family: boolean
  /** What the chips choose, for screen readers. */
  label?: string
  /** The chip for what belongs to nobody in particular. */
  familyLabel?: string
}>(), { label: undefined, familyLabel: undefined })

const familyName = computed(() => props.familyLabel ?? t('people.family'))

const emit = defineEmits<{ toggle: [number | null] }>()

function active(id: number | null): boolean {
  return props.selection.length === 0 || props.selection.includes(id)
}

function snapshot(id: number): Progress | null {
  return props.progress.find(entry => entry.person_id === id) ?? null
}

function share(id: number): number {
  const entry = snapshot(id)

  return entry === null || entry.total === 0 ? 0 : entry.done / entry.total
}

/*
 * What a screen reader hears after the name. Inside the button, not as
 * aria-label: the visible text (the avatar's initial included) must be part
 * of the name, so a voice command saying what is written there finds it.
 */
function personDetail(person: AgendaPerson): string {
  const entry = snapshot(person.id)
  const state = active(person.id) ? t('people.shown') : t('people.hidden')

  return entry === null
    ? `, ${state}`
    : `, ${t('people.progress', { done: entry.done, total: entry.total })}, ${state}`
}
</script>

<template>
  <div
    v-if="people.length > 0"
    role="group"
    :aria-label="label ?? $t('people.showCalendars')"
    data-testid="people-strip"
    class="flex flex-wrap items-center gap-2"
  >
    <button
      v-if="family"
      type="button"
      data-testid="people-chip"
      data-person="familie"
      :aria-pressed="active(null)"
      :aria-label="`${familyName}, ${active(null) ? $t('people.shown') : $t('people.hidden')}`"
      class="flex min-h-11 items-center gap-2 rounded-full px-4 text-base font-semibold transition-opacity"
      :class="active(null) ? 'bg-surface text-ink shadow-card' : 'bg-surface-2 text-ink-3 opacity-60'"
      @click="emit('toggle', null)"
    >
      {{ familyName }}
    </button>

    <button
      v-for="person in people"
      :key="person.id"
      type="button"
      data-testid="people-chip"
      :data-person="person.id"
      :aria-pressed="active(person.id)"
      class="relative flex min-h-11 items-center gap-2 rounded-full px-3 transition-opacity"
      :class="active(person.id) ? 'bg-surface shadow-card' : 'bg-surface-2 opacity-60'"
      @click="emit('toggle', person.id)"
    >
      <!-- The name follows as text; the initial would only be read as a stray letter. -->
      <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="30" aria-hidden="true" />

      <span class="flex flex-col items-start gap-0.5">
        <span class="text-base leading-none font-semibold" :class="active(person.id) ? 'text-ink' : 'text-ink-3'">
          {{ person.name }}
        </span>
        <span class="sr-only">{{ personDetail(person) }}</span>

        <!--
          The bar only shows where there is something to do. An empty track
          under every name would read like "nothing done".
        -->
        <span
          v-if="snapshot(person.id)"
          data-testid="people-bar"
          class="flex h-1.5 w-20 overflow-hidden rounded-full bg-surface-2"
        >
          <span
            class="h-full rounded-full transition-[width]"
            :style="{ width: `${Math.round(share(person.id) * 100)}%`, backgroundColor: person.color ?? '#8a8276' }"
          />
        </span>
      </span>
    </button>
  </div>
</template>
