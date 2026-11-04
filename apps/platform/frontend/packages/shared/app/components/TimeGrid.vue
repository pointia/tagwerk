<script setup lang="ts">
import { intlLocale } from '../utils/intlLocale'
import {
  type AgendaEvent,
  type CalendarDay,
  type CalendarPerson,
  type PersonColumn,
  formatIsoDate,
  isoWeek,
  minutesSinceMidnight,
  zonedIso,
} from '../utils/calendar'
import { bandLayout } from '../utils/schoolBand'
import { tint } from '../utils/tint'
import { type Lesson, minutesOfDay } from '../utils/timetable'

const { t } = useI18n()

/*
 * Hour grid for the week, three days and the single day.
 *
 * Events stand where they take place — a list at the top does not answer
 * the question "when?", and that is exactly the question asked in front of a
 * wall calendar. All-day items have no time and therefore stay at the top in
 * a bar of their own; whatever lies outside the shown hours lands there too,
 * so nothing becomes invisible.
 */
interface Props {
  days: CalendarDay[]
  timeZone: string
  now: Date
  /** School lessons for the band; empty means band off. */
  lessons?: Lesson[]
  /**
   * Columns per person instead of per day. Empty means the days are the
   * columns.
   *
   * The same mechanics, just split differently: a single day with seven
   * events says little about who is affected — side by side you see it at a
   * glance.
   */
  columns?: PersonColumn[]
  /** Faces for the events; the events only carry ids. */
  people?: CalendarPerson[]
  /** Pixels per hour — the wall has room, a phone or a tile has less. */
  hourHeight?: number
  /**
   * Keep the current hour in view as the day goes on. For a tile nobody
   * scrolls; on the phone it pauses for a minute after someone scrolled.
   */
  follow?: boolean
}

/*
 * Annotated on purpose: in the apps' type check the macro's result is `any`
 * for components that live in this layer.
 */
const props: Required<Props> = withDefaults(defineProps<Props>(), {
  lessons: () => [],
  columns: () => [],
  people: () => [],
  hourHeight: 68,
  follow: false,
})

interface Pillar {
  key: string
  day: CalendarDay
  person: PersonColumn | null
}

const pillars = computed<Pillar[]>(() => (
  props.columns.length > 0
    ? props.columns.map(column => ({
        key: `person-${column.personId ?? 'familie'}`,
        day: column.day,
        person: column,
      }))
    : props.days.map(day => ({ key: day.iso, day, person: null }))
))

const emit = defineEmits<{
  'pick-day': [string]
  'pick-event': [AgendaEvent]
  'pick-lesson': [Lesson]
}>()

/* Nobody stands in front of the wall at night; the hours before only cost height. */
const FROM = 6
const UNTIL = 23
const hours = Array.from({ length: UNTIL - FROM }, (_, i) => FROM + i)
const gridHeight = computed(() => (UNTIL - FROM) * props.hourHeight)

interface Placement {
  event: AgendaEvent
  start: number
  end: number
}

interface Ranking extends Placement {
  column: number
  columns: number
}

/**
 * Placement of an event on a day, clipped to this day.
 *
 * An overnight event starts at 0:00 on the next day and ends at 24:00 on the
 * previous day — otherwise it would draw its full length on both days.
 */
function placement(event: AgendaEvent, iso: string): Placement {
  const starts = zonedIso(new Date(event.starts_at), props.timeZone) === iso
  const ends = zonedIso(new Date(new Date(event.ends_at).getTime() - 1), props.timeZone) === iso

  return {
    event,
    start: starts ? minutesSinceMidnight(event.starts_at, props.timeZone) : 0,
    end: ends ? minutesSinceMidnight(event.ends_at, props.timeZone) : 24 * 60,
  }
}

function inGrid(placement: Placement): boolean {
  return placement.end > FROM * 60 && placement.start < UNTIL * 60 && placement.end > placement.start
}

/** All-day items and everything outside the shown hours. */
function top(day: CalendarDay): AgendaEvent[] {
  return day.events.filter(
    event => event.all_day || !inGrid(placement(event, day.iso)),
  )
}

/**
 * Overlapping events share the width.
 *
 * Every group of touching events gets as many columns as needed at the
 * densest point. Without that, the dentist appointment would lie invisible
 * behind the parents' evening.
 */
function rankings(day: CalendarDay): Ranking[] {
  const events = day.events
    .filter(event => !event.all_day)
    .map(event => placement(event, day.iso))
    .filter(inGrid)
    .sort((a, b) => a.start - b.start || b.end - a.end)

  const result: Ranking[] = []
  let group: Placement[] = []
  let groupEnd = -1

  const finish = (): void => {
    const columnsEnd: number[] = []
    const occupied = group.map((entry) => {
      let column = columnsEnd.findIndex(end => end <= entry.start)

      if (column === -1) {
        column = columnsEnd.length
      }

      columnsEnd[column] = entry.end

      return { ...entry, column }
    })

    for (const entry of occupied) {
      result.push({ ...entry, columns: columnsEnd.length })
    }

    group = []
    groupEnd = -1
  }

  for (const entry of events) {
    if (group.length > 0 && entry.start >= groupEnd) {
      finish()
    }

    group.push(entry)
    groupEnd = Math.max(groupEnd, entry.end)
  }

  if (group.length > 0) {
    finish()
  }

  return result
}

/*
 * The school band sits on the left of the day column, the events move in for
 * it. Side by side instead of on top of each other: otherwise the morning is
 * one single area in which you no longer find the dentist appointment.
 */
// A person column holds one child only, so it needs a single lane.
const band = computed(() => bandLayout(props.columns.length > 0 ? props.lessons.slice(0, 1) : props.lessons))

const bandWidth = computed(() => band.value.width)

interface BandLesson {
  lesson: Lesson
  top: number
  height: number
  /** Position within the column, in percent. */
  left: number
  width: number
}

function schoolBand(pillar: Pillar): BandLesson[] {
  return props.lessons
    .filter(lesson => lesson.date === pillar.day.iso)
    .filter(lesson => pillar.person === null || lesson.person_id === pillar.person.personId)
    .map((lesson) => {
      const start = Math.max(minutesOfDay(lesson.starts_at), FROM * 60)
      const end = Math.min(minutesOfDay(lesson.ends_at), UNTIL * 60)

      const lanes = pillar.person === null ? band.value.lanes.length : 1
      const lane = pillar.person === null ? band.value.lanes.indexOf(lesson.person_id) : 0

      return {
        lesson,
        top: (start - FROM * 60) / 60 * props.hourHeight,
        height: Math.max((end - start) / 60 * props.hourHeight - 2, 18),
        left: lane * bandWidth.value / lanes,
        width: bandWidth.value / lanes,
      }
    })
    .filter(entry => entry.height > 0 && entry.top < gridHeight.value)
}

function style(rank: Ranking): Record<string, string> {
  const start = Math.max(rank.start, FROM * 60)
  const end = Math.min(rank.end, UNTIL * 60)
  const width = (100 - bandWidth.value) / rank.columns
  const color = rank.event.color ?? '#8a8276'

  return {
    top: `${(start - FROM * 60) / 60 * props.hourHeight}px`,
    height: `${Math.max((end - start) / 60 * props.hourHeight - 2, 26)}px`,
    left: `${bandWidth.value + rank.column * width}%`,
    width: `${width}%`,
    background: tint(`${color}2e`, 'solid'),
    boxShadow: `inset 3px 0 0 ${color}`,
  }
}

function clockTime(moment: string): string {
  return new Date(moment).toLocaleTimeString(intlLocale.value, {
    hour: '2-digit',
    minute: '2-digit',
    timeZone: props.timeZone,
  })
}

function weekday(iso: string): string {
  return formatIsoDate(iso, { weekday: props.days.length > 3 ? 'short' : 'long' })
}

function label(iso: string): string {
  return t('calendar.createOn', { date: formatIsoDate(iso, { weekday: 'long', day: 'numeric', month: 'long' }) })
}

function child(personId: number): CalendarPerson | undefined {
  return props.people.find(person => person.id === personId)
}

/** The child's colour, so the band can be attributed without a name. */
function childColor(personId: number): string {
  return child(personId)?.color ?? '#8a8276'
}

function involved(event: AgendaEvent): CalendarPerson[] {
  return event.people
    .map(id => props.people.find(person => person.id === id))
    .filter((person): person is CalendarPerson => person !== undefined)
    .slice(0, 3)
}

/** Below two lines of height: time and title then share one. */
function short(rank: Ranking): boolean {
  return (Math.min(rank.end, UNTIL * 60) - Math.max(rank.start, FROM * 60)) / 60 * props.hourHeight < 46
}

/** Over already: faded, so the eye goes to what is still ahead. */
function past(event: AgendaEvent): boolean {
  return new Date(event.ends_at).getTime() <= props.now.getTime()
}

/** Position of the now line, or null outside the shown hours. */
const nowTop = computed(() => {
  const minutes = minutesSinceMidnight(props.now.toISOString(), props.timeZone)

  if (minutes < FROM * 60 || minutes > UNTIL * 60) {
    return null
  }

  return (minutes - FROM * 60) / 60 * props.hourHeight
})

const raster = useTemplateRef<HTMLElement>('raster')

function showNow(): void {
  if (nowTop.value !== null && raster.value) {
    raster.value.scrollTop = Math.max(nowTop.value - props.hourHeight, 0)
  }
}

/* The current hour should be visible without swiping. */
onMounted(showNow)

// Whoever scrolled wants to look somewhere else — for a minute at least.
let touchedAt = 0

function touched(): void {
  touchedAt = Date.now()
}

watch(nowTop, () => {
  if (props.follow && Date.now() - touchedAt > 60_000) {
    showNow()
  }
})
</script>

<template>
  <div class="flex h-full flex-col">
    <!--
      The grid's scrollbar takes away width. Without the same space in the
      header row, weekday and column would be offset by a few pixels, and the
      all-day events would stick out beyond their day.
    -->
    <div class="flex shrink-0 overflow-y-scroll border-b border-line [scrollbar-gutter:stable]">
      <!-- The otherwise empty corner carries the week, as on a paper calendar. -->
      <div
        v-if="pillars.length > 0"
        data-testid="calendar-week"
        class="flex w-16 shrink-0 flex-col items-center justify-center leading-none"
      >
        <span class="text-2xl font-semibold text-ink-2 tabular-nums">{{ isoWeek(pillars[0]!.day.iso) }}</span>
        <span class="mt-1 text-xs font-semibold tracking-wide text-ink-3">KW</span>
      </div>
      <div v-else class="w-16 shrink-0" />

      <div
        v-for="pillar in pillars"
        :key="pillar.key"
        class="min-w-0 flex-1 border-l border-line px-2 py-2"
        :class="pillar.person === null && pillar.day.isWeekend ? 'bg-surface-2/30' : ''"
      >
        <div v-if="pillar.person" data-testid="person-column" class="flex items-center gap-2">
          <TwAvatar
            :name="pillar.person.name"
            :color="pillar.person.color"
            :avatar="pillar.person.avatar"
            :size="36"
          />

          <span class="min-w-0 block truncate text-base font-semibold">{{ pillar.person.name }}</span>
        </div>

        <div v-else class="flex items-center gap-2">
          <span
            class="flex size-10 shrink-0 items-center justify-center rounded-full text-xl font-semibold tabular-nums"
            :class="pillar.day.isToday ? 'bg-today text-today-ink' : 'text-ink'"
          >
            {{ pillar.day.day }}
          </span>

          <span class="min-w-0">
            <span class="block truncate text-base font-semibold capitalize">{{ weekday(pillar.day.iso) }}</span>
            <span v-if="pillar.day.holiday" class="block truncate text-sm text-ink-2">{{ pillar.day.holiday.name }}</span>
          </span>
        </div>

        <div class="mt-1.5 flex flex-col gap-1">
          <EventChip
            v-for="event in top(pillar.day)"
            :key="event.id"
            :event="event"
            :time-zone="timeZone"
            :people="people"
            wrap
            @click.stop="emit('pick-event', event)"
          />
        </div>
      </div>
    </div>

    <div
      ref="raster"
      class="min-h-0 flex-1 overflow-y-scroll [scrollbar-gutter:stable]"
      @pointerdown.passive="touched"
      @wheel.passive="touched"
    >
      <div class="flex" :style="{ height: `${gridHeight}px` }">
        <div class="relative w-16 shrink-0">
          <span
            v-for="hour in hours"
            :key="hour"
            class="absolute right-2 -translate-y-1/2 text-sm text-ink-3 tabular-nums"
            :style="{ top: `${(hour - FROM) * hourHeight}px` }"
          >
            {{ String(hour).padStart(2, '0') }}
          </span>
        </div>

        <div
          v-for="pillar in pillars"
          :key="pillar.key"
          data-testid="day"
          :data-iso="pillar.day.iso"
          class="relative min-w-0 flex-1 border-l border-line"
          :class="pillar.person === null && pillar.day.isWeekend ? 'bg-surface-2/30' : ''"
          :style="pillar.person?.color ? { backgroundColor: pillar.person.color + '14' } : undefined"
          @click="emit('pick-day', pillar.day.iso)"
        >
          <!--
            The day's surface is a tap target for fingers; keyboards and
            screen readers get this button instead. A surface with role
            "button" around the event buttons would nest controls.
          -->
          <button
            type="button"
            data-testid="day-add"
            class="sr-only focus-visible:not-sr-only focus-visible:absolute focus-visible:inset-x-1 focus-visible:top-1 focus-visible:z-20 focus-visible:rounded-lg focus-visible:bg-surface focus-visible:px-2 focus-visible:py-1 focus-visible:text-sm focus-visible:font-semibold focus-visible:shadow-floating"
            @click.stop="emit('pick-day', pillar.day.iso)"
          >
            {{ label(pillar.day.iso) }}
          </button>

          <div
            v-for="hour in hours"
            :key="hour"
            class="absolute inset-x-0 border-t border-line/60"
            :style="{ top: `${(hour - FROM) * hourHeight}px` }"
          />

          <button
            v-for="entry in schoolBand(pillar)"
            :key="`${entry.lesson.lesson_id}-${entry.lesson.date}`"
            type="button"
            data-testid="school-lesson"
            class="absolute overflow-hidden rounded-md px-1 text-left text-xs leading-tight"
            :style="{
              top: `${entry.top}px`,
              height: `${entry.height}px`,
              left: `${entry.left}%`,
              width: `${entry.width}%`,
              background: tint(`${entry.lesson.color ?? childColor(entry.lesson.person_id)}1f`, 'solid'),
              boxShadow: `inset 2px 0 0 ${entry.lesson.color ?? childColor(entry.lesson.person_id)}80`,
            }"
            :class="entry.lesson.status === 'cancelled' ? 'text-ink-3 line-through' : 'text-ink-2'"
            @click.stop="emit('pick-lesson', entry.lesson)"
          >
            <span class="flex items-start gap-1">
              <!-- The face says whose lesson it is; the colour belongs to the subject. -->
              <TwAvatar
                v-if="child(entry.lesson.person_id)"
                data-testid="school-lesson-person"
                class="mt-px shrink-0"
                :name="child(entry.lesson.person_id)!.name"
                :color="child(entry.lesson.person_id)!.color"
                :avatar="child(entry.lesson.person_id)!.avatar"
                :size="16"
              />
              <span class="min-w-0 break-words">{{ entry.lesson.title }}</span>
            </span>
          </button>

          <button
            v-for="rank in rankings(pillar.day)"
            :key="rank.event.id"
            type="button"
            data-testid="event"
            class="absolute overflow-hidden rounded-lg px-2 py-0.5 text-left text-base leading-snug text-ink transition-[filter] hover:brightness-95"
            :class="past(rank.event) ? 'tw-past' : ''"
            :style="style(rank)"
            @click.stop="emit('pick-event', rank.event)"
          >
            <span class="flex items-center gap-1.5">
              <span class="shrink-0 text-sm text-ink-2 tabular-nums">{{ clockTime(rank.event.starts_at) }}</span>

              <!-- Too short for two lines: the title joins the time, or it would be cut off. -->
              <span v-if="short(rank)" class="min-w-0 truncate font-medium">{{ rank.event.title }}</span>

              <span v-if="involved(rank.event).length > 0" class="flex shrink-0 -space-x-1">
                <TwAvatar
                  v-for="person in involved(rank.event)"
                  :key="person.id"
                  :name="person.name"
                  :color="person.color"
                  :avatar="person.avatar"
                  :size="18"
                />
              </span>
            </span>
            <span v-if="!short(rank)" class="block font-medium break-words">{{ rank.event.title }}</span>
          </button>

          <div
            v-if="pillar.day.isToday && nowTop !== null"
            aria-hidden="true"
            class="pointer-events-none absolute inset-x-0 z-10 border-t-2 border-today"
            :style="{ top: `${nowTop}px` }"
          >
            <span class="absolute -top-1 -left-1 size-2 rounded-full bg-today" />
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
