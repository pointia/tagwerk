import { describe, expect, it } from 'vitest'
import { stacked } from '../app/utils/grid'

describe('stacked', () => {
  const tile = (id: number, x: number, y: number) => ({ id, x, y, w: 3, h: 2, type: 'clock', settings: {} })

  // Reading order: row by row, left to right within a row.
  it('orders tiles top to bottom, then left to right', () => {
    const order = stacked([tile(1, 6, 2), tile(2, 0, 0), tile(3, 0, 2), tile(4, 9, 0)]).map(entry => entry.id)

    expect(order).toEqual([2, 4, 3, 1])
  })
})
