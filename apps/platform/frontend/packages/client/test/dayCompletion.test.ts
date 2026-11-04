import { describe, expect, it } from 'vitest'
import { justFinished, isDone, openOnDay } from '../src/rules/dayCompletion'

const today = '2026-09-22'
const tomorrow = '2026-09-23'

describe('isDone', () => {
  it('holds when nothing is open on the day anymore', () => {
    expect(isDone([{ date: today, done: true }], today)).toBe(true)
  })

  it('does not hold while something is open', () => {
    expect(isDone([
      { date: today, done: true },
      { date: today, done: false },
    ], today)).toBe(false)
  })

  it('does not consider an empty day done', () => {
    expect(isDone([], today)).toBe(false)
    expect(isDone([{ date: tomorrow, done: false }], today)).toBe(false)
  })
})

describe('openOnDay', () => {
  it('counts only its own day', () => {
    expect(openOnDay([
      { date: today, done: false },
      { date: today, done: true },
      { date: tomorrow, done: false },
    ], today)).toBe(1)
  })
})

describe('justFinished', () => {
  it('celebrates the last check', () => {
    const before = [{ date: today, done: true }, { date: today, done: false }]
    const after = [{ date: today, done: true }, { date: today, done: true }]

    expect(justFinished(before, after, today)).toBe(true)
  })

  it('does not celebrate the same day twice', () => {
    const finished = [{ date: today, done: true }]

    expect(justFinished(finished, finished, today)).toBe(false)
  })

  it('does not celebrate while something stays open', () => {
    const before = [{ date: today, done: false }, { date: today, done: false }]
    const after = [{ date: today, done: true }, { date: today, done: false }]

    expect(justFinished(before, after, today)).toBe(false)
  })
})
