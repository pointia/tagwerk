/**
 * The terms of the task form — the same on the wall and on the phone.
 *
 * Deliberately the bare minimum: both interfaces carry more on their
 * occurrences (origin, rotation, who checked it off), and the form wants to
 * know nothing about that.
 */

export interface Member {
  id: number
  name: string
  color: string | null
  avatar: string | null
}

/** The task being changed — read off one of its occurrences. */
export interface TaskTemplate {
  task_id: number
  title: string
  icon: string | null
  points: number
  person_id: number | null
  /** Whom the task stands for; more than one means one per person. */
  person_ids?: number[]
  skip_holidays?: boolean
  date: string
  routine: boolean
  /** Start of the rule; a rotation counts from here. */
  due_on?: string | null
  /** Readable rule, shown when the form cannot change it. */
  recurrence?: string | null
  /** The rule's weekdays; null when the form cannot express the rule. */
  weekdays?: string[] | null
  dayparts?: string[]
  /** Turn order; empty without a rotation. */
  rotation?: number[]
}

export interface TaskInput {
  title: string
  icon: string | null
  points: number
  /** Empty is "everyone"; two or more make it one per person. */
  person_ids: number[]
  /** Skipped on days off school; see `TaskOccurrenceService`. */
  skip_holidays: boolean
  date: string
  /**
   * Empty means one-off, on exactly this date. Left out, the stored rule
   * and its start stay — for rules the form cannot express.
   */
  weekdays?: string[]
  /** Empty is "any time of the day". */
  dayparts: string[]
  /** Turn order; two or more take over from `person_ids`. */
  rotation: number[]
}

/** What the form offers for selection; the server dictates it. */
export interface FormOption {
  /** Abbreviation to full name, e.g. `MO` to "Montag". */
  weekdays: Record<string, string>
  dayparts?: DaypartOption[]
}

export interface DaypartOption {
  value: string
  label: string
  icon: string
}

/**
 * What names one occurrence besides task and date: the person of a task per
 * person and the time of day. Both null for an ordinary task.
 */
export interface OccurrenceSlot {
  task_id: number
  date: string
  for_person_id: number | null
  daypart: string | null
}

/** A key unique per occurrence — task and date alone repeat. */
export function occurrenceKey(entry: OccurrenceSlot): string {
  return `${entry.task_id}-${entry.date}-${entry.for_person_id ?? ''}-${entry.daypart ?? ''}`
}

/** Sent along when ticking, so the server closes exactly this occurrence. */
export function occurrenceSlot(entry: OccurrenceSlot): { for_person_id: number | null, daypart: string | null } {
  return { for_person_id: entry.for_person_id, daypart: entry.daypart }
}

/** "🌙 abends" for a time of day, otherwise nothing. */
export function daypartText(entry: { daypart_icon: string | null, daypart_label: string | null }): string | null {
  return entry.daypart_label ? `${entry.daypart_icon ?? ''} ${entry.daypart_label}`.trim() : null
}

/**
 * Open tasks and list items first, ticked ones at the end — otherwise in the given order.
 * What is still to do stays on top; what was just ticked moves out of the way.
 */
export function openFirst<T extends { done: boolean }>(entries: T[]): T[] {
  return [...entries].sort((a, b) => Number(a.done) - Number(b.done))
}

import type { BonusDto } from '../tasks.ts'

/**
 * A tick made here that a load may not know of yet. `at` is `Infinity` while
 * the request is on its way, afterwards the step at which the server
 * confirmed it (see `keepTicks`).
 */
export interface LocalTick {
  done: boolean
  done_by_id: number | null
  earned: number
  at: number
}

/**
 * A loaded list with this device's own ticks laid over it.
 *
 * Ticking three tasks in a row: the first one's announcement reloads while
 * the third is still on its way, and the answer shows it open again — until
 * the next announcement takes it away once more. A tick still on its way, or
 * confirmed only after the load had started, therefore wins over the load.
 * Ticks the load already knows of are dropped from `ticks`.
 */
export function keepTicks<T extends OccurrenceSlot & { done: boolean, done_by_id?: number | null, earned?: number }>(
  loaded: T[],
  ticks: Record<string, LocalTick>,
  startedAt: number,
): T[] {
  for (const [key, tick] of Object.entries(ticks)) {
    if (tick.at <= startedAt) {
      delete ticks[key]
    }
  }

  return loaded.map((entry) => {
    const tick = ticks[occurrenceKey(entry)]

    return tick ? { ...entry, done: tick.done, done_by_id: tick.done_by_id, earned: tick.earned } : entry
  })
}

/** A day bonus this device settled: the stars booked now (0 for none), and when. */
export interface LocalDayBonus {
  points: number
  at: number
}

/** The rows with one person's day bonus for a day replaced; 0 removes it. */
export function withDayBonus(rows: BonusDto[], personId: number, date: string, points: number): BonusDto[] {
  const others = rows.filter(row => !(row.day_bonus && row.person_id === personId && row.date === date))

  return points > 0 ? [...others, { person_id: personId, date, points, day_bonus: true }] : others
}

/**
 * Loaded bonus rows with the day bonuses this device settled after the load
 * started — the same reasoning as `keepTicks`. Replaced, never added: whether
 * the load already counted it cannot be told.
 */
export function keepDayBonus(loaded: BonusDto[], settled: Record<string, LocalDayBonus>, startedAt: number): BonusDto[] {
  let rows = loaded

  for (const [key, bonus] of Object.entries(settled)) {
    if (bonus.at <= startedAt) {
      delete settled[key]

      continue
    }

    const [personId, date] = key.split('|')

    rows = withDayBonus(rows, Number(personId), date ?? '', bonus.points)
  }

  return rows
}
