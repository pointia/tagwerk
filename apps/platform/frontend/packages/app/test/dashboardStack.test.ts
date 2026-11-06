import { describe, expect, it } from 'vitest'
import { reflowed, stacked } from '../app/utils/grid'

describe('stacked', () => {
  const tile = (id: number, x: number, y: number) => ({ id, x, y, w: 3, h: 2, type: 'clock', settings: {} })

  // Reading order: row by row, left to right within a row.
  it('orders tiles top to bottom, then left to right', () => {
    const order = stacked([tile(1, 6, 2), tile(2, 0, 0), tile(3, 0, 2), tile(4, 9, 0)]).map(entry => entry.id)

    expect(order).toEqual([2, 4, 3, 1])
  })
})

describe('reflowed', () => {
  const tile = (id: number, x: number, y: number, w: number, h: number) => ({ id, x, y, w, h, type: 'clock', settings: {} })

  // The default layout upright: calendar across, then two tiles per row.
  it('sets the landscape tiles anew on six columns, keeping their sizes', () => {
    const placed = reflowed([
      tile(1, 0, 0, 6, 6),
      tile(2, 6, 0, 3, 2),
      tile(3, 9, 0, 3, 2),
      tile(4, 6, 2, 3, 4),
      tile(5, 9, 2, 3, 4),
    ]).map(({ id, x, y, w, h }) => ({ id, x, y, w, h }))

    expect(placed).toEqual([
      { id: 1, x: 0, y: 0, w: 6, h: 6 },
      { id: 2, x: 0, y: 6, w: 3, h: 2 },
      { id: 3, x: 3, y: 6, w: 3, h: 2 },
      { id: 4, x: 0, y: 8, w: 3, h: 4 },
      { id: 5, x: 3, y: 8, w: 3, h: 4 },
    ])
  })

  it('narrows a tile wider than the half grid', () => {
    expect(reflowed([tile(1, 0, 0, 12, 2)])[0]).toMatchObject({ x: 0, w: 6 })
  })
})
