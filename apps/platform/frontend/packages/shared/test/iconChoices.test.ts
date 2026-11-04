import { describe, expect, it } from 'vitest'
import { iconChoices } from '../app/utils/iconChoices'

const icons = ['🪥', '🥦', '🥕', '🍎']
const words = {
  '🪥': 'zähne putzen brush teeth',
  '🥦': 'brokkoli gemüse broccoli vegetables',
  '🥕': 'karotte gemüse carrot vegetables',
  '🍎': 'obst apfel fruit apple',
}

describe('iconChoices', () => {
  it('keeps the order without search or title', () => {
    expect(iconChoices(icons, words, '', '')).toEqual(icons)
  })

  it('puts icons matching the title first', () => {
    expect(iconChoices(icons, words, '', 'Gemüse gegessen')).toEqual(['🥦', '🥕', '🪥', '🍎'])
  })

  it('narrows to the search, in both languages', () => {
    expect(iconChoices(icons, words, 'Obst', '')).toEqual(['🍎'])
    expect(iconChoices(icons, words, 'veget', '')).toEqual(['🥦', '🥕'])
    expect(iconChoices(icons, words, 'gemüse karotte', '')).toEqual(['🥕'])
  })

  it('ignores terms shorter than three letters', () => {
    expect(iconChoices(icons, words, 'a', '')).toEqual(icons)
  })
})
