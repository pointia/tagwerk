import { describe, expect, it } from 'vitest'
import { type AgendaArea, asAreas, validMode } from '../app/utils/areas'

const CALENDAR_VIEWS: AgendaArea = { feature: 'calendar', mode: 'calendar', label: 'Kalender', icon: 'calendar' }
const LISTS: AgendaArea = { feature: 'lists', mode: 'lists', label: 'Listen', icon: 'lists' }
const MEALS: AgendaArea = { feature: 'meals', mode: 'meals', label: 'Essen', icon: 'meals' }

describe('asAreas', () => {
  it('takes name, icon and view from the server', () => {
    expect(asAreas([MEALS])).toEqual([
      { value: 'meals', feature: 'meals', label: 'Essen', icon: 'meals' },
    ])
  })

  // The order is the server's, not that of a list here.
  it('keeps the order of the response', () => {
    expect(asAreas([MEALS, CALENDAR_VIEWS]).map(entry => entry.value)).toEqual(['meals', 'calendar'])
  })
})

describe('validMode', () => {
  it('stays on the selected area while it exists', () => {
    expect(validMode('lists', asAreas([CALENDAR_VIEWS, LISTS]))).toBe('lists')
  })

  /*
   * Without this switch the wall would stay on a view that no longer exists
   * — and whose API has answered with 404 since it was switched off.
   */
  it('switches away when the shown area disappears', () => {
    expect(validMode('points', asAreas([CALENDAR_VIEWS, LISTS]))).toBe('calendar')
  })

  it('stays put when nothing is left', () => {
    expect(validMode('points', [])).toBe('points')
  })
})
