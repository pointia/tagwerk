import { describe, expect, it } from 'vitest'
import { arranged, moved } from '../app/utils/menu'

const areas = ['dashboard', 'calendar', 'tasks', 'lists']

describe('arranged', () => {
  it('keeps the default without a choice', () => {
    expect(arranged(areas, [], [])).toEqual({ all: areas, visible: areas })
  })

  it('puts the chosen order first and the rest behind in default order', () => {
    expect(arranged(areas, ['lists', 'calendar'], []).all).toEqual(['lists', 'calendar', 'dashboard', 'tasks'])
  })

  it('shows an area added later instead of swallowing it', () => {
    expect(arranged([...areas, 'photos'], ['tasks'], ['lists']).visible).toEqual(['tasks', 'dashboard', 'calendar', 'photos'])
  })

  it('ignores areas the household does not have', () => {
    expect(arranged(areas, ['photos', 'tasks'], ['meals']).all).toEqual(['tasks', 'dashboard', 'calendar', 'lists'])
  })

  it('never leaves the menu empty', () => {
    expect(arranged(areas, [], areas).visible).toEqual(['dashboard'])
  })
})

describe('moved', () => {
  it('swaps with the neighbour', () => {
    expect(moved(areas, 'tasks', -1)).toEqual(['dashboard', 'tasks', 'calendar', 'lists'])
  })

  it('stays put at the edge', () => {
    expect(moved(areas, 'lists', 1)).toEqual(areas)
  })
})
