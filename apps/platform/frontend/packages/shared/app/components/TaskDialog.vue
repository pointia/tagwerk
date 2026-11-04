<script setup lang="ts">
import { iconChoices } from '../utils/iconChoices'
import { emojiHex } from '../utils/emoji'
import { intlLocale } from '../utils/intlLocale'
import type { DaypartOption, TaskInput, TaskTemplate, Member } from '../utils/task'

/**
 * Create or change a task — on the wall as on the phone.
 *
 * Deliberately shorter than the form in the admin: whoever stands here has
 * one hand free. Everything is tappable, only the title is typed.
 *
 * Saving does not happen here. The calling view knows where it goes and what
 * to reload afterwards — on the wall the parent PIN sits in between.
 */
const props = defineProps<{
  /** Null creates; otherwise this task is changed. */
  task: TaskTemplate | null
  /** Preselected when creating — the plus of a day column means its person. */
  defaultPerson?: number | null
  people: Member[]
  /** Abbreviation to full name, e.g. `MO` to "Montag". */
  weekdays: Record<string, string>
  dayparts?: DaypartOption[]
  today: string
  busy: boolean
  error: string | null
}>()

const emit = defineEmits<{
  close: []
  save: [TaskInput]
  remove: []
}>()

useEscape(() => emit('close'))

const POINT_STEPS = [0, 1, 2, 3, 5, 10]

const title = ref(props.task?.title ?? '')
const icon = ref<string | null>(props.task?.icon ?? null)
const points = ref(props.task?.points ?? 1)
const rotation = props.task?.rotation ?? []
const initial = rotation.length > 0
  ? rotation
  : props.task?.person_ids ?? [props.task?.person_id ?? props.defaultPerson ?? null]
const who = ref<number[]>(initial.filter((id): id is number => id !== null))
const takeTurns = ref(rotation.length > 1)
const skipHolidays = ref(props.task?.skip_holidays ?? false)
// The rule's start, not the tapped date: a rotation counts from there.
const date = ref(props.task?.due_on ?? props.task?.date ?? props.today)
const days = ref<string[]>(props.task?.weekdays ?? [])
const dayparts = ref<string[]>(props.task?.dayparts ?? [])
const removeConfirmed = ref(false)

/** Monthly, every other week, custom: the weekday buttons cannot show it. */
const ruleLocked = props.task?.weekdays === null

/** Open from the start when the task already uses something in there. */
const more = ref(dayparts.value.length > 0 || takeTurns.value)

/*
 * The picker shows all of the catalog at once, some 1,600 icons. A search
 * narrows them; without one, icons matching the title come first
 * ("Gemüse gegessen" → 🥦 🥕). They are drawn as text, not as pictures:
 * 1,600 image requests kept a tablet busy for seconds.
 */
const iconSearch = ref('')

const { icons: catalog, load: loadCatalog } = useEmojiCatalog()
onMounted(loadCatalog)

const catalogIcons = computed(() => catalog.value.map(entry => entry.emoji))
const catalogWords = computed(() => Object.fromEntries(catalog.value.map(entry => [entry.emoji, entry.words])))

/** Stored icons may lack the variation selector the catalog's carry: same picture, same icon. */
const sameIcon = (a: string | null, b: string): boolean => a !== null && emojiHex(a) === emojiHex(b)

const shownIcons = computed(() => {
  const shown = iconChoices(catalogIcons.value, catalogWords.value, iconSearch.value, title.value)

  if (iconSearch.value.trim() !== '') {
    return shown
  }

  // An icon typed in the admin is not in the catalog; it stays in reach.
  return icon.value !== null && !shown.some(entry => sameIcon(icon.value, entry)) ? [icon.value, ...shown] : shown
})

/*
 * Fallback for an occurrence without the rule's weekdays (a list loaded
 * before they were sent): the weekday of the tapped date.
 */
if (props.task?.routine && props.task.weekdays === undefined) {
  const abbreviation = Object.keys(props.weekdays)
  const index = (new Date(`${props.task.date}T12:00:00Z`).getUTCDay() + 6) % 7

  days.value = abbreviation[index] ? [abbreviation[index]] : []
}

const repeats = computed(() => ruleLocked || days.value.length > 0)
const mayTakeTurns = computed(() => repeats.value && who.value.length > 1)

/** Names in tap order — the order of turns. */
const turnOrder = computed(() => who.value
  .map(id => props.people.find(person => person.id === id)?.name)
  .filter(Boolean)
  .join(' → '))
const valid = computed(() => title.value.trim() !== '')

const fromDate = computed(() => new Date(`${date.value}T00:00:00Z`)
  .toLocaleDateString(intlLocale.value, { day: 'numeric', month: 'long', timeZone: 'UTC' }))

const cell = 'mt-1.5 w-full rounded-2xl border-2 border-line bg-canvas px-4 py-3 text-lg text-ink outline-none transition-colors focus:border-accent sm:text-xl'

/** Several at once, for homework every child has — each ticks off alone. */
function togglePerson(id: number): void {
  who.value = who.value.includes(id)
    ? who.value.filter(present => present !== id)
    : [...who.value, id]
}

function toggleDay(abbreviation: string): void {
  days.value = days.value.includes(abbreviation)
    ? days.value.filter(present => present !== abbreviation)
    : [...days.value, abbreviation]
}

function toggleDaypart(value: string): void {
  dayparts.value = dayparts.value.includes(value)
    ? dayparts.value.filter(present => present !== value)
    : [...dayparts.value, value]
}

function save(): void {
  if (!valid.value) {
    return
  }

  emit('save', {
    title: title.value.trim(),
    icon: icon.value,
    points: points.value,
    person_ids: who.value,
    skip_holidays: repeats.value && skipHolidays.value,
    date: date.value,
    // Left out, the server keeps a rule this form cannot express.
    ...(ruleLocked ? {} : { weekdays: days.value }),
    dayparts: dayparts.value,
    rotation: takeTurns.value && mayTakeTurns.value ? who.value : [],
  })
}

function remove(): void {
  // Two-step: on a wall in the hallway people tap by accident.
  if (!removeConfirmed.value) {
    removeConfirmed.value = true

    return
  }

  emit('remove')
}

const color = (value: string | null): string => value ?? '#8a8276'
</script>

<template>
  <div class="fixed inset-0 z-40 flex items-center justify-center bg-scrim p-3 sm:p-6" @click.self="emit('close')">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="task-title"
      class="max-h-full w-full max-w-2xl overflow-y-auto rounded-3xl bg-surface p-5 shadow-floating sm:p-8"
    >
      <div class="flex items-start justify-between gap-4">
        <h2 id="task-title" class="font-display text-2xl font-semibold sm:text-3xl">
          {{ props.task ? $t('task.edit') : $t('task.new') }}
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

      <div class="mt-5 space-y-5 sm:mt-6 sm:space-y-6">
        <label class="block">
          <span class="text-base font-semibold text-ink-2">{{ $t('task.what') }}</span>
          <input
            v-model="title"
            autofocus
            data-testid="task-title"
            :placeholder="$t('task.whatPlaceholder')"
            :class="cell"
          >
        </label>

        <!--
          The icon stands before the title — whoever cannot read yet
          recognises the task by it. Hence a grid to tap, with a search over
          German and English words.
        -->
        <fieldset>
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('task.icon') }}
          </legend>

          <input
            v-model="iconSearch"
            type="search"
            data-testid="icon-search"
            :placeholder="$t('task.iconSearch')"
            :aria-label="$t('task.iconSearch')"
            class="mt-2 w-full rounded-2xl border-2 border-line bg-canvas px-4 py-2.5 text-base text-ink outline-none transition-colors focus:border-accent"
          >

          <div class="mt-2 flex max-h-72 flex-wrap gap-1.5 overflow-y-auto">
            <button
              type="button"
              data-testid="icon-none"
              :aria-pressed="icon === null"
              :aria-label="$t('task.noIcon')"
              class="flex size-12 items-center justify-center rounded-2xl text-xl transition-colors"
              :class="icon === null ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-3'"
              @click="icon = null"
            >
              —
            </button>

            <button
              v-for="suggestion in shownIcons"
              :key="suggestion"
              type="button"
              data-testid="icon"
              :aria-pressed="sameIcon(icon, suggestion)"
              :aria-label="$t('task.iconNamed', { icon: suggestion })"
              class="flex size-12 items-center justify-center rounded-2xl text-2xl transition-colors"
              :class="sameIcon(icon, suggestion) ? 'bg-accent/25 ring-2 ring-accent' : 'bg-surface-2'"
              @click="icon = suggestion"
            >
              {{ suggestion }}
            </button>
          </div>
        </fieldset>

        <fieldset>
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('task.forWhom') }}
          </legend>
          <p class="text-sm text-ink-3">
            {{ $t('task.forWhomHint') }}
          </p>

          <div class="mt-2 flex flex-wrap gap-2">
            <button
              type="button"
              data-testid="task-person"
              :aria-pressed="who.length === 0"
              class="min-h-12 rounded-full px-5 text-lg font-semibold transition-colors"
              :class="who.length === 0 ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2'"
              @click="who = []"
            >
              {{ $t('task.everyone') }}
            </button>

            <button
              v-for="person in people"
              :key="person.id"
              type="button"
              data-testid="task-person"
              :aria-pressed="who.includes(person.id)"
              class="flex min-h-12 items-center gap-2.5 rounded-full px-5 text-lg font-semibold transition-colors"
              :class="who.includes(person.id) ? 'text-ink' : 'bg-surface-2 text-ink-2'"
              :style="who.includes(person.id)
                ? { backgroundColor: color(person.color) + '33', boxShadow: `inset 0 0 0 2px ${color(person.color)}` }
                : undefined"
              @click="togglePerson(person.id)"
            >
              <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="28" />
              {{ person.name }}
            </button>
          </div>
        </fieldset>

        <!-- Stars instead of a number in an input field: that is what the
             children collect, and that is what the task calls it too. -->
        <fieldset>
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('task.starsForDoing') }}
          </legend>

          <div class="mt-2 flex flex-wrap gap-2">
            <button
              v-for="level in POINT_STEPS"
              :key="level"
              type="button"
              data-testid="task-points"
              :aria-pressed="points === level"
              :aria-label="level === 0 ? $t('task.noStars') : $t('task.stars', level)"
              class="flex min-h-12 min-w-14 items-center justify-center gap-1.5 rounded-2xl px-4 text-lg font-bold tabular-nums transition-colors"
              :class="points === level ? 'bg-points text-points-ink' : 'bg-surface-2 text-ink-2'"
              @click="points = level"
            >
              <TwStar />
              {{ level }}
            </button>
          </div>
        </fieldset>

        <fieldset>
          <legend class="text-base font-semibold text-ink-2">
            {{ $t('task.when') }}
          </legend>
          <p v-if="ruleLocked" data-testid="task-rule-locked" class="mt-1 text-lg">
            {{ props.task?.recurrence }}
            <span class="block text-sm text-ink-3">{{ $t('task.ruleInAdmin') }}</span>
          </p>
          <p v-else class="text-sm text-ink-3">
            {{ $t('task.whenHint') }}
          </p>

          <div v-if="!ruleLocked" class="mt-2 flex flex-wrap gap-1.5">
            <button
              v-for="(name, abbreviation) in weekdays"
              :key="abbreviation"
              type="button"
              data-testid="task-day"
              :aria-pressed="days.includes(abbreviation)"
              :aria-label="name"
              class="min-h-12 min-w-13 rounded-2xl text-lg font-semibold transition-colors"
              :class="days.includes(abbreviation) ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2'"
              @click="toggleDay(abbreviation)"
            >
              {{ name.slice(0, 2) }}
            </button>
          </div>

          <label v-if="!ruleLocked" class="mt-4 block">
            <span class="text-base font-semibold text-ink-2">
              {{ repeats ? $t('task.from') : $t('task.dueOn') }}
            </span>
            <input v-model="date" type="date" data-testid="task-date" :class="cell">
          </label>

          <p v-if="repeats && !ruleLocked" data-testid="task-preview" class="mt-2 text-base text-ink-2">
            {{ $t('task.weeklyFrom', { date: fromDate }) }}
          </p>

          <!-- Only for repeating tasks: a one-off is set on its day on purpose. -->
          <button
            v-if="repeats"
            type="button"
            role="switch"
            data-testid="task-skip-holidays"
            :aria-checked="skipHolidays"
            class="mt-4 flex min-h-12 w-full items-center gap-3 rounded-2xl bg-surface-2 px-4 py-3 text-left"
            @click="skipHolidays = !skipHolidays"
          >
            <span
              class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors"
              :class="skipHolidays ? 'bg-accent' : 'bg-line'"
            >
              <span class="size-5 rounded-full bg-surface transition-transform" :class="skipHolidays ? 'translate-x-5' : ''" />
            </span>
            <span class="min-w-0">
              <span class="block text-lg font-semibold">{{ $t('task.skipHolidays') }}</span>
              <span class="block text-sm text-ink-3">{{ $t('task.skipHolidaysHint') }}</span>
            </span>
          </button>
        </fieldset>

        <!--
          What a routine needs now and then, folded away so creating in
          passing stays short. The rest of the admin form stays there.
        -->
        <div class="rounded-2xl border-2 border-line">
          <button
            type="button"
            data-testid="task-more"
            :aria-expanded="more"
            class="flex min-h-12 w-full items-center justify-between gap-3 px-4 text-lg font-semibold text-ink-2"
            @click="more = !more"
          >
            {{ $t('task.more') }}
            <TwIcon :name="more ? 'up' : 'down'" :size="20" />
          </button>

          <div v-if="more" class="space-y-5 border-t-2 border-line p-4">
            <fieldset v-if="props.dayparts?.length">
              <legend class="text-base font-semibold text-ink-2">
                {{ $t('task.dayparts') }}
              </legend>
              <p class="text-sm text-ink-3">
                {{ $t('task.daypartsHint') }}
              </p>

              <div class="mt-2 flex flex-wrap gap-2">
                <button
                  v-for="part in props.dayparts"
                  :key="part.value"
                  type="button"
                  data-testid="task-daypart"
                  :aria-pressed="dayparts.includes(part.value)"
                  class="flex min-h-12 items-center gap-2 rounded-2xl px-4 text-lg font-semibold transition-colors"
                  :class="dayparts.includes(part.value) ? 'bg-accent text-accent-ink' : 'bg-surface-2 text-ink-2'"
                  @click="toggleDaypart(part.value)"
                >
                  <TwEmoji :icon="part.icon" />
                  {{ part.label }}
                </button>
              </div>
            </fieldset>

            <!-- Only with two or more people on a repeating task; the tap
                 order above is the order of turns. -->
            <button
              v-if="mayTakeTurns"
              type="button"
              role="switch"
              data-testid="task-take-turns"
              :aria-checked="takeTurns"
              class="flex min-h-12 w-full items-center gap-3 rounded-2xl bg-surface-2 px-4 py-3 text-left"
              @click="takeTurns = !takeTurns"
            >
              <span
                class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors"
                :class="takeTurns ? 'bg-accent' : 'bg-line'"
              >
                <span class="size-5 rounded-full bg-surface transition-transform" :class="takeTurns ? 'translate-x-5' : ''" />
              </span>
              <span class="min-w-0">
                <span class="block text-lg font-semibold">{{ $t('task.takeTurns') }}</span>
                <span class="block text-sm text-ink-3">{{ $t('task.takeTurnsHint', { order: turnOrder }) }}</span>
              </span>
            </button>

            <p class="text-sm text-ink-3">
              {{ $t('task.moreInAdmin') }}
            </p>
          </div>
        </div>
      </div>

      <p
        v-if="error"
        role="alert"
        data-testid="task-error"
        class="mt-6 rounded-2xl bg-danger-soft px-5 py-3 text-lg text-danger"
      >
        {{ error }}
      </p>

      <div class="mt-7 flex items-center justify-between gap-3 sm:mt-8">
        <!-- "Entfernen", not "Löschen": the task disappears from the view,
             the history and the coins earned stay. -->
        <button
          v-if="props.task"
          type="button"
          data-testid="task-remove"
          class="flex min-h-13 items-center gap-2 rounded-2xl px-4 text-lg font-semibold transition-colors sm:px-5"
          :class="removeConfirmed ? 'bg-danger text-surface' : 'text-danger hover:bg-danger-soft'"
          @click="remove"
        >
          <TwIcon name="delete" :size="20" />
          {{ removeConfirmed ? $t('common.removeConfirm') : $t('common.remove') }}
        </button>
        <span v-else />

        <div class="flex gap-2 sm:gap-3">
          <button
            type="button"
            class="min-h-13 rounded-2xl px-4 text-lg font-semibold text-ink-2 transition-colors hover:bg-surface-2 sm:px-5"
            @click="emit('close')"
          >
            {{ $t('common.cancel') }}
          </button>
          <button
            type="button"
            data-testid="task-save"
            :disabled="!valid || busy"
            class="inline-flex items-center justify-center gap-2 min-h-13 rounded-2xl bg-accent px-5 text-lg font-semibold text-accent-ink transition-opacity disabled:opacity-35 sm:px-7"
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
