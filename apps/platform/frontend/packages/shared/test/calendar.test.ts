import { describe, expect, it } from 'vitest'
import { isoWeek } from '../app/utils/calendar'

describe('isoWeek', () => {
  it('counts weeks from Monday, as in Austria', () => {
    expect(isoWeek('2026-09-29')).toBe(40)
    expect(isoWeek('2026-09-28')).toBe(40)
    expect(isoWeek('2026-09-27')).toBe(39)
  })

  // The week with the year's first Thursday is week 1.
  it('crosses the turn of the year', () => {
    expect(isoWeek('2026-01-01')).toBe(1)
    expect(isoWeek('2027-01-01')).toBe(53)
    expect(isoWeek('2024-12-30')).toBe(1)
  })
})
