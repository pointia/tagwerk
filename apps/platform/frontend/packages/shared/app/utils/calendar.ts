import { intlLocale } from './intlLocale'

/**
 * Grid computation for the month, week and three-day views.
 *
 * Everything is computed in the household's time zone, not the device's. An
 * all-day event starts at 00:00 in the household's local time; if the wall
 * computed in its own time zone, it would slip over the day boundary and
 * take up two cells. On a tablet in Austria this would never show, because
 * its time zone happens to match.
 *
 * Days are therefore plain calendar dates (YYYY-MM-DD), not instants, and
 * the date arithmetic runs via UTC — there are no DST jumps there.
 */

export interface AgendaEvent {
  id: number
  title: string
  location: string | null
  starts_at: string
  ends_at: string
  all_day: boolean
  calendar_id: number
  people: number[]
  color: string | null
  /** Whether the viewer may change it; the server decides "own". */
  can?: { edit: boolean }
}

/** What the grid needs to show a face: the agenda's people carry more. */
export interface CalendarPerson {
  id: number
  name: string
  color: string | null
  /** URL of the picture; null means the initial is enough. */
  avatar: string | null
}

export interface Holiday {
  date: string
  name: string
  official: boolean
}

export interface GridInput {
  events: AgendaEvent[]
  holidays: Holiday[]
  today: Date
  timeZone: string
}

export interface CalendarDay {
  iso: string
  day: number
  inMonth: boolean
  isToday: boolean
  isWeekend: boolean
  holiday: Holiday | null
  events: AgendaEvent[]
}

/** A column of the hour grid when it is split by person instead of by day. */
export interface PersonColumn {
  /** Null is the "Familie" column — events that belong to nobody. */
  personId: number | null
  name: string
  color: string | null
  avatar: string | null
  day: CalendarDay
}

/** Calendar day an instant falls on in the given time zone. */
export function zonedIso(instant: Date, timeZone: string): string {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
  }).formatToParts(instant)

  const value = (kind: string): string => parts.find(part => part.type === kind)?.value ?? '01'

  return `${value('year')}-${value('month')}-${value('day')}`
}

/**
 * Formats a plain calendar date.
 *
 * UTC midnight together with timeZone UTC prints exactly this date — without
 * the detour via an instant that could flip depending on the device time
 * zone.
 */
export function formatIsoDate(iso: string, options: Intl.DateTimeFormatOptions): string {
  return new Date(`${iso}T00:00:00Z`).toLocaleDateString(intlLocale.value, { ...options, timeZone: 'UTC' })
}

export function monthGrid(anchor: Date, input: GridInput): CalendarDay[] {
  const month = zonedIso(anchor, input.timeZone).slice(0, 7)
  const first = `${month}-01`
  const start = move(first, -mondayOffset(first))

  // Fixed 42 cells: on the wall the grid must not change height when the
  // month changes.
  return buildDays(start, 42, month, input)
}

export function weekGrid(anchor: Date, input: GridInput): CalendarDay[] {
  const anchorIso = zonedIso(anchor, input.timeZone)
  const start = move(anchorIso, -mondayOffset(anchorIso))

  // Without a month reference: in the week view a greyed-out day at the
  // month boundary would be misleading.
  return buildDays(start, 7, null, input)
}

/**
 * A single day.
 *
 * The view in which the columns are no longer days but people: whoever wants
 * to know what their own afternoon looks like reads one column instead of a
 * week.
 */
export function dayGrid(anchor: Date, input: GridInput): CalendarDay[] {
  return buildDays(zonedIso(anchor, input.timeZone), 1, null, input)
}

/**
 * Three days from the anchor — without the Monday offset.
 *
 * The wall's default view: what is on today, tomorrow and the day after is
 * what someone stops for in passing. A whole week answers that question with
 * seven columns, four of which interest nobody.
 */
export function threeDayGrid(anchor: Date, input: GridInput): CalendarDay[] {
  return buildDays(zonedIso(anchor, input.timeZone), 3, null, input)
}

/**
 * Minutes since midnight in the household's time zone.
 *
 * For the vertical placement in the hour grid. As everywhere here, the
 * household's zone applies: a tablet with a wrongly set time zone would
 * otherwise shift every event by hours.
 */
export function minutesSinceMidnight(moment: string, timeZone: string): number {
  const parts = new Intl.DateTimeFormat('en-US', {
    timeZone,
    hour: '2-digit',
    minute: '2-digit',
    hourCycle: 'h23',
  }).formatToParts(new Date(moment))

  const value = (kind: string): number => Number(parts.find(part => part.type === kind)?.value ?? 0)

  return value('hour') * 60 + value('minute')
}

/** Distance to the preceding Monday. Sunday is 0 in JavaScript, 6 here. */
function mondayOffset(iso: string): number {
  return (weekday(iso) + 6) % 7
}

function weekday(iso: string): number {
  return utc(iso).getUTCDay()
}

function move(iso: string, days: number): string {
  const date = utc(iso)
  date.setUTCDate(date.getUTCDate() + days)

  return date.toISOString().slice(0, 10)
}

function utc(iso: string): Date {
  return new Date(`${iso}T00:00:00Z`)
}

function buildDays(
  start: string,
  count: number,
  month: string | null,
  input: GridInput,
): CalendarDay[] {
  const holidays = new Map(input.holidays.map(entry => [entry.date, entry]))
  const today = zonedIso(input.today, input.timeZone)

  /*
   * Every event is mapped onto the calendar days it occupies. The end is
   * exclusive — Google delivers all-day events that way, and an event until
   * midnight does not end on the next day either. One millisecond before the
   * end therefore hits exactly the last occupied day.
   */
  const occupancy = input.events
    .map((event) => {
      const from = zonedIso(new Date(event.starts_at), input.timeZone)
      const end = new Date(new Date(event.ends_at).getTime() - 1)
      const until = zonedIso(end, input.timeZone)

      return { event, from, until: until < from ? from : until }
    })
    .sort((a, b) => a.event.starts_at.localeCompare(b.event.starts_at))

  const days: CalendarDay[] = []

  for (let i = 0; i < count; i++) {
    const iso = move(start, i)
    const day = weekday(iso)

    days.push({
      iso,
      day: Number(iso.slice(8, 10)),
      inMonth: month === null || iso.startsWith(month),
      isToday: iso === today,
      isWeekend: day === 0 || day === 6,
      holiday: holidays.get(iso) ?? null,
      events: occupancy
        .filter(({ from, until }) => from <= iso && iso <= until)
        .map(({ event }) => event),
    })
  }

  return days
}

/**
 * The calendar week after ISO 8601, as printed in Austrian calendars: weeks
 * start on Monday, week 1 holds the year's first Thursday. From the pure
 * calendar day, in UTC — the device's zone must not shift it.
 */
export function isoWeek(iso: string): number {
  const date = new Date(`${iso}T00:00:00Z`)
  const weekday = (date.getUTCDay() + 6) % 7

  // The Thursday of this week decides the year the week belongs to.
  date.setUTCDate(date.getUTCDate() + 3 - weekday)

  const yearStart = Date.UTC(date.getUTCFullYear(), 0, 1)

  return 1 + Math.floor((date.getTime() - yearStart) / (7 * 86_400_000))
}
