import { describe, expect, it } from 'vitest'
import { areaEmoji } from '../app/utils/areaEmoji'

describe('areaEmoji', () => {
  it('gives every area icon a colourful emoji', () => {
    for (const icon of ['dashboard', 'today', 'calendar', 'tasks', 'lists', 'points', 'meals', 'recipes', 'school', 'photo']) {
      expect(areaEmoji(icon)).not.toBeNull()
    }
  })

  it('keeps the line icon for an unknown area', () => {
    expect(areaEmoji('something-new')).toBeNull()
  })
})
