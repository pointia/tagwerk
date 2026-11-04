import { describe, expect, it } from 'vitest'
import { shownView } from '../app/utils/calendarView'

describe('shownView', () => {
  it('keeps every view on a wide screen', () => {
    expect(shownView('week', false)).toBe('week')
    expect(shownView('list', false)).toBe('list')
  })

  it('shows one day where a week or month does not fit', () => {
    expect(shownView('week', true)).toBe('day')
    expect(shownView('month', true)).toBe('day')
    expect(shownView('three', true)).toBe('three')
    expect(shownView('list', true)).toBe('list')
  })

  it('falls back to one day for an unknown value', () => {
    expect(shownView('', false)).toBe('day')
  })
})
