import { describe, expect, it } from 'vitest'

import {
  type AgendaEvent,
  type Holiday,
  dayGrid,
  formatIsoDate,
  minutesSinceMidnight,
  monthGrid,
  threeDayGrid,
  weekGrid,
  zonedIso,
} from '../app/utils/calendar'

function event(overrides: Partial<AgendaEvent> & Pick<AgendaEvent, 'starts_at' | 'ends_at'>): AgendaEvent {
  return {
    id: 1,
    title: 'Termin',
    location: null,
    all_day: false,
    calendar_id: 1,
    people: [],
    color: '#ff0000',
    ...overrides,
  }
}

const empty = {
  events: [],
  holidays: [],
  today: new Date('2026-09-14T09:00:00+02:00'),
  timeZone: 'Europe/Vienna',
}

describe('monthGrid', () => {
  it('always returns 42 cells', () => {
    // Fixed height: on the wall the grid must not jump when the month changes.
    for (const month of ['2026-02-01', '2026-08-01', '2026-09-01']) {
      expect(monthGrid(new Date(`${month}T12:00:00Z`), empty)).toHaveLength(42)
    }
  })

  it('starts on Monday and contains the first of the month', () => {
    const raster = monthGrid(new Date('2026-09-01T12:00:00Z'), empty)

    expect(new Date(`${raster[0]!.iso}T00:00:00Z`).getUTCDay()).toBe(1)
    expect(raster.map(day => day.iso)).toContain('2026-09-01')
  })

  it('marks days outside the month', () => {
    const raster = monthGrid(new Date('2026-09-01T12:00:00Z'), empty)
    const inMonth = raster.filter(day => day.inMonth)

    expect(inMonth).toHaveLength(30)
    expect(inMonth[0]!.iso).toBe('2026-09-01')
    expect(inMonth[inMonth.length - 1]!.iso).toBe('2026-09-30')
  })

  it('detects weekend and today', () => {
    const raster = monthGrid(new Date('2026-09-01T12:00:00Z'), empty)

    expect(raster.find(day => day.isToday)?.iso).toBe('2026-09-14')
    expect(raster.filter(day => day.isWeekend).map(day => day.iso)).toContain('2026-09-05')
    expect(raster.filter(day => day.isWeekend).map(day => day.iso)).toContain('2026-09-06')
    expect(raster.filter(day => day.isWeekend).map(day => day.iso)).not.toContain('2026-09-07')
  })

  it('attaches holidays to the right day', () => {
    const holiday: Holiday = { date: '2026-09-15', name: 'Testtag', official: true }
    const raster = monthGrid(new Date('2026-09-01T12:00:00Z'), { ...empty, holidays: [holiday] })

    expect(raster.find(day => day.iso === '2026-09-15')?.holiday?.name).toBe('Testtag')
    expect(raster.find(day => day.iso === '2026-09-14')?.holiday).toBeNull()
  })
})

describe('distributing events across days', () => {
  function days(events: AgendaEvent[]): string[] {
    return monthGrid(new Date('2026-09-01T12:00:00Z'), { ...empty, events })
      .filter(day => day.events.length > 0)
      .map(day => day.iso)
  }

  it('puts an all-day event only on its own day', () => {
    // Google's end date is exclusive: the 15th at 00:00 no longer belongs to it.
    expect(days([event({
      all_day: true,
      starts_at: '2026-09-14T00:00:00+02:00',
      ends_at: '2026-09-15T00:00:00+02:00',
    })])).toEqual(['2026-09-14'])
  })

  it('shows a multi-day event on every affected day', () => {
    expect(days([event({
      all_day: true,
      starts_at: '2026-09-14T00:00:00+02:00',
      ends_at: '2026-09-17T00:00:00+02:00',
    })])).toEqual(['2026-09-14', '2026-09-15', '2026-09-16'])
  })

  it('does not let an event ending at midnight spill into the next day', () => {
    expect(days([event({
      starts_at: '2026-09-14T23:00:00+02:00',
      ends_at: '2026-09-15T00:00:00+02:00',
    })])).toEqual(['2026-09-14'])
  })

  it('sorts the events of a day by start', () => {
    const raster = monthGrid(new Date('2026-09-01T12:00:00Z'), {
      ...empty,
      events: [
        event({ id: 2, starts_at: '2026-09-14T15:00:00+02:00', ends_at: '2026-09-14T16:00:00+02:00' }),
        event({ id: 1, starts_at: '2026-09-14T08:00:00+02:00', ends_at: '2026-09-14T09:00:00+02:00' }),
      ],
    })

    expect(raster.find(day => day.iso === '2026-09-14')?.events.map(e => e.id)).toEqual([1, 2])
  })
})

describe('weekGrid', () => {
  it('returns seven days from Monday', () => {
    const week = weekGrid(new Date('2026-09-17T12:00:00Z'), empty)

    expect(week).toHaveLength(7)
    expect(new Date(`${week[0]!.iso}T00:00:00Z`).getUTCDay()).toBe(1)
    expect(week.map(day => day.iso)).toContain('2026-09-17')
  })
})

describe('household time zone', () => {
  function daysWithZone(events: AgendaEvent[], timeZone: string): string[] {
    return monthGrid(new Date('2026-09-01T12:00:00Z'), { ...empty, events, timeZone })
      .filter(day => day.events.length > 0)
      .map(day => day.iso)
  }

  it("distributes events by the household time zone, not by the device's", () => {
    /*
     * An all-day event starts at 00:00 in the household's local time. If the
     * wall computes in its own time zone, it slips over the day boundary and
     * takes up two cells. On a tablet in Austria this never shows, because
     * its time zone happens to match.
     */
    const trip = event({
      all_day: true,
      starts_at: '2026-09-18T00:00:00+02:00',
      ends_at: '2026-09-19T00:00:00+02:00',
    })

    // The process runs in UTC. Before the fix, exactly this case took up
    // two cells, because the event there already started on the 17th at 22:00.
    expect(daysWithZone([trip], 'Europe/Vienna')).toEqual(['2026-09-18'])
  })

  it('also determines today by the household time zone', () => {
    // 23:30 in Vienna is still the same day at 17:30 in New York, but in
    // Auckland already the next day.
    const late = new Date('2026-09-14T23:30:00+02:00')

    expect(monthGrid(late, { ...empty, today: late }).find(day => day.isToday)?.iso)
      .toBe('2026-09-14')

    expect(monthGrid(late, { ...empty, today: late, timeZone: 'Pacific/Auckland' })
      .find(day => day.isToday)?.iso).toBe('2026-09-15')
  })
})

describe('zonedIso', () => {
  it("uses the given time zone, not the process's", () => {
    const moment = new Date('2026-09-14T00:30:00+02:00')

    expect(zonedIso(moment, 'Europe/Vienna')).toBe('2026-09-14')
    expect(zonedIso(moment, 'UTC')).toBe('2026-09-13')
  })
})

describe('formatIsoDate', () => {
  it('prints the calendar date independent of the process time zone', () => {
    // Only the day number: the exact month abbreviation depends on the ICU
    // version and does not belong in an assertion.
    expect(formatIsoDate('2026-09-14', { day: 'numeric' })).toBe('14')
  })
})

describe('threeDayGrid', () => {
  it('starts at the anchor day instead of Monday', () => {
    const days = threeDayGrid(new Date('2026-09-16T09:00:00+02:00'), empty)

    expect(days.map(day => day.iso)).toEqual(['2026-09-16', '2026-09-17', '2026-09-18'])
  })

  it('greys out nothing because there is no month context', () => {
    const days = threeDayGrid(new Date('2026-09-30T09:00:00+02:00'), empty)

    expect(days.map(day => day.iso)).toEqual(['2026-09-30', '2026-10-01', '2026-10-02'])
    expect(days.every(day => day.inMonth)).toBe(true)
  })

  it('computes the anchor day in the household time zone', () => {
    const justBeforeMidnight = new Date('2026-09-16T23:30:00+02:00')

    expect(threeDayGrid(justBeforeMidnight, empty)[0]!.iso).toBe('2026-09-16')
    expect(threeDayGrid(justBeforeMidnight, { ...empty, timeZone: 'UTC' })[0]!.iso).toBe('2026-09-16')
  })

  it('distributes events like the other views', () => {
    const days = threeDayGrid(new Date('2026-09-16T09:00:00+02:00'), {
      ...empty,
      events: [event({ starts_at: '2026-09-17T10:00:00+02:00', ends_at: '2026-09-17T11:00:00+02:00' })],
    })

    expect(days.map(day => day.events.length)).toEqual([0, 1, 0])
  })
})

describe('minutesSinceMidnight', () => {
  it("computes in the household time zone, not in the device's", () => {
    const moment = '2026-09-16T08:30:00+02:00'

    expect(minutesSinceMidnight(moment, 'Europe/Vienna')).toBe(8 * 60 + 30)
    expect(minutesSinceMidnight(moment, 'UTC')).toBe(6 * 60 + 30)
  })

  it('returns zero minutes for midnight instead of 24 hours', () => {
    expect(minutesSinceMidnight('2026-09-16T00:00:00+02:00', 'Europe/Vienna')).toBe(0)
  })
})

describe('dayGrid', () => {
  it('returns exactly the anchor day', () => {
    const days = dayGrid(new Date('2026-09-16T09:00:00+02:00'), empty)

    expect(days.map(day => day.iso)).toEqual(['2026-09-16'])
  })

  it("takes the day from the household zone, not from the device's", () => {
    // 23:30 UTC is already the next day in Vienna.
    const days = dayGrid(new Date('2026-09-16T23:30:00Z'), empty)

    expect(days[0]!.iso).toBe('2026-09-17')
  })
})
