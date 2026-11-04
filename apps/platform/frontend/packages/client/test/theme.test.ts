import { describe, expect, it } from 'vitest'
import { LONGEST_ALARM, type ThemeState, withoutServer, alarmIn } from '../src/rules/theme'

/** An autumn day in Vienna: light from 07:00, dark from 20:00 (UTC+2). */
function state(part: Partial<ThemeState> = {}): ThemeState {
  return {
    preference: 'auto',
    mode: 'fixed',
    theme: 'light',
    light_from: '2026-09-15T07:00:00+02:00',
    dark_from: '2026-09-15T20:00:00+02:00',
    next_change: '2026-09-15T20:00:00+02:00',
    backdrop: 'dawn',
    ...part,
  }
}

const at = (moment: string): number => Date.parse(moment)

describe('alarmIn', () => {
  it('schedules nothing when a permanent choice is set', () => {
    expect(alarmIn(state({ next_change: null }), at('2026-09-15T12:00:00+02:00'))).toBeNull()
  })

  /*
   * One second after the change, not exactly on it: if the interface asked
   * to the millisecond, the server might still be before the boundary and
   * answer with the old theme.
   */
  it('wakes one second after the switch', () => {
    expect(alarmIn(state(), at('2026-09-15T12:00:00+02:00'))).toBe(8 * 3600 * 1000 + 1000)
  })

  it('wakes immediately when the switch has already passed', () => {
    expect(alarmIn(state(), at('2026-09-15T20:00:05+02:00'))).toBe(0)
  })

  /** Beyond this setTimeout overflows and fires immediately — in a loop. */
  it('caps at the longest delay setTimeout honours', () => {
    expect(alarmIn(state({ next_change: '2026-12-24T20:00:00+01:00' }), at('2026-09-15T12:00:00+02:00')))
      .toBe(LONGEST_ALARM)
  })
})

describe('withoutServer', () => {
  it('keeps the theme before the switch', () => {
    const before = state()

    expect(withoutServer(before, at('2026-09-15T19:59:00+02:00'))).toBe(before)
  })

  it('still turns dark in the evening without network and schedules the morning', () => {
    const afterwards = withoutServer(state(), at('2026-09-15T20:00:30+02:00'))

    expect(afterwards.theme).toBe('dark')
    expect(afterwards.next_change).toBe('2026-09-16T05:00:00.000Z')
  })

  it('turns light in the morning without network and schedules the evening', () => {
    const night = state({ theme: 'dark', next_change: '2026-09-16T07:00:00+02:00' })
    const afterwards = withoutServer(night, at('2026-09-16T07:00:30+02:00'))

    expect(afterwards.theme).toBe('light')
    expect(afterwards.next_change).toBe('2026-09-16T18:00:00.000Z')
  })

  it('leaves a permanent choice untouched', () => {
    const dark = state({ preference: 'dark', theme: 'dark', next_change: null })

    expect(withoutServer(dark, at('2026-09-16T12:00:00+02:00'))).toBe(dark)
  })
})
