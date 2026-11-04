import { describe, expect, it } from 'vitest'
import { motionStill } from '../app/utils/motion'

describe('motionStill', () => {
  it('moves when the device switch is on, whatever the system says', () => {
    expect(motionStill('on', true)).toBe(false)
  })

  it('stays still when the device switch is off', () => {
    expect(motionStill('off', false)).toBe(true)
  })

  it('follows the system before the switch is known', () => {
    expect(motionStill(undefined, true)).toBe(true)
    expect(motionStill(undefined, false)).toBe(false)
  })
})
