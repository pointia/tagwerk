import { describe, expect, it } from 'vitest'
import { adjustTimer, clockText, elapsedMs, remainingMs, timerParts } from '../app/utils/clockTools'

describe('clockText', () => {
  it('shows minutes and seconds below an hour', () => {
    expect(clockText(0)).toBe('00:00')
    expect(clockText(65_000)).toBe('01:05')
  })

  it('adds hours from one hour on', () => {
    expect(clockText(3_725_000)).toBe('1:02:05')
  })

  // A timer at 0.4 s left still shows one second, not zero.
  it('rounds a countdown up, so zero means done', () => {
    expect(clockText(400, 'up')).toBe('00:01')
    expect(clockText(400)).toBe('00:00')
  })
})

describe('elapsedMs', () => {
  it('adds the running stretch to what was stopped before', () => {
    expect(elapsedMs({ startedAt: 1_000, banked: 5_000 }, 4_000)).toBe(8_000)
  })

  it('is what was banked while stopped', () => {
    expect(elapsedMs({ startedAt: null, banked: 5_000 }, 99_000)).toBe(5_000)
  })
})

describe('remainingMs', () => {
  it('counts down to the end', () => {
    expect(remainingMs({ endsAt: 10_000, left: 60_000 }, 4_000)).toBe(6_000)
  })

  it('never goes below zero', () => {
    expect(remainingMs({ endsAt: 10_000, left: 60_000 }, 12_000)).toBe(0)
  })

  it('keeps what was left while paused', () => {
    expect(remainingMs({ endsAt: null, left: 42_000 }, 99_000)).toBe(42_000)
  })
})

describe('adjustTimer', () => {
  it('moves minutes and keeps the seconds', () => {
    expect(adjustTimer(5 * 60_000 + 30_000, 'minutes', 2)).toBe(7 * 60_000 + 30_000)
  })

  it('stops minutes at zero and 99', () => {
    expect(adjustTimer(60_000, 'minutes', -5)).toBe(0)
    expect(adjustTimer(98 * 60_000, 'minutes', 5)).toBe(99 * 60_000)
  })

  // A wheel: past 59 comes 0, and the minutes stay where they are.
  it('turns seconds around without touching the minutes', () => {
    expect(adjustTimer(3 * 60_000 + 58_000, 'seconds', 3)).toBe(3 * 60_000 + 1_000)
    expect(adjustTimer(3 * 60_000, 'seconds', -1)).toBe(3 * 60_000 + 59_000)
  })
})

describe('timerParts', () => {
  it('counts minutes past the hour instead of showing hours', () => {
    expect(timerParts(75 * 60_000 + 400)).toEqual({ minutes: 75, seconds: 1 })
  })
})
