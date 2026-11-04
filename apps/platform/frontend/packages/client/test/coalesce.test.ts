import { describe, expect, it } from 'vitest'
import { coalesce } from '../src/rules/coalesce'

function gate(): { promise: Promise<void>, open: () => void } {
  let open = (): void => {}
  const promise = new Promise<void>((resolve) => {
    open = resolve
  })

  return { promise, open }
}

describe('coalesce', () => {
  it('turns a burst during a run into one more run', async () => {
    const gates = [gate(), gate()]
    let runs = 0
    const run = coalesce(() => gates[runs++]!.promise)

    const first = run()
    for (let i = 0; i < 50; i++) {
      void run()
    }

    gates[0]!.open()
    await Promise.resolve()
    gates[1]!.open()
    await first

    expect(runs).toBe(2)
  })

  it('runs again once the previous run is over', async () => {
    let runs = 0
    const run = coalesce(async () => {
      runs++
    })

    await run()
    await run()

    expect(runs).toBe(2)
  })

  it('keeps going after a failed run', async () => {
    let runs = 0
    const run = coalesce(async () => {
      runs++
      throw new Error('offline')
    })

    await run()
    await run()

    expect(runs).toBe(2)
  })
})
