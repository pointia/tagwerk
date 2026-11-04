<script setup lang="ts">
import { daypartText, occurrenceKey, openFirst } from '../utils/task'
import type { AgendaPerson } from '../utils/person'
import type { BonusDto, OccurrenceDto } from '../composables/useTasks'
import type { TaskInput } from '../utils/task'
import { isDone } from '../utils/dayCompletion'
import { formatIsoDate } from '../utils/calendar'
import { dayProgress, shiftedDay, shownFor } from '../utils/dayColumns'
import { isOffline } from '../utils/network'
import { tint } from '../utils/tint'

const { t } = useI18n()

interface Props {
  occurrences: OccurrenceDto[]
  people: AgendaPerson[]
  today: string
  error?: string | null
  /** Whether the household uses stars at all; without, no extra stars. */
  points?: boolean
}

const props: Props = withDefaults(defineProps<Props>(), { error: null, points: false })

const emit = defineEmits<{ toggle: [OccurrenceDto, number | null] }>()

const { bonus: bonusStars, dayBonus: completeDayStars, selection, range, peopleFilter, toggle, assign, save, remove, reload, error: writeError } = useTasks()

/*
 * A device writes behind the PIN, an account only with the permission. An
 * account also says who checks off — nobody is asked — and the server's
 * `can` decides what may be ticked.
 */
const { kind, personId: actorPersonId, can, write } = useActor()
const signedIn = computed(() => kind.value === 'user')
const mayCreate = computed(() => can('tasks.manage'))
const mayManage = computed(() => can('tasks.manage'))
const maySwap = computed(() => can('tasks.manage'))

/*
 * Extra stars from a child's column, the same booking as in the stars area.
 * The PIN comes when saving; the gate repeats the call, so the reason typed
 * before survives it.
 */
const bonusIds = computed(() => props.points && can('points.award')
  ? props.people.filter(person => person.role === 'child').map(person => person.id)
  : [])
const bonusFor = ref<AgendaPerson | null>(null)
const bonusBusy = ref(false)
const bonusError = ref<string | null>(null)

function openBonus(personId: number): void {
  bonusError.value = null
  bonusFor.value = props.people.find(person => person.id === personId) ?? null
}

async function giveBonus(bonus: { points: number, reason: string }): Promise<void> {
  const person = bonusFor.value

  if (person === null) {
    return
  }

  if (isOffline()) {
    bonusError.value = t('network.offline')

    return
  }

  bonusBusy.value = true

  try {
    await write(() => $fetch<unknown>('/api/wall/points', {
      baseURL: '/',
      method: 'POST',
      credentials: 'same-origin',
      body: { person_id: person.id, points: bonus.points, reason: bonus.reason },
    }))
    bonusFor.value = null
    await reload()
  }
  catch {
    bonusError.value = t('common.failed')
  }
  finally {
    bonusBusy.value = false
  }
}


/*
 * List or day. The setting lives in the device's preferences: whoever
 * switches also sets how the tablet comes up after a power cut.
 */
const { preferences, set: setPreference } = usePreferences()

const view = computed<'list' | 'day'>({
  get: () => (preferences.value.task_view === 'list' ? 'list' : 'day'),
  set: value => void setPreference('task_view', value),
})

/*
 * Done tasks: hidden by default, so what is left stands alone. Stored with
 * the view, so the tablet comes up the way it was left.
 */
const showDone = computed({
  get: () => preferences.value.task_done === 'shown',
  set: value => void setPreference('task_done', value ? 'shown' : 'hidden'),
})

/** The day of the column view. The list shows everything anyway. */
const day = ref(props.today)

/** How far back the day view looks: what happened, not an archive. */
const HISTORY_DAYS = 28

function page(offset: number): void {
  day.value = shiftedDay(
    day.value,
    offset,
    shiftedDay(props.today, -HISTORY_DAYS, '', props.today),
    range.value?.until ?? props.today,
  )
}

/*
 * A past day is history: what was left open, what got done. On the wall it
 * is read only, the server refuses a late tick anyway; an account may catch
 * up on what was forgotten. It is fetched on its own, so the area's range
 * (today onwards, reloaded on every sync) stays as it is.
 */
const past = computed(() => day.value < props.today)
const history = ref<{ day: string, occurrences: OccurrenceDto[], bonus: BonusDto[] } | null>(null)
const historyError = ref<string | null>(null)

watch(day, loadHistory)

async function loadHistory(iso: string): Promise<void> {
  if (iso >= props.today) {
    return
  }

  try {
    const data = await $fetch<{ occurrences: OccurrenceDto[], bonus?: BonusDto[] }>('/api/wall/tasks', {
      params: { from: iso, to: iso },
      baseURL: '/',
      credentials: 'same-origin',
    })

    // Only the day still shown draws: a quick tap back and forth leaves answers behind.
    if (day.value === iso) {
      history.value = { day: iso, occurrences: data.occurrences, bonus: data.bonus ?? [] }
      historyError.value = null
    }
  }
  catch {
    historyError.value = t('tasks.loadFailed')
  }
}

const dayOccurrences = computed(() => past.value
  ? (history.value?.day === day.value ? history.value.occurrences : [])
  : props.occurrences)
const dayBonus = computed(() => past.value ? (history.value?.bonus ?? []) : bonusStars.value)

const dayTitle = computed(() => day.value === props.today
  ? t('common.today')
  : formatIsoDate(day.value, { weekday: 'long', day: 'numeric', month: 'long' }))

// Whose tasks show, chosen in the bar on top as in the calendar.

function togglePerson(id: number | null): void {
  peopleFilter.value = peopleFilter.value.includes(id)
    ? peopleFilter.value.filter(entry => entry !== id)
    : [...peopleFilter.value, id]
}

/*
 * On a phone the options fold into one sheet behind a filter button: they
 * took five rows above the tasks. The dot on the button tells that
 * something is hidden or shown that usually is not.
 */
const narrow = useNarrow()
const filterOpen = ref(false)
useEscape(() => { filterOpen.value = false }, () => filterOpen.value)
const filtered = computed(() => peopleFilter.value.length > 0 || showDone.value)

const visiblePeople = computed(() => props.people.filter(person => shownFor(peopleFilter.value, person.id)))
const visibleOccurrences = computed(() => props.occurrences.filter(entry => shownFor(peopleFilter.value, entry.person_id)))
const dayVisible = computed(() => dayOccurrences.value.filter(entry => shownFor(peopleFilter.value, entry.person_id)))
const unassigned = computed(() => dayOccurrences.value.some(entry => entry.person_id === null))
const progress = computed(() => dayProgress(dayOccurrences.value, day.value))

/** The occurrence whose assignee is being swapped. Null means closed. */
const swap = ref<OccurrenceDto | null>(null)
useEscape(() => { swap.value = null }, () => swap.value !== null)

/*
 * Check off first, then ask.
 *
 * No signed-in person stands at the wall, and a completion carries stars —
 * so someone has to say whom they belong to. But nobody expected to pick who
 * they are first: you tap the task you did. The question comes afterwards,
 * and if an assignee is set it is answered with one tap.
 */
const asked = ref<OccurrenceDto | null>(null)

/**
 * From the column view. The column's owner is fixed — on the phone too,
 * where a parent catches up on a child's task — except for "Für alle":
 * there it asks as in the list, otherwise a child could get stars for a
 * sibling.
 */
function fromColumn(entry: OccurrenceDto, personId: number | null): void {
  if (personId === null || entry.done) {
    complete(entry)

    return
  }

  tick(entry, personId)
}

/*
 * The area's state knows today onwards; a late tick changes the day fetched
 * on its own, so that day is fetched again — with its day bonus.
 */
function tick(entry: OccurrenceDto, personId: number | null): void {
  if (entry.date >= props.today) {
    emit('toggle', entry, personId)

    return
  }

  void toggle(entry, personId).then(() => loadHistory(entry.date))
}

function complete(entry: OccurrenceDto): void {
  // Reverting needs no name: it deletes the completion instead of
  // crediting someone.
  if (entry.done) {
    tick(entry, null)

    return
  }

  /*
   * Signed in: whose task it is, otherwise the person holding it. A parent
   * catching up on a child's forgotten task means the child's stars.
   */
  if (signedIn.value) {
    tick(entry, entry.for_person_id ?? entry.person_id ?? actorPersonId.value)

    return
  }

  // A task per person already says whose it is; no question needed.
  if (entry.for_person_id !== null) {
    tick(entry, entry.for_person_id)

    return
  }

  asked.value = entry
}

function completedBy(personId: number | null): void {
  const entry = asked.value

  asked.value = null

  if (entry !== null) {
    tick(entry, personId)
  }
}

/*
 * On the wall creating, changing and removing sit behind the PIN: `withPin`
 * in `useTasks` repeats the call after unlocking, and the form closes only
 * once it went through.
 */
const formOpen = ref(false)
const editing = ref<OccurrenceDto | null>(null)
const saving = ref(false)

/**
 * The task tapped for reading. `personId` is the column it was tapped in;
 * undefined means the list, where checking off still asks who it was.
 */
const details = ref<{ entry: OccurrenceDto, personId: number | null | undefined } | null>(null)

function completeFromDetails(): void {
  const current = details.value

  details.value = null

  if (current === null) {
    return
  }

  if (current.personId === undefined) {
    complete(current.entry)
  }
  else {
    fromColumn(current.entry, current.personId)
  }
}

/*
 * Without stars and without asking who: nobody did it. The day bonus
 * is settled on the server as for any tick.
 */
function skipFromDetails(): void {
  const current = details.value
  details.value = null

  if (current === null) {
    return
  }

  const day = current.entry.date
  void toggle(current.entry, null, true).then(() => {
    if (day < props.today) {
      loadHistory(day)
    }
  })
}

function editFromDetails(): void {
  const current = details.value

  details.value = null

  if (current !== null) {
    editTask(current.entry)
  }
}

/** Preselected when creating; a column's plus means its person. */
const defaultPerson = ref<number | null>(null)

function newTask(personId: number | null = null): void {
  defaultPerson.value = personId
  editing.value = null
  writeError.value = null
  formOpen.value = true
}

function editTask(entry: OccurrenceDto): void {
  editing.value = entry
  writeError.value = null
  formOpen.value = true
}

async function saveTask(input: TaskInput): Promise<void> {
  saving.value = true
  formOpen.value = !await save(input, editing.value?.task_id ?? null)
  saving.value = false
}

async function removeTask(): Promise<void> {
  const id = editing.value?.task_id

  if (id !== undefined) {
    formOpen.value = !await remove(id)
  }
}

const color = (value: string | null): string => value ?? '#8a8276'

const confetti = ref(false)

const familyColors = computed(() => props.people
  .map(person => person.color)
  .filter((value): value is string => value !== null))

/*
 * The transition is celebrated, not the state.
 *
 * What is watched is a string of day and check marks instead of the list
 * itself: whether the parent replaces the array or mutates it makes no
 * difference to the result.
 */
const wasDone = ref(isDone(props.occurrences, props.today))

watch(
  () => props.occurrences.map(entry => `${entry.date}:${entry.done}`).join('|'),
  () => {
    const now = isDone(props.occurrences, props.today)

    if (now && !wasDone.value) {
      confetti.value = true
    }

    wasDone.value = now
  },
)

const days = computed(() => {
  const groups = new Map<string, OccurrenceDto[]>()

  for (const entry of visibleOccurrences.value) {
    const present = groups.get(entry.date)

    if (present) {
      present.push(entry)
    }
    else {
      groups.set(entry.date, [entry])
    }
  }

  return [...groups.entries()].map(([iso, entries]) => ({
    iso,
    title: iso === props.today
      ? t('common.today')
      : formatIsoDate(iso, { weekday: 'long', day: 'numeric', month: 'long' }),
    entries: openFirst(entries),
    shown: openFirst(showDone.value ? entries : entries.filter(entry => !entry.done)),
    openCount: entries.filter(entry => !entry.done).length,
    done: entries.every(entry => entry.done),
  }))
})

/** Secondary details in a quiet second line, so the title carries the row. */
function inBackground(entry: OccurrenceDto): string {
  return [
    daypartText(entry),
    entry.recurrence,

    // Where it comes from: nobody here created "Zahnarzt anrufen" from Dad's
    // list, and that also explains why it carries no stars.
    entry.origin,
    entry.done_by ? t('tasks.doneBy', { name: entry.done_by }) : null,
  ].filter(Boolean).join(' · ')
}

async function choose(entry: OccurrenceDto, personId: number | null): Promise<void> {
  if (await assign(entry, personId)) {
    swap.value = null
  }
}
</script>

<template>
  <!-- In the day view the columns take only what the toolbar leaves: the
       "for everyone" strip below them must stay in sight. On the wall it ends
       at the area's own margin — no second gap below it. -->
  <div class="h-full overflow-y-auto pb-6" :class="view === 'day' ? 'sm:flex sm:flex-col sm:pb-0' : ''">
    <TwConfetti v-if="confetti" :colors="familyColors" @finished="confetti = false" />

    <!-- Fixed on top only where there is height to spare: on a phone it took
         more than half the screen from the tasks. -->
    <div v-if="narrow" class="mb-3 flex items-center gap-2">
      <div class="flex min-w-0 flex-1 items-center gap-1 rounded-2xl bg-surface-2 p-1">
        <button
          v-if="view === 'day'"
          type="button"
          :aria-label="$t('calendar.page.dayBack')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2"
          @click="page(-1)"
        >
          <TwIcon name="back" :size="20" />
        </button>

        <span data-testid="day-title" class="min-h-11 min-w-0 flex-1 truncate text-center text-base leading-11 font-semibold capitalize">
          {{ view === 'day' ? dayTitle : $t('tasks.views.list') }}
        </span>

        <button
          v-if="view === 'day'"
          type="button"
          :aria-label="$t('calendar.page.dayForward')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2"
          @click="page(1)"
        >
          <TwIcon name="forward" :size="20" />
        </button>
      </div>

      <button
        type="button"
        data-testid="task-filter"
        :data-active="filtered"
        :aria-label="filtered ? $t('tasks.filters.filterActive') : $t('tasks.filters.filter')"
        class="relative flex size-13 shrink-0 items-center justify-center rounded-2xl bg-surface-2 text-ink-2"
        @click="filterOpen = true"
      >
        <TwIcon name="filter" :size="22" />
        <span v-if="filtered" aria-hidden="true" class="absolute top-2.5 right-2.5 size-2.5 rounded-full bg-accent" />
      </button>

      <button
        v-if="mayCreate"
        type="button"
        data-testid="task-new"
        :aria-label="$t('task.new')"
        class="flex size-13 shrink-0 items-center justify-center rounded-2xl bg-accent text-accent-ink"
        @click="newTask()"
      >
        <TwIcon name="plus" :size="24" />
      </button>
    </div>

    <p
      v-if="narrow && (error || writeError || (past && historyError))"
      role="alert"
      class="mb-3 flex items-center gap-2 rounded-2xl bg-warning px-4 py-2.5 text-base font-medium text-warning-ink"
    >
      <TwIcon name="warning" :size="18" />
      {{ error ?? writeError ?? (past ? historyError : null) }}
    </p>

    <div v-if="!narrow" class="sticky top-0 z-10 mb-4 flex flex-wrap items-center gap-3 bg-canvas pb-3">
      <div role="group" :aria-label="$t('calendar.view')" class="flex gap-1 rounded-2xl bg-surface-2 p-1">
        <button
          v-for="(label, value) in { list: $t('tasks.views.list'), day: $t('calendar.views.day') }"
          :key="value"
          type="button"
          data-testid="task-view"
          :aria-pressed="view === value"
          class="min-h-11 rounded-xl px-4 text-base font-semibold transition-colors"
          :class="view === value ? 'bg-surface text-ink shadow-card' : 'text-ink-2 hover:text-ink'"
          @click="view = value as 'list' | 'day'"
        >
          {{ label }}
        </button>
      </div>

      <div v-if="view === 'day'" class="flex items-center gap-1 rounded-2xl bg-surface-2 p-1">
        <button
          type="button"
          :aria-label="$t('calendar.page.dayBack')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
          @click="page(-1)"
        >
          <TwIcon name="back" :size="20" />
        </button>

        <span data-testid="day-title" class="min-w-40 text-center text-base font-semibold capitalize">
          {{ dayTitle }}
        </span>

        <button
          type="button"
          :aria-label="$t('calendar.page.dayForward')"
          class="flex size-11 items-center justify-center rounded-xl text-ink-2 transition-colors hover:bg-surface hover:text-ink"
          @click="page(1)"
        >
          <TwIcon name="forward" :size="20" />
        </button>
      </div>

      <button
        type="button"
        data-testid="show-done"
        :aria-pressed="showDone"
        class="flex min-h-11 items-center gap-2 rounded-2xl bg-surface-2 px-4 text-base font-semibold transition-colors"
        :class="showDone ? 'text-ink' : 'text-ink-2 hover:text-ink'"
        @click="showDone = !showDone"
      >
        <TwIcon :name="showDone ? 'eye' : 'eyeOff'" :size="20" />
        {{ $t('tasks.showDone') }}
      </button>

      <button
        v-if="mayCreate"
        type="button"
        data-testid="task-new"
        class="ml-auto flex min-h-12 items-center gap-2 rounded-full bg-accent px-5 text-lg font-semibold text-accent-ink transition-opacity hover:opacity-90"
        @click="newTask()"
      >
        <TwIcon name="plus" :size="20" />
        {{ $t('task.new') }}
      </button>

      <PeopleBar
        class="w-full"
        :people="people"
        :selection="peopleFilter"
        :progress="progress"
        :family="unassigned"
        :label="$t('tasks.showTasks')"
        :family-label="$t('tasks.forEveryone')"
        @toggle="togglePerson"
      />

      <p
        v-if="error || writeError || (past && historyError)"
        role="alert"
        class="flex w-full items-center gap-2 rounded-2xl bg-warning px-4 py-2.5 text-base font-medium text-warning-ink"
      >
        <TwIcon name="warning" :size="18" />
        {{ error ?? writeError ?? (past ? historyError : null) }}
      </p>
    </div>

    <!-- The phone's options, as a sheet from below. -->
    <div
      v-if="filterOpen"
      class="fixed inset-0 z-30 flex items-end bg-scrim"
      @click.self="filterOpen = false"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="task-filter-title"
        data-testid="task-filter-sheet"
        class="max-h-[85svh] w-full space-y-5 overflow-y-auto rounded-t-[2rem] bg-surface px-5 pt-6 pb-[calc(1.25rem+env(safe-area-inset-bottom))] shadow-floating"
      >
        <h2 id="task-filter-title" class="font-display text-2xl font-semibold">
          {{ $t('tasks.filters.filter') }}
        </h2>

        <div role="group" :aria-label="$t('calendar.view')" class="flex gap-1 rounded-2xl bg-surface-2 p-1">
          <button
            v-for="(label, value) in { list: $t('tasks.views.list'), day: $t('calendar.views.day') }"
            :key="value"
            type="button"
            data-testid="task-view"
            :aria-pressed="view === value"
            class="min-h-12 flex-1 rounded-xl text-base font-semibold transition-colors"
            :class="view === value ? 'bg-surface text-ink shadow-card' : 'text-ink-2'"
            @click="view = value as 'list' | 'day'"
          >
            {{ label }}
          </button>
        </div>

        <button
          type="button"
          role="switch"
          data-testid="show-done"
          :aria-checked="showDone"
          class="flex min-h-12 w-full items-center gap-3 rounded-2xl bg-surface-2 px-4 py-3 text-left"
          @click="showDone = !showDone"
        >
          <span class="flex h-7 w-12 shrink-0 items-center rounded-full p-1 transition-colors" :class="showDone ? 'bg-accent' : 'bg-line'">
            <span class="size-5 rounded-full bg-surface transition-transform" :class="showDone ? 'translate-x-5' : ''" />
          </span>
          <span class="text-lg font-semibold">{{ $t('tasks.showDone') }}</span>
        </button>

        <div v-if="people.length > 0">
          <div class="mb-2 flex items-center justify-between gap-3">
            <span class="text-base font-semibold text-ink-2">{{ $t('tasks.filters.people') }}</span>
            <button
              v-if="peopleFilter.length > 0"
              type="button"
              data-testid="people-all"
              class="min-h-11 rounded-xl px-3 text-base font-semibold text-accent"
              @click="peopleFilter = []"
            >
              {{ $t('tasks.filters.showAll') }}
            </button>
          </div>

          <PeopleBar
            :people="people"
            :selection="peopleFilter"
            :progress="progress"
            :family="unassigned"
            :label="$t('tasks.showTasks')"
            :family-label="$t('tasks.forEveryone')"
            @toggle="togglePerson"
          />
        </div>

        <button
          type="button"
          data-testid="task-filter-close"
          class="min-h-14 w-full rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
          @click="filterOpen = false"
        >
          {{ $t('tasks.filters.done') }}
        </button>
      </div>
    </div>

    <p
      v-if="view === 'day' && past"
      data-testid="past-day"
      class="mb-3 flex items-center gap-2 rounded-2xl bg-surface-2 px-4 py-2.5 text-base font-medium text-ink-2"
    >
      <TwIcon name="info" :size="18" />
      {{ signedIn ? $t('tasks.pastDayLate') : $t('tasks.pastDay') }}
    </p>

    <!-- Until the first answer: an empty view would say "nothing to do". -->
    <TwLoading v-if="range === null && !error" />

    <DayColumns
      v-else-if="view === 'day'"
      class="min-h-0 flex-1"
      :occurrences="dayVisible"
      :people="visiblePeople"
      :day="day"
      :today="today"
      :can-create="mayCreate && !past"
      :hide-done="!showDone"
      :bonus-ids="past ? [] : bonusIds"
      :bonus="dayBonus"
      :day-bonus="completeDayStars"
      @create="newTask"
      @bonus="openBonus"
      @open="(entry, personId) => details = { entry, personId }"
    />

    <div
      v-else-if="days.length === 0"
      class="flex flex-col items-center rounded-3xl border-2 border-dashed border-line px-6 py-20 text-center"
    >
      <TwIcon name="tasks" :size="44" class="text-ink-3" />
      <p class="mt-4 font-display text-3xl font-semibold">
        {{ $t('tasks.nothing') }}
      </p>
      <p class="mt-1 text-lg text-ink-3">
        {{ $t('tasks.noneAhead') }}
      </p>

      <button
        v-if="mayCreate"
        type="button"
        data-testid="task-new"
        class="mt-5 flex min-h-13 items-center gap-2 rounded-2xl bg-accent px-6 text-lg font-semibold text-accent-ink"
        @click="newTask()"
      >
        <TwIcon name="plus" :size="20" />
        {{ $t('task.new') }}
      </button>
    </div>

    <template v-if="view === 'list'">
      <section
        v-for="group in days"
        :key="group.iso"
        class="mb-8"
        :aria-labelledby="`aufgaben-${group.iso}`"
      >
      <h2
        :id="`aufgaben-${group.iso}`"
        class="mb-3 flex items-baseline gap-3 font-display text-2xl font-semibold capitalize"
      >
        {{ group.title }}
        <span v-if="!group.done" class="font-sans text-base font-medium text-ink-3 normal-case">
          {{ $t('tasks.openCount', { n: group.openCount }) }}
        </span>
      </h2>

      <!--
        The praise stands large and before the tasks, not small beside them:
        whoever it is meant for does not read in passing yet.
      -->
      <p
        v-if="group.done"
        data-testid="done"
        role="status"
        class="mb-3 flex items-center gap-4 rounded-3xl bg-success/15 px-6 py-4"
      >
        <span aria-hidden="true" class="text-4xl">🎉</span>
        <span>
          <span class="block font-display text-2xl font-semibold text-success">{{ $t('tasks.allDone') }}</span>
          <span class="block text-base text-ink-2">
            {{ $t('tasks.allDoneHint', group.entries.length) }}
          </span>
        </span>
      </p>

      <ul class="grid gap-2.5 xl:grid-cols-2">
        <li
          v-for="entry in group.shown"
          :key="occurrenceKey(entry)"
          data-testid="task"
          :data-occurrence="occurrenceKey(entry)"
          class="flex items-center gap-2 rounded-2xl bg-surface p-2 pr-3 shadow-card transition-opacity"
          :class="entry.done ? 'opacity-65' : ''"
        >
          <!-- A tap opens the task; checking off is confirmed there, so a
               brush in passing ticks nothing. -->
          <button
            type="button"
            data-testid="task-open"
            :aria-label="entry.done ? $t('tasks.doneNamed', { name: entry.title }) : entry.title"
            class="flex min-h-14 min-w-0 flex-1 items-center gap-4 rounded-xl pl-2 pr-2 text-left"
            @click="details = { entry, personId: undefined }"
          >
            <!-- Icon on the first line, not in the middle of a long title. -->
            <span class="flex min-w-0 flex-1 items-start gap-4">
              <span v-if="entry.icon" aria-hidden="true" class="shrink-0 text-3xl leading-8">
                <TwEmoji :icon="entry.icon" />
              </span>

              <span class="min-w-0 flex-1">
                <span class="block text-xl leading-8 font-medium break-words" :class="entry.done ? 'text-ink-3 line-through' : ''">
                  {{ entry.title }}
                </span>
                <span v-if="inBackground(entry)" class="block text-sm text-ink-3">
                  {{ inBackground(entry) }}
                </span>
              </span>
            </span>

            <!--
              The pill turns gold as soon as the check mark is set: what it
              earned shows where it was earned — not only in the stars area.
              The star itself is drawn as the device is set up.
            -->
            <span
              v-if="entry.points > 0"
              :aria-label="$t('task.stars', entry.points)"
              class="flex shrink-0 items-center gap-1 rounded-full px-2.5 py-1 text-base font-semibold transition-colors"
              :class="entry.done
                ? 'bg-points text-points-ink'
                : 'bg-surface-2 text-ink-3'"
            >
              <TwStar />
              {{ entry.points }}
            </span>

            <!-- A mark, not a control: undoing goes through the task too. -->
            <span
              v-if="entry.done"
              data-testid="done-mark"
              aria-hidden="true"
              class="flex size-9 shrink-0 items-center justify-center rounded-full text-white"
              :style="{ backgroundColor: color(entry.color) }"
            >
              <TwIcon name="check" :size="20" />
            </span>
          </button>

          <!--
            With a rotation the assignee is tappable: if the timetable
            changes, you swap one week without shifting the order.
          -->
          <button
            v-if="entry.rotating && maySwap"
            type="button"
            data-testid="assignee"
            :aria-label="$t('tasks.swapAssignee', { name: entry.person_name ?? $t('tasks.open') })"
            class="flex min-h-11 shrink-0 items-center gap-2 rounded-full px-4 text-base font-semibold text-ink transition-[filter] hover:brightness-95"
            :style="{ background: tint(color(entry.color) + '2e') }"
            @click="swap = entry"
          >
            <TwAvatar
              v-if="entry.person_name"
              :name="entry.person_name"
              :color="entry.color"
              :avatar="entry.avatar"
              :size="26"
            />
            <TwIcon v-else name="swap" :size="18" />
            {{ entry.person_name ?? $t('tasks.open') }}
          </button>

          <span v-else-if="entry.person_name" class="flex shrink-0 items-center gap-2 px-2 text-base font-medium text-ink-2">
            <TwAvatar
              :name="entry.person_name"
              :color="entry.color"
              :avatar="entry.avatar"
              :size="26"
            />
            {{ entry.person_name }}
          </span>

          <!-- Only own tasks: what comes from another service belongs there,
               and the next sync would pull it back anyway. -->
          <button
            v-if="mayManage && entry.origin === null"
            type="button"
            data-testid="task-edit"
            :aria-label="$t('tasks.editNamed', { name: entry.title })"
            class="flex size-11 shrink-0 items-center justify-center rounded-full text-ink-3 transition-colors hover:bg-surface-2 hover:text-ink"
            @click="editTask(entry)"
          >
            <TwIcon name="pencil" :size="18" />
          </button>
          </li>
        </ul>
      </section>
    </template>

    <div
      v-if="swap"
      class="fixed inset-0 z-30 flex items-center justify-center bg-scrim p-6"
      @click.self="swap = null"
    >
      <div
        role="dialog"
        aria-modal="true"
        aria-labelledby="swap-title"
        class="w-full max-w-lg rounded-3xl bg-surface p-7 shadow-floating"
      >
        <h2 id="swap-title" class="font-display text-3xl font-semibold">
          {{ $t('tasks.whoseTurn') }}
        </h2>

        <p class="mt-1 text-lg text-ink-2">
          {{ swap.title }} —
          {{ formatIsoDate(swap.date, { weekday: 'long', day: 'numeric', month: 'long' }) }}
        </p>

        <div class="mt-6 grid grid-cols-2 gap-2.5">
          <button
            v-for="person in people"
            :key="person.id"
            type="button"
            data-testid="swap-person"
            :aria-pressed="swap.person_id === person.id"
            class="flex min-h-14 items-center gap-3 rounded-2xl bg-surface-2 px-4 text-lg font-semibold transition-colors hover:bg-surface-3"
            :style="swap.person_id === person.id ? { boxShadow: `inset 0 0 0 2px ${color(person.color)}` } : undefined"
            @click="swap && choose(swap, person.id)"
          >
            <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="32" />
            {{ person.name }}
          </button>
        </div>

        <!-- Back to the rule, not "assign to nobody": the deviation disappears,
             and whoever's turn it would be anyway applies again. -->
        <button
          type="button"
          data-testid="swap-back"
          class="mt-3 flex min-h-13 w-full items-center justify-center gap-2 rounded-2xl border-2 border-line text-lg font-semibold transition-colors hover:bg-surface-2"
          @click="swap && choose(swap, null)"
        >
          <TwIcon name="refresh" :size="20" />
          {{ $t('tasks.backToRotation') }}
        </button>

        <button
          type="button"
          class="mt-2 min-h-12 w-full rounded-2xl text-lg font-medium text-ink-2 transition-colors hover:bg-surface-2"
          @click="swap = null"
        >
          {{ $t('common.cancel') }}
        </button>
      </div>
    </div>

    <TaskDialog
      v-if="formOpen"
      :key="editing?.task_id ?? 'new'"
      :task="editing"
      :default-person="defaultPerson"
      :people="people"
      :weekdays="selection.weekdays"
      :dayparts="selection.dayparts"
      :today="today"
      :busy="saving"
      :error="writeError"
      @close="formOpen = false"
      @save="saveTask"
      @remove="removeTask"
    />

    <TaskDetails
      v-if="details"
      :entry="details.entry"
      :can-edit="signedIn && mayManage && details.entry.origin === null"
      :can-skip="mayManage && (signedIn || details.entry.date >= today)"
      :can-complete="signedIn ? details.entry.can.complete : details.entry.date >= today"
      :past="!signedIn && details.entry.date < today"
      @close="details = null"
      @complete="completeFromDetails"
      @skip="skipFromDetails"
      @edit="editFromDetails"
    />

    <CompletionDialog
      v-if="asked"
      :entry="asked"
      :people="people"
      @close="asked = null"
      @confirm="completedBy"
    />

    <BonusDialog
      v-if="bonusFor"
      :name="bonusFor.name"
      :busy="bonusBusy"
      :error="bonusError"
      @close="bonusFor = null"
      @save="giveBonus"
    />
  </div>
</template>
