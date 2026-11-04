<script setup lang="ts">
import { type AgendaEvent, type CalendarDay, formatIsoDate } from '~/utils/calendar'
import { type Lesson, dayBars, weekdayName } from '../../../shared/app/utils/timetable'
import { tint } from '../../../shared/app/utils/tint'

const { t } = useI18n()

const props = withDefaults(
  defineProps<{
    days: CalendarDay[]
    timeZone: string
    /** School lessons for the band; empty means band off. */
    lessons?: Lesson[]
  }>(),
  { lessons: () => [] },
)

const emit = defineEmits<{ 'pick-day': [string], 'pick-event': [AgendaEvent] }>()

const { agenda } = useAgenda()

/**
 * One bar per child, never single lessons: they do not fit into a month
 * cell, and nobody reads them from two metres away anyway.
 */
function bar(iso: string): Array<{ personId: number, text: string, color: string }> {
  return dayBars(props.lessons, iso).map((entry) => {
    const kind = agenda.value?.people.find(person => person.id === entry.personId)

    return {
      personId: entry.personId,
      text: `${kind?.name ?? t('timetable.school')} ${entry.from}–${entry.until}`,
      color: kind?.color ?? '#8a8276',
    }
  })
}

const weekdays = computed(() => [1, 2, 3, 4, 5, 6, 7].map(day => weekdayName(day, 'short')))

// More does not fit into a cell without nothing being readable from a
// distance. The rest is counted instead of cut off.
const PER_CELL = 4

function label(iso: string): string {
  return t('calendar.createOn', { date: formatIsoDate(iso, { weekday: 'long', day: 'numeric', month: 'long' }) })
}
</script>

<template>
  <div class="flex h-full flex-col">
    <div class="grid shrink-0 grid-cols-7 border-b border-line">
      <div
        v-for="day in weekdays"
        :key="day"
        class="px-3 py-2.5 text-sm font-semibold tracking-wider text-ink-3 uppercase"
      >
        {{ day }}
      </div>
    </div>

    <div class="grid min-h-0 flex-1 grid-cols-7 grid-rows-6">
      <div
        v-for="day in days"
        :key="day.iso"
        data-testid="day"
        :data-iso="day.iso"
        class="relative flex min-h-0 flex-col gap-1 border-r border-b border-line p-1.5 transition-colors hover:bg-surface-2/60 [&:nth-child(7n)]:border-r-0 [&:nth-last-child(-n+7)]:border-b-0"
        :class="!day.inMonth ? 'bg-canvas/60' : day.isWeekend ? 'bg-surface-2/30' : ''"
        @click="emit('pick-day', day.iso)"
      >
        <!-- Keyboards and screen readers: the cell is no control itself (see TimeGrid). -->
        <button
          type="button"
          data-testid="day-add"
          class="sr-only focus-visible:not-sr-only focus-visible:absolute focus-visible:inset-x-1 focus-visible:top-1 focus-visible:z-20 focus-visible:rounded-lg focus-visible:bg-surface focus-visible:px-2 focus-visible:py-1 focus-visible:text-sm focus-visible:font-semibold focus-visible:shadow-floating"
          @click.stop="emit('pick-day', day.iso)"
        >
          {{ label(day.iso) }}
        </button>

        <div class="flex shrink-0 items-center justify-between gap-1">
          <span
            class="flex size-8 items-center justify-center rounded-full text-base font-semibold tabular-nums"
            :class="day.isToday
              ? 'bg-today text-today-ink'
              : day.inMonth ? 'text-ink' : 'text-ink-3'"
          >
            {{ day.day }}
          </span>

          <span
            v-if="day.holiday"
            class="truncate pr-1 text-sm font-semibold text-ink-2"
            :title="day.holiday.name"
          >
            {{ day.holiday.name }}
          </span>
        </div>

        <span
          v-for="entry in bar(day.iso)"
          :key="entry.personId"
          data-testid="school-bar"
          class="truncate rounded-md px-2 text-sm font-medium text-ink-2 tabular-nums"
          :style="{ background: tint(`${entry.color}1f`, 'solid'), boxShadow: `inset 2px 0 0 ${entry.color}80` }"
        >
          {{ entry.text }}
        </span>

        <EventChip
          v-for="event in day.events.slice(0, PER_CELL)"
          :key="event.id"
          :event="event"
          :time-zone="timeZone"
          :people="agenda?.people ?? []"
          @click.stop="emit('pick-event', event)"
        />

        <span
          v-if="day.events.length > PER_CELL"
          class="px-2 text-sm font-medium text-ink-2"
        >
          {{ $t('calendar.more', { count: day.events.length - PER_CELL }) }}
        </span>
      </div>
    </div>
  </div>
</template>
