import { describe, expect, it } from 'vitest'
import { actorCan } from '../app/utils/actor'

describe('actorCan', () => {
  it('allows nothing before anyone is known', () => {
    expect(actorCan(null, 'lists.edit')).toBe(false)
  })

  it('lets a device try everything, the PIN decides on the server', () => {
    expect(actorCan({ kind: 'device', personId: null, permissions: [] }, 'tasks.manage')).toBe(true)
  })

  it('gives an account only what it holds', () => {
    const child = { kind: 'user' as const, personId: 3, permissions: ['tasks.complete_own'] }

    expect(actorCan(child, 'tasks.complete_own')).toBe(true)
    expect(actorCan(child, 'tasks.manage')).toBe(false)
  })
})
