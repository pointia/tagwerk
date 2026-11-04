<script setup lang="ts">
import { occurrenceKey } from '../utils/task'
import type { AgendaPerson } from '../utils/person'
import type { BonusDto, OccurrenceDto } from '../composables/useTasks'
import type { DayColumn } from '../utils/dayColumns'
import { formatIsoDate } from '../utils/calendar'
import { dayColumns } from '../utils/dayColumns'
import { celebration } from '../utils/celebration'
import { tint } from '../utils/tint'

const { t } = useI18n()

/**
 * One day, one column per person.
 *
 * Whoever checks off here does not have to pick their name — the column
 * already says it. That is the real gain over the list. Checking off goes
 * through the task itself: a tap opens it, and it is confirmed there.
 */
interface Props {
  occurrences: OccurrenceDto[]
  people: AgendaPerson[]
  day: string
  today: string
  /** False on a phone whose person may not create tasks. */
  canCreate?: boolean
  /** Done tasks stay out of the column; "Super!" and the counts still know them. */
  hideDone?: boolean
  /** People whose column offers extra stars — children, if stars may be given. */
  bonusIds?: number[]
  /** Extra stars per person and day; they count with what was earned elsewhere. */
  bonus?: BonusDto[]
  /** Stars for finishing all of one's own tasks of the day; 0 shows no bar. */
  dayBonus?: number
}

const props: Props = withDefaults(defineProps<Props>(), { canCreate: true, hideDone: false, bonusIds: () => [], dayBonus: 0 })

/*
 * The day bonus as a bar that fills: a child sees how close the gift is,
 * not only that one exists. Only where the column has tasks of its own —
 * an empty day earns nothing.
 */
const showsDayBonus = (column: DayColumn): boolean =>
  (props.dayBonus ?? 0) > 0 && column.personId !== null && column.entries.length > 0

const doneShare = (column: DayColumn): number =>
  (column.entries.length - column.openCount) / column.entries.length

const shown = (column: DayColumn): OccurrenceDto[] => props.hideDone
  ? column.entries.filter(entry => !entry.done)
  : column.entries

const emit = defineEmits<{
  create: [number | null]
  open: [OccurrenceDto, number | null]
  bonus: [number]
}>()

const columns = computed(() => dayColumns(props.occurrences, props.people, props.day, t('tasks.forEveryone'), props.bonus))

/*
 * "Für alle" belongs to nobody, so on the wall it gets no column of its own:
 * as a strip across the full width below, it leaves the people their width.
 * A phone shows one column at a time — there a strip would take the height
 * the columns need, so it becomes the last column to swipe to.
 */
const personColumns = computed(() => columns.value.filter(column => column.personId !== null))
const everyone = computed(() => columns.value.find(column => column.personId === null) ?? null)

const narrow = useNarrow()

const swipeColumns = computed(() => narrow.value && everyone.value !== null
  ? [...personColumns.value, everyone.value]
  : personColumns.value)

const color = (value: string | null): string => value ?? '#8a8276'

/*
 * The column very light, the task in it a bit stronger: the colour should
 * carry the assignment, not cost readability.
 */
const columnBackground = (column: DayColumn): string => color(column.color) + '1f'

const heading = computed(() => props.day === props.today
  ? t('common.today')
  : formatIsoDate(props.day, { weekday: 'long', day: 'numeric', month: 'long' }))
</script>

<template>
  <div class="flex min-h-0 flex-col gap-3 sm:h-full">
    <!-- On a narrow phone one column fills the width; the next is a swipe away.
         There the page scrolls, not the column: a column as high as what is
         left below the toolbar was a sliver. -->
    <div class="relative flex snap-x snap-mandatory gap-3 overflow-x-auto overscroll-x-contain sm:min-h-0 sm:flex-1 sm:snap-none">
      <section
        v-for="column in swipeColumns"
        :key="column.personId ?? 'everyone'"
        data-testid="day-column"
        :aria-label="column.name"
        class="flex min-h-[55svh] min-w-[85%] flex-1 snap-start flex-col rounded-3xl p-3 sm:min-h-0 sm:min-w-64"
        :style="{ background: tint(columnBackground(column)) }"
      >
        <header class="mb-3 flex items-center gap-2.5 px-1">
          <TwAvatar
            v-if="column.personId !== null"
            :name="column.name"
            :color="column.color"
            :avatar="column.avatar"
            :size="40"
          />

          <span class="min-w-0 flex-1">
            <span class="block truncate font-display text-xl font-semibold">{{ column.name }}</span>

            <span class="flex items-center gap-2 text-sm font-medium text-ink-2">
              <span
                data-testid="column-open"
                class="rounded-full bg-surface px-2 py-0.5 tabular-nums"
              >{{ $t('tasks.openCount', { n: column.openCount }) }}</span>

              <span
                v-if="column.sterne > 0 || column.elsewhere !== 0"
                data-testid="column-stars"
                class="tabular-nums"
                :aria-label="$t('tasks.starsToday', column.sterne + column.elsewhere)"
              >
                <TwStar /> {{ column.sterne }}<span
                  v-if="column.elsewhere !== 0"
                  :title="$t('tasks.starsElsewhere', column.elsewhere)"
                  class="font-bold text-ink"
                >{{ column.elsewhere > 0 ? '+' : '−' }}{{ Math.abs(column.elsewhere) }}</span>
              </span>
            </span>
          </span>

          <!-- Extra stars right where the child's day is: no switch to the
               stars area for a quick "well done". -->
          <button
            v-if="column.personId !== null && props.bonusIds?.includes(column.personId)"
            type="button"
            data-testid="column-bonus"
            :aria-label="$t('points.bonusFor', { name: column.name })"
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-points-ink transition-colors hover:text-ink"
            @click="emit('bonus', column.personId)"
          >
            <TwIcon name="points" :size="20" />
          </button>

          <!-- The column's plus creates for this person: whoever looks at the
               child's column thinks of something missing there. -->
          <button
            v-if="props.canCreate"
            type="button"
            data-testid="column-new"
            :aria-label="$t('tasks.newFor', { name: column.name })"
            class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-ink-2 transition-colors hover:text-ink"
            @click="emit('create', column.personId)"
          >
            <TwIcon name="plus" :size="20" />
          </button>
        </header>

        <div
          v-if="showsDayBonus(column)"
          data-testid="column-day-bonus"
          :data-earned="column.done"
          class="mb-3 flex items-center gap-2 px-1"
        >
          <span class="sr-only">{{ column.done
            ? $t('tasks.dayBonusEarned', { n: props.dayBonus }, props.dayBonus ?? 0)
            : $t('tasks.dayBonusOpen', { n: props.dayBonus, done: column.entries.length - column.openCount, total: column.entries.length }, props.dayBonus ?? 0) }}</span>

          <span aria-hidden="true" class="h-2.5 flex-1 overflow-hidden rounded-full bg-surface">
            <span
              class="block h-full rounded-full transition-[width] duration-500"
              :style="{ width: `${Math.round(doneShare(column) * 100)}%`, backgroundColor: color(column.color) }"
            />
          </span>

          <span
            aria-hidden="true"
            class="flex shrink-0 items-center gap-1 rounded-full px-2.5 py-0.5 text-sm font-bold tabular-nums transition-colors"
            :class="column.done ? 'tw-pop bg-points text-points-ink' : 'bg-surface text-ink-2'"
          >🎁 +{{ props.dayBonus }} <TwStar /></span>
        </div>

        <p
          v-if="column.done"
          data-testid="column-done"
          role="status"
          class="mb-2 flex flex-col items-center rounded-2xl bg-surface/70 py-4"
        >
          <CelebrationFace :face="celebration(props.day, column.personId).face" class="mb-2 text-7xl" />
          <span class="tw-pop font-display text-2xl font-bold text-success [animation-delay:400ms]">{{ $t(celebration(props.day, column.personId).praise) }}</span>
        </p>

        <ul class="flex-1 space-y-2 sm:min-h-0 sm:overflow-y-auto">
          <ColumnTask
            v-for="entry in shown(column)"
            :key="occurrenceKey(entry)"
            :entry="entry"
            :color="color(column.color)"
            @open="emit('open', entry, column.personId)"
          />

          <!-- Nothing to do is good news too: a big face, not a grey line. -->
          <li v-if="column.entries.length === 0" data-testid="column-empty" class="flex h-full flex-col items-center justify-center px-2 py-6 text-center">
            <span aria-hidden="true" class="inline-block text-6xl leading-none">
              {{ celebration(props.day, column.personId).face }}
            </span>
            <span class="mt-3 text-base font-semibold text-ink-3">{{ $t('tasks.nothing') }}</span>
          </li>
        </ul>
      </section>

      <p
        v-if="personColumns.length === 0 && everyone === null"
        class="w-full self-center text-center font-display text-2xl text-ink-3"
      >
        {{ props.day === props.today ? $t('tasks.nobodyToday') : $t('tasks.nobodyOn', { day: heading }) }}
      </p>
    </div>

    <section
      v-if="everyone && !narrow"
      data-testid="day-column"
      :aria-label="everyone.name"
      class="flex shrink-0 flex-col gap-2 rounded-3xl p-3 sm:flex-row sm:items-center"
      :style="{ background: tint(columnBackground(everyone)) }"
    >
      <header class="flex shrink-0 items-center gap-2.5 px-1 sm:w-52">
        <span class="min-w-0 flex-1">
          <span class="block truncate font-display text-xl font-semibold">{{ everyone.name }}</span>
          <span
            data-testid="column-open"
            class="inline-block rounded-full bg-surface px-2 py-0.5 text-sm font-medium text-ink-2 tabular-nums"
          >{{ $t('tasks.openCount', { n: everyone.openCount }) }}</span>
        </span>

        <button
          v-if="props.canCreate"
          type="button"
          data-testid="column-new"
          :aria-label="$t('tasks.newFor', { name: everyone.name })"
          class="flex size-10 shrink-0 items-center justify-center rounded-full bg-surface text-ink-2 transition-colors hover:text-ink"
          @click="emit('create', null)"
        >
          <TwIcon name="plus" :size="20" />
        </button>
      </header>

      <!-- One row to swipe through: the strip stays one card high. -->
      <ul class="relative flex min-w-0 flex-1 snap-x gap-2 overflow-x-auto overscroll-x-contain pb-1">
        <ColumnTask
          v-for="entry in shown(everyone)"
          :key="occurrenceKey(entry)"
          :entry="entry"
          :color="color(everyone.color)"
          class="w-72 shrink-0 snap-start"
          @open="emit('open', entry, null)"
        />

        <li
          v-if="everyone.done"
          data-testid="column-done"
          role="status"
          class="flex shrink-0 items-center gap-3 rounded-2xl bg-surface/70 px-4"
        >
          <CelebrationFace :face="celebration(props.day, null).face" class="text-4xl" />
          <span class="tw-pop font-display [animation-delay:400ms] text-lg font-semibold text-success">{{ $t(celebration(props.day, null).praise) }}</span>
        </li>
      </ul>
    </section>
  </div>
</template>
