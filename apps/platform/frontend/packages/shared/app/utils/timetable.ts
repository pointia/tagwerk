import { intlLocale } from './intlLocale'
import { tint } from './tint'

/** A lesson on one day, as the API delivers it. */
export interface Lesson {
  lesson_id: number
  person_id: number
  /** ISO date, e.g. "2026-09-07". */
  date: string
  starts_at: string
  ends_at: string
  title: string
  /** For a replacement, the subject that would actually be due. */
  original_title: string | null
  period_number: number | null
  status: 'normal' | 'cancelled' | 'replaced'
  /** The subject's colour (#rrggbb), null when none is set or for a replacement. */
  color: string | null
}

/**
 * A soft tint and a bar in the subject's colour. The text stays in the
 * theme's ink: a bright yellow would otherwise be unreadable on white.
 */
export function lessonTint(color: string | null): Record<string, string> {
  return color
    ? { background: tint(`${color}2e`), boxShadow: `inset 4px 0 0 ${color}` }
    : {}
}

/**
 * Readable text on a person's colour: white on dark colours, near-black on
 * light ones such as yellow. Relative luminance after WCAG, compared at the
 * point where both give the same contrast.
 */
export function inkOn(color: string): string {
  const [red, green, blue] = [1, 3, 5]
    .map(start => Number.parseInt(color.slice(start, start + 2), 16) / 255)
    .map(channel => channel <= 0.03928 ? channel / 12.92 : ((channel + 0.055) / 1.055) ** 2.4)

  const luminance = 0.2126 * red! + 0.7152 * green! + 0.0722 * blue!

  return luminance > 0.179 ? '#1c1917' : '#ffffff'
}

export interface GridRow {
  person_id: number
  number: number
  starts_at: string
  ends_at: string
}

export interface TimetableChild {
  id: number
  name: string
  color: string | null
  avatar: string | null
}

export interface SchoolDay {
  /** ISO-Datum. */
  date: string
  /** 1 = Monday … 7 = Sunday. */
  weekday: number
  lessons: Lesson[]
}

/** Monday to Friday. Saturday lessons exist, but they are the exception. */
const SCHOOL_WEEK = 5

/**
 * The time of day without date and seconds: "2026-09-07T07:50:00+02:00"
 * becomes "07:50".
 *
 * Deliberately via the string and not via `Date`: the timestamp already
 * carries the offset of the household zone. Turning it into a `Date` would
 * mean converting it into the device's time zone — and then the tablet
 * would show a different time on holiday than at school.
 */
export function clockTime(timestamp: string): string {
  return timestamp.slice(11, 16)
}

/**
 * The days of a week, starting from Monday.
 *
 * Weekends are left out as long as nothing takes place on them: two empty
 * columns only take up space on the wall.
 */
export function schoolDays(monday: string, lessons: Lesson[]): SchoolDay[] {
  const days: SchoolDay[] = []
  const weekend = lessons.some(lesson => weekdayOf(lesson.date) > SCHOOL_WEEK)
  const count = weekend ? 7 : SCHOOL_WEEK

  for (let offset = 0; offset < count; offset++) {
    const date = move(monday, offset)

    days.push({
      date,
      weekday: weekdayOf(date),
      lessons: lessons
        .filter(lesson => lesson.date === date)
        .sort((a, b) => a.starts_at.localeCompare(b.starts_at)),
    })
  }

  return days
}

/**
 * The rows of the grid: either the child's period numbers or, if there are
 * none, the times that occur.
 *
 * That way even a child without a stored bell schedule gets a grid — built
 * from what actually takes place.
 */
export function gridRows(
  raster: GridRow[],
  lessons: Lesson[],
  kindId: number | null,
): Array<{ key: string, label: string, time: string }> {
  const own = raster
    .filter(row => kindId === null || row.person_id === kindId)
    .sort((a, b) => a.number - b.number)

  if (own.length > 0) {
    return own.map(row => ({
      key: `nummer-${row.number}`,
      label: `${row.number}.`,
      time: row.starts_at,
    }))
  }

  const times = [...new Set(lessons.map(lesson => clockTime(lesson.starts_at)))].sort()

  return times.map(time => ({
    key: `zeit-${time}`,
    label: time,
    time,
  }))
}

/**
 * Which grid row a lesson belongs to.
 *
 * A lesson with a number belongs in its row, one with a free time in the row
 * of its start time. A shifted replacement lesson keeps its row so it stands
 * where the cancelled one stood.
 */
export function rowFor(lesson: Lesson): string {
  return lesson.period_number !== null
    ? `nummer-${lesson.period_number}`
    : `zeit-${clockTime(lesson.starts_at)}`
}

/** The Monday of the week the date falls in. */
export function mondayOf(date: string): string {
  const day = weekdayOf(date)

  return move(date, 1 - day)
}

/**
 * Date arithmetic via UTC midnight, never via the device time zone —
 * otherwise days would jump at the clock change.
 */
export function move(date: string, days: number): string {
  const point = new Date(`${date}T00:00:00Z`)

  point.setUTCDate(point.getUTCDate() + days)

  return point.toISOString().slice(0, 10)
}

/** 1 = Monday … 7 = Sunday, as ISO-8601 counts. */
export function weekdayOf(date: string): number {
  const day = new Date(`${date}T00:00:00Z`).getUTCDay()

  return day === 0 ? 7 : day
}

/** A bar in the month grid: a child's school window on one day. */
export interface DayBar {
  personId: number
  from: string
  until: string
}

/**
 * Minutes since midnight — from the string, not via `Date`.
 *
 * As with `clockTime`: the timestamp carries the household zone, and the
 * device must not convert in between.
 */
export function minutesOfDay(timestamp: string): number {
  const [hour, minute] = clockTime(timestamp).split(':')

  return Number(hour) * 60 + Number(minute)
}

/**
 * One bar per child and day, from the first to the last lesson.
 *
 * The month grid has no room for single lessons; what counts is "when is
 * Lena back from school". Cancelled lessons do not count — if the last one
 * is cancelled, the school day simply ends earlier.
 */
export function dayBars(lessons: Lesson[], date: string): DayBar[] {
  const children = new Map<number, DayBar>()

  for (const lesson of lessons) {
    if (lesson.date !== date || lesson.status === 'cancelled') {
      continue
    }

    const previous = children.get(lesson.person_id)
    const from = clockTime(lesson.starts_at)
    const until = clockTime(lesson.ends_at)

    children.set(lesson.person_id, {
      personId: lesson.person_id,
      from: previous && previous.from < from ? previous.from : from,
      until: previous && previous.until > until ? previous.until : until,
    })
  }

  return [...children.values()].sort((a, b) => a.from.localeCompare(b.from) || a.personId - b.personId)
}

/**
 * The name of a weekday, 1 = Monday, in the interface's language. German
 * short forms without the dot: "Mo", not "Mo.".
 */
export function weekdayName(weekday: number, style: 'short' | 'long'): string {
  // 1 January 2024 was a Monday.
  return new Date(Date.UTC(2024, 0, weekday)).toLocaleDateString(intlLocale.value, { weekday: style, timeZone: 'UTC' })
    .replace(/\.$/, '')
}
