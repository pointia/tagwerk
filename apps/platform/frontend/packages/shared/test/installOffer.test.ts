import { describe, expect, it } from 'vitest'
import { installWay, isIos, snoozeOver } from '../app/utils/installOffer'

const DAY = 24 * 60 * 60 * 1000
const now = Date.UTC(2026, 9, 1, 12)

describe('snoozeOver', () => {
  it('asks when nothing was stored', () => {
    expect(snoozeOver(null, now)).toBe(true)
  })

  it('stays away for seven days after "later"', () => {
    expect(snoozeOver(now - 6 * DAY, now)).toBe(false)
    expect(snoozeOver(now - 7 * DAY + 1, now)).toBe(false)
  })

  it('asks again once seven days have passed', () => {
    expect(snoozeOver(now - 7 * DAY, now)).toBe(true)
    expect(snoozeOver(now - 30 * DAY, now)).toBe(true)
  })

  it('treats a broken value as nothing stored', () => {
    expect(snoozeOver(Number.NaN, now)).toBe(true)
  })
})

describe('isIos', () => {
  it('knows iPhone and iPad', () => {
    expect(isIos('Mozilla/5.0 (iPhone; CPU iPhone OS 18_0 like Mac OS X)', 5)).toBe(true)
    expect(isIos('Mozilla/5.0 (iPad; CPU OS 17_0 like Mac OS X)', 5)).toBe(true)
  })

  it('tells an iPad posing as a Mac from a real Mac by touch', () => {
    const mac = 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7)'

    expect(isIos(mac, 5)).toBe(true)
    expect(isIos(mac, 0)).toBe(false)
  })

  it('leaves Android alone', () => {
    expect(isIos('Mozilla/5.0 (Linux; Android 14; SM-X200)', 5)).toBe(false)
  })
})

describe('installWay', () => {
  it('offers nothing once installed, even with a prompt at hand', () => {
    expect(installWay({ installed: true, prompt: true, ios: false })).toBeNull()
  })

  it('prefers the browser prompt', () => {
    expect(installWay({ installed: false, prompt: true, ios: false })).toBe('prompt')
  })

  it('explains the way on iOS', () => {
    expect(installWay({ installed: false, prompt: false, ios: true })).toBe('ios')
  })

  it('stays silent where neither works', () => {
    expect(installWay({ installed: false, prompt: false, ios: false })).toBeNull()
  })
})
