import { describe, expect, it } from 'vitest'
import type { GridRow, Lesson } from '../app/utils/timetable'
import { inkOn } from '../app/utils/timetable'
import { intlLocale } from '../app/utils/intlLocale'
import {
  minutesOfDay,
  mondayOf,
  gridRows,
  schoolDays,
  dayBars,
  clockTime,
  move,
  weekdayOf,
  rowFor,
  weekdayName,
  lessonTint,
} from '../app/utils/timetable'

function lesson(part: Partial<Lesson> = {}): Lesson {
  return {
    lesson_id: 1,
    person_id: 1,
    date: '2026-09-07',
    starts_at: '2026-09-07T07:50:00+02:00',
    ends_at: '2026-09-07T08:40:00+02:00',
    title: 'Mathematik',
    original_title: null,
    period_number: 1,
    status: 'normal',
    color: null,
    ...part,
  }
}

describe('lessonTint', () => {
  it('tints with the subject colour and leaves an uncoloured lesson alone', () => {
    expect(lessonTint('#22c55e')).toEqual({ background: 'linear-gradient(#22c55e2e, #22c55e2e) var(--tint-soft, transparent)', boxShadow: 'inset 4px 0 0 #22c55e' })
    expect(lessonTint(null)).toEqual({})
  })
})

describe('clockTime', () => {
  it('reads the time from the timestamp without converting', () => {
    // The timestamp carries the offset of the household zone. Putting it into
    // a Date would show a different time on the tablet while on holiday.
    expect(clockTime('2026-09-07T07:50:00+02:00')).toBe('07:50')
    expect(clockTime('2026-01-07T07:50:00+01:00')).toBe('07:50')
  })
})

describe('weekdayOf', () => {
  it('counts like ISO-8601: Monday is 1, Sunday is 7', () => {
    expect(weekdayOf('2026-09-07')).toBe(1)
    expect(weekdayOf('2026-09-13')).toBe(7)
  })
})

describe('mondayOf', () => {
  it('finds the Monday of the week', () => {
    expect(mondayOf('2026-09-09')).toBe('2026-09-07')
    expect(mondayOf('2026-09-07')).toBe('2026-09-07')
    expect(mondayOf('2026-09-13')).toBe('2026-09-07')
  })
})

describe('move', () => {
  it('crosses month and year boundaries', () => {
    expect(move('2026-09-30', 1)).toBe('2026-10-01')
    expect(move('2027-01-01', -1)).toBe('2026-12-31')
  })

  it('stays stable across the DST change', () => {
    // In the night to 25 October 2026 the clocks go back.
    expect(move('2026-10-24', 1)).toBe('2026-10-25')
    expect(move('2026-10-25', 1)).toBe('2026-10-26')
  })
})

describe('schoolDays', () => {
  it('shows Monday to Friday while the weekend is empty', () => {
    const days = schoolDays('2026-09-07', [lesson()])

    expect(days).toHaveLength(5)
    expect(days[0]?.date).toBe('2026-09-07')
    expect(days[4]?.date).toBe('2026-09-11')
  })

  it('adds the weekend as soon as there are lessons', () => {
    const days = schoolDays('2026-09-07', [lesson({ date: '2026-09-12' })])

    expect(days).toHaveLength(7)
  })

  it('sorts the lessons of a day by start time', () => {
    const days = schoolDays('2026-09-07', [
      lesson({ title: 'Spät', starts_at: '2026-09-07T10:00:00+02:00' }),
      lesson({ title: 'Früh', starts_at: '2026-09-07T08:00:00+02:00' }),
    ])

    expect(days[0]?.lessons.map(entry => entry.title)).toEqual(['Früh', 'Spät'])
  })
})

describe('gridRows', () => {
  const raster: GridRow[] = [
    { person_id: 1, number: 2, starts_at: '08:45', ends_at: '09:35' },
    { person_id: 1, number: 1, starts_at: '07:50', ends_at: '08:40' },
    { person_id: 2, number: 1, starts_at: '08:00', ends_at: '08:50' },
  ]

  it('takes the bell schedule of the selected child, sorted by number', () => {
    const rows = gridRows(raster, [], 1)

    expect(rows.map(row => row.label)).toEqual(['1.', '2.'])
  })

  it('falls back to the clock times when no bell schedule exists', () => {
    const rows = gridRows([], [
      lesson({ period_number: null, starts_at: '2026-09-07T16:30:00+02:00' }),
      lesson({ period_number: null, starts_at: '2026-09-07T14:00:00+02:00' }),
    ], 1)

    expect(rows.map(row => row.label)).toEqual(['14:00', '16:30'])
  })
})

describe('minutesOfDay', () => {
  it('converts the household time into minutes', () => {
    expect(minutesOfDay('2026-09-07T07:50:00+02:00')).toBe(470)
    expect(minutesOfDay('2026-01-07T07:50:00+01:00')).toBe(470)
  })
})

describe('dayBars', () => {
  it('spans one bar per child from the first to the last lesson', () => {
    const bar = dayBars([
      lesson({ starts_at: '2026-09-07T07:50:00+02:00', ends_at: '2026-09-07T08:40:00+02:00' }),
      lesson({ starts_at: '2026-09-07T11:00:00+02:00', ends_at: '2026-09-07T11:50:00+02:00' }),
      lesson({ person_id: 2, starts_at: '2026-09-07T08:00:00+02:00', ends_at: '2026-09-07T12:30:00+02:00' }),
    ], '2026-09-07')

    expect(bar).toEqual([
      { personId: 1, from: '07:50', until: '11:50' },
      { personId: 2, from: '08:00', until: '12:30' },
    ])
  })

  it('leaves out cancelled lessons', () => {
    // If the last lesson is cancelled, the child is home earlier — exactly
    // what you want to see in the month.
    const bar = dayBars([
      lesson({ starts_at: '2026-09-07T07:50:00+02:00', ends_at: '2026-09-07T08:40:00+02:00' }),
      lesson({
        status: 'cancelled',
        starts_at: '2026-09-07T11:00:00+02:00',
        ends_at: '2026-09-07T11:50:00+02:00',
      }),
    ], '2026-09-07')

    expect(bar).toEqual([{ personId: 1, from: '07:50', until: '08:40' }])
  })

  it('skips the lessons of other days', () => {
    expect(dayBars([lesson({ date: '2026-09-08' })], '2026-09-07')).toEqual([])
  })
})

describe('rowFor', () => {
  it('orders by lesson number when there is one', () => {
    expect(rowFor(lesson({ period_number: 3 }))).toBe('nummer-3')
  })

  it('otherwise orders by start time', () => {
    expect(rowFor(lesson({ period_number: null }))).toBe('zeit-07:50')
  })

  it('keeps a moved substitute lesson in its row', () => {
    // It should stand where the cancelled lesson stood — otherwise you look
    // for it in a row that does not exist in the grid.
    const replacement = lesson({
      period_number: 1,
      status: 'replaced',
      title: 'Turnen',
      original_title: 'Mathematik',
      starts_at: '2026-09-07T09:00:00+02:00',
    })

    expect(rowFor(replacement)).toBe('nummer-1')
  })
})

describe('weekdayName', () => {
  it('names the weekday in the interface language', () => {
    expect(weekdayName(1, 'short')).toBe('Mo')
    expect(weekdayName(7, 'long')).toBe('Sonntag')

    intlLocale.value = 'en-US'
    expect(weekdayName(1, 'short')).toBe('Mon')
    expect(weekdayName(7, 'long')).toBe('Sunday')
    intlLocale.value = 'de-AT'
  })
})

describe('inkOn', () => {
  it('writes white on dark colours and dark on light ones', () => {
    expect(inkOn('#1d4ed8')).toBe('#ffffff')
    expect(inkOn('#c2410c')).toBe('#ffffff')
    expect(inkOn('#facc15')).toBe('#1c1917')
    expect(inkOn('#ffffff')).toBe('#1c1917')
  })
})
