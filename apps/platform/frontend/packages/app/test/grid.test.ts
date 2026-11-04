import { describe, expect, it } from 'vitest'
import type { Tile } from '../app/utils/grid'
import { cellOffset, freeCell, fits, overlaps, resized, shifted, rows } from '../app/utils/grid'

function tile(id: number, x: number, y: number, w = 3, h = 2): Tile {
  return { id, type: 'clock', x, y, w, h, settings: {} }
}

describe('overlaps', () => {
  it('detects an overlap at the corner', () => {
    expect(overlaps({ x: 0, y: 0, w: 3, h: 2 }, { x: 2, y: 1, w: 3, h: 2 })).toBe(true)
  })

  it('allows tiles edge to edge', () => {
    expect(overlaps({ x: 0, y: 0, w: 3, h: 2 }, { x: 3, y: 0, w: 3, h: 2 })).toBe(false)
    expect(overlaps({ x: 0, y: 0, w: 3, h: 2 }, { x: 0, y: 2, w: 3, h: 2 })).toBe(false)
  })
})

describe('fits', () => {
  const occupied = [tile(1, 0, 0), tile(2, 3, 0)]

  it('rejects a spot that is already taken', () => {
    expect(fits({ x: 1, y: 0, w: 3, h: 2 }, occupied)) .toBe(false)
  })

  it('does not treat a tile as its own obstacle', () => {
    expect(fits({ x: 0, y: 0, w: 4, h: 3 }, [tile(1, 0, 0)], 1)).toBe(true)
  })

  it('lets nothing stick out over the right edge', () => {
    expect(fits({ x: 10, y: 5, w: 4, h: 2 }, [])).toBe(false)
    expect(fits({ x: 9, y: 5, w: 3, h: 2 }, [])).toBe(true)
  })

  it('rejects negative cells', () => {
    expect(fits({ x: -1, y: 0, w: 2, h: 2 }, [])).toBe(false)
    expect(fits({ x: 0, y: -1, w: 2, h: 2 }, [])).toBe(false)
  })
})

describe('freeCell', () => {
  it('takes the top left while there is room', () => {
    expect(freeCell([], 3, 2)).toEqual({ x: 0, y: 0, w: 3, h: 2 })
  })

  it('moves next to an occupied corner', () => {
    expect(freeCell([tile(1, 0, 0, 6, 2)], 3, 2)).toEqual({ x: 6, y: 0, w: 3, h: 2 })
  })

  it('moves to the next row when the first is full', () => {
    expect(freeCell([tile(1, 0, 0, 12, 2)], 3, 2)).toEqual({ x: 0, y: 2, w: 3, h: 2 })
  })

  it('returns null when a tile is wider than the grid', () => {
    expect(freeCell([], 13, 2)).toBeNull()
  })
})

describe('cellOffset', () => {
  it('rounds to the nearest cell', () => {
    expect(cellOffset(60, 100)).toBe(1)
    expect(cellOffset(40, 100)).toBe(0)
    expect(cellOffset(-60, 100)).toBe(-1)
  })

  it('stays put at a cell width of zero', () => {
    expect(cellOffset(120, 0)).toBe(0)
  })
})

describe('shifted', () => {
  it('keeps the tile inside the grid', () => {
    expect(shifted({ x: 10, y: 0, w: 3, h: 2 }, 5, 0).x).toBe(9)
    expect(shifted({ x: 0, y: 0, w: 3, h: 2 }, -5, -5)).toEqual({ x: 0, y: 0, w: 3, h: 2 })
  })
})

describe('resized', () => {
  it('does not let a tile shrink below one cell', () => {
    expect(resized({ x: 0, y: 0, w: 2, h: 2 }, -5, -5)).toEqual({ x: 0, y: 0, w: 1, h: 1 })
  })

  it('does not let it grow over the right edge', () => {
    expect(resized({ x: 9, y: 0, w: 3, h: 2 }, 4, 0).w).toBe(3)
  })
})

describe('rows', () => {
  it('counts down to the lowest tile', () => {
    expect(rows([tile(1, 0, 0), tile(2, 3, 7, 3, 3)])).toBe(10)
  })

  it('keeps a minimum size so an empty grid does not collapse', () => {
    expect(rows([])).toBe(6)
  })
})
