<script setup lang="ts">
import type { EventInput } from '~/composables/useEventWrite'
import type { AgendaCalendar, AgendaPerson } from '~/composables/useAgenda'
import type { AgendaEvent } from '~/utils/calendar'
import { allDaySpan, instantFromWallTime, wallTimeFromInstant } from '~/utils/time'

const props = defineProps<{
  event: AgendaEvent | null
  /** Default when creating, as a plain calendar date. */
  day: string
  calendars: AgendaCalendar[]
  people: AgendaPerson[]
  timeZone: string
}>()

const emit = defineEmits<{ close: [], saved: [] }>()

useEscape(() => emit('close'))

const { busy, error, foreignState, reset, create, update, destroy } = useEventWrite()

const editing = computed(() => props.event !== null)

const title = ref('')
const location = ref('')
const allDay = ref(false)
const from = ref('')
const until = ref('')
const calendarId = ref<number | null>(null)
const selectedPeople = ref<number[]>([])
const removeConfirmed = ref(false)

const cell = 'mt-1.5 w-full rounded-2xl border-2 border-line bg-canvas px-4 py-3 text-lg text-ink outline-none transition-colors focus:border-accent'

watch(() => props.event, (present) => {
  reset()
  removeConfirmed.value = false

  if (present) {
    title.value = present.title
    location.value = present.location ?? ''
    allDay.value = present.all_day
    calendarId.value = present.calendar_id
    selectedPeople.value = [...present.people]

    from.value = wallTimeFromInstant(present.starts_at, props.timeZone)
    until.value = wallTimeFromInstant(present.ends_at, props.timeZone)

    return
  }

  title.value = ''
  location.value = ''
  allDay.value = false
  selectedPeople.value = []
  /*
   * The first writable one, not simply the first: a signed-out account's
   * calendar cannot be written to, and the rejection would only come once
   * everything has been typed.
   */
  calendarId.value = (props.calendars.find(entry => entry.writable) ?? props.calendars[0])?.id ?? null

  // Default to the tapped day, 09:00 to 10:00 -- one hour is
  // the most common length and saves two inputs.
  from.value = `${props.day}T09:00`
  until.value = `${props.day}T10:00`
}, { immediate: true })

const valid = computed(() => title.value.trim() !== '' && from.value !== '' && until.value !== ''
  && (editing.value || calendarId.value !== null))

function span(): [string, string] {
  if (!allDay.value) {
    return [
      instantFromWallTime(from.value, props.timeZone),
      instantFromWallTime(until.value, props.timeZone),
    ]
  }

  return allDaySpan(from.value.slice(0, 10), until.value.slice(0, 10), props.timeZone)
}

async function save(): Promise<void> {
  if (!valid.value) {
    return
  }

  const [starts, ends] = span()

  const input: EventInput = {
    title: title.value.trim(),
    location: location.value.trim() === '' ? null : location.value.trim(),
    starts_at: starts,
    ends_at: ends,
    all_day: allDay.value,
    people: selectedPeople.value,
  }

  const result = props.event
    ? await update(props.event.id, input)
    : await create({ ...input, calendar_id: calendarId.value ?? undefined })

  if (result !== null) {
    emit('saved')
  }
}

async function remove(): Promise<void> {
  if (!props.event) {
    return
  }

  // Two-step: on a wall in the hallway people tap by accident.
  if (!removeConfirmed.value) {
    removeConfirmed.value = true

    return
  }

  await destroy(props.event.id)

  if (error.value === null) {
    emit('saved')
  }
}

function togglePerson(id: number): void {
  selectedPeople.value = selectedPeople.value.includes(id)
    ? selectedPeople.value.filter(present => present !== id)
    : [...selectedPeople.value, id]
}
</script>

<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-3 sm:p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="event-title"
      class="max-h-full w-full max-w-2xl overflow-y-auto rounded-3xl bg-surface p-5 shadow-floating sm:p-8"
    >
      <div class="flex items-start justify-between gap-4">
        <h2 id="event-title" class="font-display text-3xl font-semibold">
          {{ editing ? $t('event.edit') : $t('event.new') }}
        </h2>

        <button
          type="button"
          :aria-label="$t('common.close')"
          class="-mt-1 -mr-2 flex size-12 items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-surface-2"
          @click="emit('close')"
        >
          <TwIcon name="close" />
        </button>
      </div>

      <div class="mt-6 space-y-5">
        <label class="block">
          <span class="text-base font-semibold text-ink-2">{{ $t('event.title') }}</span>
          <input v-model="title" autofocus :class="cell">
        </label>

        <label class="flex min-h-11 items-center gap-3 text-lg font-medium">
          <input v-model="allDay" type="checkbox" class="size-6 rounded accent-accent">
          {{ $t('event.allDay') }}
        </label>

        <div class="grid grid-cols-2 gap-4">
          <label class="block">
            <span class="text-base font-semibold text-ink-2">{{ $t('event.from') }}</span>
            <input v-model="from" :type="allDay ? 'date' : 'datetime-local'" :class="cell">
          </label>

          <label class="block">
            <span class="text-base font-semibold text-ink-2">{{ $t('event.until') }}</span>
            <input v-model="until" :type="allDay ? 'date' : 'datetime-local'" :class="cell">
          </label>
        </div>

        <label class="block">
          <span class="text-base font-semibold text-ink-2">{{ $t('event.location') }}</span>
          <input v-model="location" :class="cell">
        </label>

        <label v-if="!editing" class="block">
          <span class="text-base font-semibold text-ink-2">{{ $t('event.calendar') }}</span>
          <select v-model="calendarId" :class="cell">
            <option
              v-for="entry in calendars"
              :key="entry.id"
              :value="entry.id"
              :disabled="!entry.writable"
            >
              {{ entry.summary }}{{ entry.writable ? '' : ` — ${$t('event.notConnected')}` }}
            </option>
          </select>
        </label>

        <fieldset>
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('task.forWhom') }}
          </legend>
          <p class="text-sm text-ink-3">
            {{ $t('event.forWhomHint') }}
          </p>

          <div class="mt-2.5 flex flex-wrap gap-2">
            <button
              v-for="person in people"
              :key="person.id"
              type="button"
              data-testid="person-chip"
              :aria-pressed="selectedPeople.includes(person.id)"
              class="flex min-h-12 items-center gap-2.5 rounded-full px-5 text-lg font-semibold transition-colors"
              :class="selectedPeople.includes(person.id) ? 'text-ink' : 'bg-surface-2 text-ink-2'"
              :style="selectedPeople.includes(person.id)
                ? { backgroundColor: (person.color ?? '#8a8276') + '33', boxShadow: `inset 0 0 0 2px ${person.color ?? '#8a8276'}` }
                : undefined"
              @click="togglePerson(person.id)"
            >
              <span class="size-3 rounded-full" :style="{ backgroundColor: person.color ?? '#8a8276' }" />
              {{ person.name }}
            </button>
          </div>
        </fieldset>
      </div>

      <p
        v-if="error"
        data-testid="write-error"
        role="alert"
        class="mt-6 rounded-2xl bg-danger-soft px-5 py-3 text-lg text-danger"
      >
        {{ error }}
        <span v-if="foreignState" class="mt-1 block text-base">
          {{ $t('event.foreignState', { title: foreignState.title }) }}
        </span>
      </p>

      <div class="mt-8 flex items-center justify-between gap-3">
        <button
          v-if="editing"
          type="button"
          class="flex min-h-13 items-center gap-2 rounded-2xl px-5 text-lg font-semibold transition-colors"
          :class="removeConfirmed ? 'bg-danger text-surface' : 'text-danger hover:bg-danger-soft'"
          @click="remove"
        >
          <TwIcon name="delete" :size="20" />
          {{ removeConfirmed ? $t('common.deleteConfirm') : $t('common.delete') }}
        </button>
        <span v-else />

        <div class="flex gap-3">
          <button
            type="button"
            class="min-h-13 rounded-2xl px-5 text-lg font-semibold text-ink-2 transition-colors hover:bg-surface-2"
            @click="emit('close')"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="button"
            :disabled="!valid || busy"
            class="inline-flex items-center justify-center gap-2 min-h-13 rounded-2xl bg-accent px-7 text-lg font-semibold text-accent-ink transition-opacity disabled:opacity-35"
            @click="save"
          >
            <TwPending :busy="busy" />
            {{ $t('common.save') }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
