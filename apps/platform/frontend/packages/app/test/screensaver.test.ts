import { afterEach, describe, expect, it, vi } from 'vitest'
import { carousel, dimScreen, idleMs, nextSlide, previewScreen, restingBrightness, restingDim, restoreScreen, slideMs, veilOpacity } from '../app/utils/screensaver'

describe('carousel', () => {
  it('keeps the chosen order', () => {
    expect(carousel(['photos', 'calendar', 'tasks'], ['calendar', 'tasks', 'photos'])).toEqual(['photos', 'calendar', 'tasks'])
  })

  it('drops areas the household switched off or that are for working', () => {
    expect(carousel(['calendar', 'points', 'lists'], ['calendar', 'lists'])).toEqual(['calendar'])
  })

  it('is empty without a choice — no resting mode', () => {
    expect(carousel([], ['calendar'])).toEqual([])
  })
})

describe('idleMs', () => {
  it('reads minutes', () => {
    expect(idleMs('2')).toBe(120_000)
  })

  it('falls back to five minutes', () => {
    expect(idleMs('')).toBe(300_000)
  })
})

describe('slideMs', () => {
  it('uses the interval', () => {
    expect(slideMs('calendar', '15', 30_000)).toBe(15_000)
  })

  it('gives each page its own interval', () => {
    expect(slideMs('timetable', '15', 30_000, 3)).toBe(45_000)
    expect(slideMs('timetable', '15', 30_000, 0)).toBe(15_000)
  })

  it('gives photos several pictures', () => {
    expect(slideMs('photos', '15', 30_000)).toBe(90_000)
  })
})

describe('nextSlide', () => {
  it('starts over at the end', () => {
    expect(nextSlide(2, 3)).toBe(0)
    expect(nextSlide(0, 3)).toBe(1)
  })
})

describe('restingDim', () => {
  it('takes the night value while the theme is dark', () => {
    expect(restingDim('50', '70', 'dark')).toBe('70')
    expect(restingDim('50', '70', 'light')).toBe('50')
  })

  it('stays with the day value until the theme is known', () => {
    expect(restingDim('50', '70', undefined)).toBe('50')
  })
})

describe('veilOpacity', () => {
  it('follows the chosen darkness', () => {
    expect(veilOpacity('50')).toBe(0.5)
    expect(veilOpacity('0')).toBe(0)
    expect(veilOpacity('90')).toBe(0.9)
  })

  it('falls back to the default when unreadable', () => {
    expect(veilOpacity('')).toBe(0.5)
  })
})

describe('restingBrightness', () => {
  it('lowers the brightness by the chosen share', () => {
    expect(restingBrightness(200, '50')).toBe(100)
    expect(restingBrightness(200, '90')).toBe(20)
    expect(restingBrightness(200, '0')).toBe(200)
  })
})

describe('dimScreen', () => {
  afterEach(() => {
    vi.unstubAllGlobals()
  })

  it('does nothing in a plain browser', () => {
    expect(dimScreen('50')).toBeNull()
  })

  it('lowers Fully Kiosk\'s brightness and gives it back', () => {
    let brightness = 180
    vi.stubGlobal('fully', {
      getScreenBrightness: () => brightness,
      setScreenBrightness: (value: number) => {
        brightness = value
      },
    })

    const before = dimScreen('80')
    expect(brightness).toBe(36)

    restoreScreen(before)
    expect(brightness).toBe(180)
  })

  it('previews each step from the brightness before the drag, not the last step', () => {
    let brightness = 200
    vi.stubGlobal('fully', {
      getScreenBrightness: () => brightness,
      setScreenBrightness: (value: number) => {
        brightness = value
      },
    })

    const before = previewScreen('50', null)
    expect(brightness).toBe(100)

    expect(previewScreen('80', before)).toBe(200)
    expect(brightness).toBe(40)

    restoreScreen(before)
    expect(brightness).toBe(200)
  })

  it('previews nothing in a plain browser', () => {
    expect(previewScreen('50', null)).toBeNull()
  })
})
