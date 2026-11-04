import { describe, expect, it } from 'vitest'
import { coversRange } from '../app/utils/agenda'

describe('coversRange', () => {
  const range = { from: '2026-09-29T00:00:00+02:00', to: '2026-10-01T23:59:59+02:00' }

  it('accepts the range that was asked for', () => {
    expect(coversRange(range, '2026-09-29', '2026-10-01')).toBe(true)
  })

  // The service worker's fallback carries whatever was loaded last.
  it('rejects a response for other days', () => {
    expect(coversRange(range, '2026-10-02', '2026-10-04')).toBe(false)
    expect(coversRange(range, '2026-09-29', '2026-10-05')).toBe(false)
  })

  it('reads the day in the household zone, not in UTC', () => {
    // Vienna midnight is 22:00 UTC the day before.
    expect(coversRange({ from: '2026-09-29T00:00:00+02:00', to: '2026-09-29T23:59:59+02:00' }, '2026-09-29', '2026-09-29')).toBe(true)
  })
})
