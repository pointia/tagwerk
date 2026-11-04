import { describe, expect, it } from 'vitest'
import { bookingDays, bookingText, affordable, riseKeys, share, shortfall } from '../app/utils/points'

/*
 * The same rule applies in the backend, where it is binding. It is here so
 * the tablet does not explain only after tapping that the balance is not
 * enough.
 */
describe('shortfall', () => {
  it('names the missing stars', () => {
    expect(shortfall(5, 20)).toBe(15)
  })

  it('is zero for an exactly matching balance', () => {
    expect(shortfall(20, 20)).toBe(0)
  })

  it('stays zero on surplus instead of going negative', () => {
    expect(shortfall(50, 20)).toBe(0)
  })

  it('copes with a negative balance', () => {
    expect(shortfall(-5, 20)).toBe(25)
  })
})

describe('affordable', () => {
  it('allows the request for an exactly matching balance', () => {
    expect(affordable(20, 20)).toBe(true)
  })

  it('denies it when one point is missing', () => {
    expect(affordable(19, 20)).toBe(false)
  })

  it('allows a free reward even without a balance', () => {
    expect(affordable(0, 0)).toBe(true)
  })
})

describe('bookingText', () => {
  it('shows the plus sign explicitly', () => {
    expect(bookingText(5)).toBe('+5')
  })

  /* A real minus sign, not a hyphen: on the wall, from two metres away, the
   * difference is between readable and not. */
  it('shows deductions with a minus sign', () => {
    expect(bookingText(-3)).toBe('−3')
  })
})

describe('share', () => {
  it('fills the bar by the way made, capped at full', () => {
    expect(share(25, 50)).toBe(50)
    expect(share(80, 50)).toBe(100)
    expect(share(-3, 50)).toBe(0)
    expect(share(5, 0)).toBe(0)
  })
})

describe('riseKeys', () => {
  it('changes only when the balance rises', () => {
    const key = riseKeys()

    expect(key(1, 5)).toBe(0)
    expect(key(1, 7)).toBe(1)
    expect(key(1, 6)).toBe(1)
    expect(key(1, 6)).toBe(1)
    expect(key(1, 8)).toBe(2)
  })

  it('counts every person on their own', () => {
    const key = riseKeys()

    key(1, 5)
    key(2, 5)

    expect(key(1, 9)).toBe(1)
    expect(key(2, 4)).toBe(0)
  })
})

describe('bookingDays', () => {
  const entry = (id: number, created_at: string | null) => ({ id, created_at })

  it('starts a new day where the date changes', () => {
    const days = bookingDays([
      entry(3, '2026-10-04T09:00:00Z'),
      entry(2, '2026-10-04T07:00:00Z'),
      entry(1, '2026-10-03T18:00:00Z'),
    ], 'Europe/Vienna')

    expect(days.map(day => [day.iso, day.entries.map(item => item.id)])).toEqual([
      ['2026-10-04', [3, 2]],
      ['2026-10-03', [1]],
    ])
  })

  // 23:30 UTC is already the next morning in Vienna.
  it('reads the day in the household zone, not the device one', () => {
    const days = bookingDays([entry(1, '2026-10-03T23:30:00Z')], 'Europe/Vienna')

    expect(days[0]?.iso).toBe('2026-10-04')
  })

  it('collects bookings without a time under an empty date', () => {
    expect(bookingDays([entry(1, null)], 'Europe/Vienna')).toEqual([{ iso: '', entries: [entry(1, null)] }])
  })
})
