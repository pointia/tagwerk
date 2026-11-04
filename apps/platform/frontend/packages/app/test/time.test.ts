import { describe, expect, it } from 'vitest'
import { allDaySpan, instantFromWallTime, wallTimeFromInstant } from '../app/utils/time'

/*
 * The process runs in UTC (see vitest.config.ts). If it ran in
 * Europe/Vienna, these tests would also pass with a conversion that silently
 * uses the device time zone -- that is, with exactly the bug they rule out.
 */
describe('instantFromWallTime', () => {
  it('converts summer time', () => {
    expect(instantFromWallTime('2026-09-22T18:00', 'Europe/Vienna'))
      .toBe('2026-09-22T16:00:00.000Z')
  })

  it('converts winter time', () => {
    expect(instantFromWallTime('2026-12-01T09:00', 'Europe/Vienna'))
      .toBe('2026-12-01T08:00:00.000Z')
  })

  it('also handles negative offsets', () => {
    expect(instantFromWallTime('2026-09-22T18:00', 'America/New_York'))
      .toBe('2026-09-22T22:00:00.000Z')
  })

  it('hits midnight in the zone, not in UTC', () => {
    // The bug that became visible on the wall: Vienna midnight ended up
    // as 00:00 UTC and thus two hours too late.
    expect(instantFromWallTime('2026-09-22T00:00', 'Europe/Vienna'))
      .toBe('2026-09-21T22:00:00.000Z')
  })
})

describe('wallTimeFromInstant', () => {
  it('shows the time of the zone, not of the device', () => {
    expect(wallTimeFromInstant('2026-09-22T16:00:00.000Z', 'Europe/Vienna'))
      .toBe('2026-09-22T18:00')
  })

  it('reverses the conversion', () => {
    const wallTime = '2026-03-29T14:30'

    expect(wallTimeFromInstant(instantFromWallTime(wallTime, 'Europe/Vienna'), 'Europe/Vienna'))
      .toBe(wallTime)
  })

  it('shows midnight as 00:00, not as 24:00', () => {
    expect(wallTimeFromInstant('2026-09-21T22:00:00.000Z', 'Europe/Vienna'))
      .toBe('2026-09-22T00:00')
  })
})

describe('allDaySpan', () => {
  it('excludes the end: a day is midnight to midnight', () => {
    expect(allDaySpan('2026-09-22', '2026-09-22', 'Europe/Vienna'))
      .toEqual(['2026-09-21T22:00:00.000Z', '2026-09-22T22:00:00.000Z'])
  })

  it('leaves multi-day spans unchanged', () => {
    expect(allDaySpan('2026-09-22', '2026-09-24', 'Europe/Vienna'))
      .toEqual(['2026-09-21T22:00:00.000Z', '2026-09-23T22:00:00.000Z'])
  })
})
