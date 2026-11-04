import { describe, expect, it } from 'vitest'
import { weekDays, weekStart } from '../app/utils/mealWeek'

describe('weekStart', () => {
  it('finds the Monday of the week', () => {
    // 2026-10-06 is a Tuesday.
    expect(weekStart('2026-10-06')).toBe('2026-10-05')
    expect(weekStart('2026-10-05')).toBe('2026-10-05')
  })

  it('counts Sunday to the week before, not the next', () => {
    expect(weekStart('2026-10-11')).toBe('2026-10-05')
  })

  it('pages whole weeks, across a month and a year', () => {
    expect(weekStart('2026-10-06', 1)).toBe('2026-10-12')
    expect(weekStart('2026-10-06', -1)).toBe('2026-09-28')
    expect(weekStart('2026-12-30', 1)).toBe('2027-01-04')
  })

  it('stays put across the change to winter time', () => {
    // Vienna turns the clocks back on 2026-10-25; dates must not slip.
    expect(weekStart('2026-10-26')).toBe('2026-10-26')
    expect(weekDays('2026-10-19').at(-1)).toBe('2026-10-25')
  })
})

describe('weekDays', () => {
  it('lists Monday to Sunday', () => {
    expect(weekDays('2026-10-05')).toEqual([
      '2026-10-05', '2026-10-06', '2026-10-07', '2026-10-08', '2026-10-09', '2026-10-10', '2026-10-11',
    ])
  })
})
