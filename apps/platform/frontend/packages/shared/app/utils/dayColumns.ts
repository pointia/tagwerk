import type { AgendaPerson } from './person'
import type { BonusDto, OccurrenceDto } from '../composables/useTasks'
import type { Progress } from '../composables/useProgress'
import { openFirst } from './task'

/**
 * A day, split by assignee.
 *
 * The list answers "What is there to do today?". This view answers "Am I
 * done?" — and that is the question a child stands in front of the wall
 * with.
 */
export interface DayColumn {
  /** Null is the "Für alle" column — tasks without an assignee. */
  personId: number | null
  name: string
  color: string | null
  avatar: string | null
  entries: OccurrenceDto[]
  openCount: number
  /** Stars the column's person earned today with tasks in this column. */
  sterne: number
  /**
   * Stars the person earned today elsewhere — a task "for everyone",
   * someone else's they did, or extra stars from the stars area. Shown as
   * "13+1", so the column adds up with the stars area. Always 0 in "Für
   * alle": stars belong to people.
   */
  elsewhere: number
  done: boolean
}

/**
 * One column per person, preceded by "Für alle".
 *
 * People without a task keep their column: the row is the picture of the
 * family, and a gap in it would read like an error. The "Für alle" column,
 * on the other hand, only appears when something is in it — an empty grey
 * cell explains nothing to anyone. A guest is not part of that picture and
 * gets a column only on a day with a task of their own.
 */
export function dayColumns(
  occurrences: OccurrenceDto[],
  people: AgendaPerson[],
  date: string,
  /** The column without an owner, in the interface's language. */
  everyone: string,
  bonus: BonusDto[] = [],
): DayColumn[] {
  const onDay = occurrences.filter(entry => entry.date === date)

  const countOpen = (entries: OccurrenceDto[]): number =>
    entries.filter(entry => !entry.done).length

  const earnedBy = (entries: OccurrenceDto[], personId: number | null): number => personId === null
    ? 0
    : entries.reduce((sum, entry) => sum + (entry.done && entry.done_by_id === personId ? entry.earned ?? 0 : 0), 0)

  const column = (
    personId: number | null,
    name: string,
    color: string | null,
    avatar: string | null,
    entries: OccurrenceDto[],
  ): DayColumn => ({
    personId,
    name,
    color,
    avatar,
    entries: openFirst(entries),
    openCount: countOpen(entries),
    sterne: earnedBy(entries, personId),
    elsewhere: earnedBy(onDay.filter(entry => !entries.includes(entry)), personId)
      + bonus.reduce((sum, entry) => sum + (entry.person_id === personId && entry.date === date ? entry.points : 0), 0),

    // The empty day does not count as done — the same rule as for the
    // confetti. Otherwise "Super!" would stand above a column that never had anything.
    done: entries.length > 0 && entries.every(entry => entry.done),
  })

  const openCount = onDay.filter(entry => entry.person_id === null)

  return [
    ...(openCount.length > 0 ? [column(null, everyone, null, null, openCount)] : []),
    ...people
      .map(person => column(
        person.id,
        person.name,
        person.color,
        person.avatar,
        onDay.filter(entry => entry.person_id === person.id),
      ))
      .filter((entry, index) => people[index]?.role !== 'guest' || entry.entries.length > 0),
  ]
}

/**
 * The day shifted by `offset` — but never beyond the loaded range. Outside
 * it an empty row of columns would show that looks like "nothing to do" and
 * really means "not loaded".
 */
export function shiftedDay(date: string, offset: number, from: string, until: string): string {
  const target = new Date(`${date}T12:00:00Z`)

  target.setUTCDate(target.getUTCDate() + offset)

  const iso = target.toISOString().slice(0, 10)

  return iso < from ? from : iso > until ? until : iso
}

/**
 * Whether the people filter lets a person through. Empty means everyone;
 * null stands for "Für alle", the tasks without an assignee.
 */
export function shownFor(selection: (number | null)[], personId: number | null): boolean {
  return selection.length === 0 || selection.includes(personId)
}

/** Done and total per person on one day, for the bars under the names. */
export function dayProgress(occurrences: OccurrenceDto[], date: string): Progress[] {
  const totals = new Map<number, Progress>()

  for (const entry of occurrences) {
    if (entry.date !== date || entry.person_id === null) {
      continue
    }

    const current = totals.get(entry.person_id) ?? { person_id: entry.person_id, done: 0, total: 0 }

    current.total++
    current.done += entry.done ? 1 : 0
    totals.set(entry.person_id, current)
  }

  return [...totals.values()]
}
