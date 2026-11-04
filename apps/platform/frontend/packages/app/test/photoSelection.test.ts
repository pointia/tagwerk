import { describe, expect, it } from 'vitest'
import { everySelectable, stillThere, toggled } from '../app/utils/photoSelection'

const mine = { id: 1, can_delete: true }
const also = { id: 2, can_delete: true }
const foreign = { id: 3, can_delete: false }

describe('toggled', () => {
  it('adds and removes a photo', () => {
    expect(toggled([], mine)).toEqual([1])
    expect(toggled([1, 2], mine)).toEqual([2])
  })

  it('never adds a photo the person may not change', () => {
    expect(toggled([1], foreign)).toEqual([1])
  })
})

describe('everySelectable', () => {
  it('takes only what may be changed', () => {
    expect(everySelectable([mine, foreign, also])).toEqual([1, 2])
  })
})

describe('stillThere', () => {
  it('drops what a reload no longer lists', () => {
    expect(stillThere([1, 2, 3], [also, foreign])).toEqual([2, 3])
  })
})
