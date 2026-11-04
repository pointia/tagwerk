import { describe, expect, it } from 'vitest'
import { daypartText, keepDayBonus, keepTicks, withDayBonus, occurrenceKey, occurrenceSlot, openFirst } from '../src/rules/task'

const teeth = { task_id: 7, date: '2026-09-29', for_person_id: 3, daypart: 'evening' }

describe('occurrenceKey', () => {
  it('keeps the rounds of one task apart', () => {
    const keys = new Set([
      occurrenceKey(teeth),
      occurrenceKey({ ...teeth, daypart: 'morning' }),
      occurrenceKey({ ...teeth, for_person_id: 4 }),
      occurrenceKey({ ...teeth, for_person_id: null, daypart: null }),
    ])

    expect(keys.size).toBe(4)
  })
})

describe('occurrenceSlot', () => {
  it('names person and time of day, nothing else', () => {
    expect(occurrenceSlot(teeth)).toEqual({ for_person_id: 3, daypart: 'evening' })
  })
})

describe('daypartText', () => {
  it('shows icon and time of day', () => {
    expect(daypartText({ daypart_icon: '🌙', daypart_label: 'abends' })).toBe('🌙 abends')
  })

  it('stays empty for any time of day', () => {
    expect(daypartText({ daypart_icon: null, daypart_label: null })).toBeNull()
  })
})

describe('openFirst', () => {
  it('moves ticked tasks to the end and keeps the order otherwise', () => {
    const entries = [
      { id: 1, done: true },
      { id: 2, done: false },
      { id: 3, done: true },
      { id: 4, done: false },
    ]

    expect(openFirst(entries).map(entry => entry.id)).toEqual([2, 4, 1, 3])
    expect(entries.map(entry => entry.id)).toEqual([1, 2, 3, 4])
  })
})

describe('keepTicks', () => {
  const open = { ...teeth, done: false, done_by_id: null, earned: 0 }
  const tick = { done: true, done_by_id: 3, earned: 2 }

  it('keeps a tick still on its way', () => {
    const ticks = { [occurrenceKey(teeth)]: { ...tick, at: Infinity } }

    expect(keepTicks([open], ticks, 5)[0]).toMatchObject({ done: true, done_by_id: 3, earned: 2 })
    expect(Object.keys(ticks)).toHaveLength(1)
  })

  it('keeps a tick confirmed after the load started', () => {
    const ticks = { [occurrenceKey(teeth)]: { ...tick, at: 6 } }

    expect(keepTicks([open], ticks, 5)[0]?.done).toBe(true)
  })

  it('trusts the load once it started after the confirmation', () => {
    const ticks = { [occurrenceKey(teeth)]: { ...tick, at: 4 } }

    // Someone else took it back meanwhile: the server's word counts.
    expect(keepTicks([open], ticks, 5)[0]?.done).toBe(false)
    expect(ticks).toEqual({})
  })

  it('leaves other occurrences alone', () => {
    const ticks = { [occurrenceKey({ ...teeth, daypart: 'morning' })]: { ...tick, at: Infinity } }

    expect(keepTicks([open], ticks, 5)[0]).toBe(open)
  })
})

describe('keepDayBonus', () => {
  const extra = { person_id: 3, date: '2026-09-29', points: 2, day_bonus: false }
  const day = { person_id: 3, date: '2026-09-29', points: 5, day_bonus: true }

  it('adds a bonus settled after the load started', () => {
    const settled = { '3|2026-09-29': { points: 5, at: 6 } }

    expect(keepDayBonus([extra], settled, 5)).toEqual([extra, day])
  })

  it('never counts a bonus twice when the load has it already', () => {
    const settled = { '3|2026-09-29': { points: 5, at: 6 } }

    expect(keepDayBonus([extra, day], settled, 5)).toEqual([extra, day])
  })

  it('takes a bonus back that the load still shows', () => {
    const settled = { '3|2026-09-29': { points: 0, at: 6 } }

    expect(keepDayBonus([extra, day], settled, 5)).toEqual([extra])
  })

  it('trusts a load that started after the settling', () => {
    const settled = { '3|2026-09-29': { points: 0, at: 4 } }

    expect(keepDayBonus([extra, day], settled, 5)).toEqual([extra, day])
    expect(settled).toEqual({})
  })
})

describe('withDayBonus', () => {
  it('leaves extra stars and other days alone', () => {
    const rows = [
      { person_id: 3, date: '2026-09-29', points: 2, day_bonus: false },
      { person_id: 3, date: '2026-09-28', points: 5, day_bonus: true },
    ]

    expect(withDayBonus(rows, 3, '2026-09-29', 0)).toEqual(rows)
  })
})
