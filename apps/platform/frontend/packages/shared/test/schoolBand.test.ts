import { describe, expect, it } from 'vitest'
import { bandLayout } from '../app/utils/schoolBand'

const lesson = (personId: number) => ({ person_id: personId })

describe('bandLayout', () => {
  it('takes no room without lessons', () => {
    expect(bandLayout([])).toEqual({ width: 0, lanes: [] })
  })

  it('gives a single child the whole band', () => {
    expect(bandLayout([lesson(4), lesson(4)])).toEqual({ width: 22, lanes: [4] })
  })

  it('puts children side by side instead of on top of each other', () => {
    const layout = bandLayout([lesson(7), lesson(3), lesson(7)])

    expect(layout.lanes).toEqual([3, 7])
    expect(layout.width).toBe(33)
  })

  it('caps the band so the events keep their room', () => {
    expect(bandLayout([lesson(1), lesson(2), lesson(3), lesson(4)]).width).toBe(33)
  })
})
