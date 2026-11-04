import { describe, expect, it } from 'vitest'
import { celebration } from '../app/utils/celebration'

describe('celebration', () => {
  it('stays the same for a person on a day', () => {
    expect(celebration('2026-09-30', 4)).toEqual(celebration('2026-09-30', 4))
  })

  it('varies between people and days', () => {
    const seen = new Set<string>()

    for (let day = 1; day <= 20; day++) {
      for (const person of [1, 2, 3, null]) {
        seen.add(celebration(`2026-10-${String(day).padStart(2, '0')}`, person).face)
      }
    }

    expect(seen.size).toBeGreaterThan(5)
  })

  it('picks one of the praises', () => {
    expect(celebration('2026-10-01', 2).praise).toMatch(/^celebrate\.praise\.p[0-5]$/)
  })
})
