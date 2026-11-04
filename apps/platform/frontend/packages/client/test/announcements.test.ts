import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { gatherAnnouncements } from '../src/rules/announcements'

describe('gatherAnnouncements', () => {
  beforeEach(() => {
    vi.useFakeTimers()
  })

  afterEach(() => {
    vi.useRealTimers()
  })

  it('reloads at once when the device is quiet', async () => {
    const runs: string[][] = []
    const announce = gatherAnnouncements(areas => runs.push(areas), () => 0)

    announce('tasks')
    await vi.runAllTimersAsync()

    expect(runs).toEqual([['tasks']])
  })

  it('waits while the device keeps writing and then reloads once', async () => {
    const runs: string[][] = []
    let until = Date.now() + 3000
    const announce = gatherAnnouncements(areas => runs.push(areas), () => until)

    announce('tasks')
    announce('points')
    await vi.advanceTimersByTimeAsync(2000)

    // Another tick: the quiet starts over.
    until = Date.now() + 3000
    announce('tasks')
    await vi.advanceTimersByTimeAsync(2500)

    expect(runs).toEqual([])

    await vi.advanceTimersByTimeAsync(600)

    expect(runs).toEqual([['tasks', 'points']])
  })

  it('gathers announcements during a reload into one more', async () => {
    const runs: string[][] = []
    let finish = (): void => {}
    const announce = gatherAnnouncements((areas) => {
      runs.push(areas)

      return runs.length === 1 ? new Promise<void>((resolve) => { finish = resolve }) : undefined
    }, () => 0)

    announce('tasks')
    await vi.runAllTimersAsync()
    announce('points')
    announce('lists')
    await vi.runAllTimersAsync()
    finish()
    await vi.runAllTimersAsync()

    expect(runs).toEqual([['tasks'], ['points', 'lists']])
  })
})
