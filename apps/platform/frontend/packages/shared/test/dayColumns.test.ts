import { describe, expect, it } from 'vitest'
import type { AgendaPerson } from '../app/utils/person'
import type { OccurrenceDto } from '../app/composables/useTasks'
import { dayColumns, dayProgress, shiftedDay, shownFor } from '../app/utils/dayColumns'

const people: AgendaPerson[] = [
  { id: 1, name: 'Lena', color: '#c66', avatar: null, role: 'child' },
  { id: 2, name: 'Max', color: '#6c6', avatar: null, role: 'child' },
]

function occurrence(part: Partial<OccurrenceDto>): OccurrenceDto {
  return {
    task_id: 1,
    title: 'Zähne putzen',
    icon: null,
    notes: null,
    date: '2026-09-21',
    points: 2,
    routine: false,
    recurrence: null,
    person_id: null,
    person_name: null,
    rotating: false,
    color: null,
    avatar: null,
    done: false,
    done_by: null,
    origin: null,
    ...part,
  }
}

describe('dayColumns', () => {
  it('gives every person a column, even without tasks', () => {
    const columns = dayColumns([], people, '2026-09-21', 'Für alle')

    expect(columns.map(column => column.name)).toEqual(['Lena', 'Max'])
  })

  it('gives a guest a column only on a day with a task of their own', () => {
    const family = [...people, { id: 3, name: 'Oma', color: null, avatar: null, role: 'guest' as const }]

    expect(dayColumns([], family, '2026-09-21', 'Für alle').map(column => column.name))
      .toEqual(['Lena', 'Max'])
    expect(dayColumns([occurrence({ person_id: 3 })], family, '2026-09-21', 'Für alle').map(column => column.name))
      .toEqual(['Lena', 'Max', 'Oma'])
  })

  it('puts unassigned tasks first', () => {
    const columns = dayColumns([occurrence({ person_id: null })], people, '2026-09-21', 'Für alle')

    expect(columns[0]?.name).toBe('Für alle')
    expect(columns[0]?.entries).toHaveLength(1)
  })

  it('omits the "for everyone" column while it is empty', () => {
    const columns = dayColumns([occurrence({ person_id: 1 })], people, '2026-09-21', 'Für alle')

    expect(columns.map(column => column.name)).toEqual(['Lena', 'Max'])
  })

  it('shows only the selected day', () => {
    const columns = dayColumns(
      [occurrence({ person_id: 1 }), occurrence({ person_id: 1, date: '2026-09-22' })],
      people,
      '2026-09-21',
      'Für alle',
    )

    expect(columns[0]?.entries).toHaveLength(1)
  })

  it('counts the open tasks and the stars already earned', () => {
    const columns = dayColumns(
      [
        occurrence({ task_id: 1, person_id: 1, done: true, points: 3, done_by_id: 1, earned: 3 }),
        occurrence({ task_id: 2, person_id: 1, done: false, points: 5 }),
      ],
      people,
      '2026-09-21',
      'Für alle',
    )

    expect(columns[0]?.openCount).toBe(1)
    expect(columns[0]?.sterne).toBe(3)
  })

  /*
   * The column adds up with the stars area: what Lena earned with a task
   * "for everyone" counts as extra, and what was booked counts — not what
   * the task is worth after a change.
   */
  it('adds stars earned in other columns separately', () => {
    const columns = dayColumns(
      [
        occurrence({ task_id: 1, person_id: 1, done: true, points: 5, done_by_id: 1, earned: 2 }),
        occurrence({ task_id: 2, person_id: null, done: true, points: 1, done_by_id: 1, earned: 1 }),
        occurrence({ task_id: 3, person_id: 2, done: true, points: 4, done_by_id: 1, earned: 4 }),
      ],
      people,
      '2026-09-21',
      'Für alle',
    )

    const [everyone, lena, max] = columns

    expect(lena?.sterne).toBe(2)
    expect(lena?.elsewhere).toBe(5)
    expect(max?.sterne).toBe(0)
    expect(everyone?.sterne).toBe(0)
    expect(everyone?.elsewhere).toBe(0)
  })

  /** Extra stars from the stars area belong to the person's day, too. */
  it('adds extra stars of the day to what was earned elsewhere', () => {
    const [lena, max] = dayColumns(
      [occurrence({ task_id: 1, person_id: 1, done: true, points: 2, done_by_id: 1, earned: 2 })],
      people,
      '2026-09-21',
      'Für alle',
      [
        { person_id: 1, date: '2026-09-21', points: 3 },
        { person_id: 1, date: '2026-09-20', points: 7 },
        { person_id: 2, date: '2026-09-21', points: -1 },
      ],
    )

    expect(lena?.sterne).toBe(2)
    expect(lena?.elsewhere).toBe(3)
    expect(max?.elsewhere).toBe(-1)
  })

  /** The same rule as for the confetti: an empty day achieves nothing. */
  it('does not call an empty column done', () => {
    const columns = dayColumns([], people, '2026-09-21', 'Für alle')

    expect(columns[0]?.done).toBe(false)
  })

  it('calls a fully checked column done', () => {
    const columns = dayColumns(
      [occurrence({ person_id: 1, done: true })],
      people,
      '2026-09-21',
      'Für alle',
    )

    expect(columns[0]?.done).toBe(true)
  })
})

describe('shiftedDay', () => {
  it('moves one day forward', () => {
    expect(shiftedDay('2026-09-21', 1, '2026-09-21', '2026-10-04')).toBe('2026-09-22')
  })

  it('crosses the month boundary', () => {
    expect(shiftedDay('2026-09-30', 1, '2026-09-21', '2026-10-04')).toBe('2026-10-01')
  })

  /** Out there an empty row would look like "nothing to do". */
  it('stays within the loaded range', () => {
    expect(shiftedDay('2026-09-21', -1, '2026-09-21', '2026-10-04')).toBe('2026-09-21')
    expect(shiftedDay('2026-10-04', 1, '2026-09-21', '2026-10-04')).toBe('2026-10-04')
  })
})

describe('shownFor', () => {
  it('lets everyone through without a choice', () => {
    expect(shownFor([], 1)).toBe(true)
    expect(shownFor([], null)).toBe(true)
  })

  it('keeps only the chosen people, null being "Für alle"', () => {
    expect(shownFor([2], 2)).toBe(true)
    expect(shownFor([2], 1)).toBe(false)
    expect(shownFor([2], null)).toBe(false)
    expect(shownFor([null], null)).toBe(true)
  })
})

describe('dayProgress', () => {
  it('counts done and total per person on that day only', () => {
    const progress = dayProgress([
      occurrence({ person_id: 1, done: true }),
      occurrence({ person_id: 1, done: false }),
      occurrence({ person_id: 2, done: true }),
      occurrence({ person_id: 2, done: false, date: '2026-09-22' }),
      occurrence({ person_id: null, done: true }),
    ], '2026-09-21')

    expect(progress).toEqual([
      { person_id: 1, done: 1, total: 2 },
      { person_id: 2, done: 1, total: 1 },
    ])
  })
})
