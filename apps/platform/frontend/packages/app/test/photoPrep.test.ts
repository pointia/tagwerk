import { describe, expect, it } from 'vitest'
import { exifDate, fitWithin, hex, isPicture, nameDate } from '../app/utils/photoPrep'

/**
 * A minimal JPEG start with an EXIF block: IFD0 with DateTime and a pointer
 * to the Exif IFD, which holds DateTimeOriginal.
 */
function jpeg(options: { original?: string, changed?: string, little?: boolean }): ArrayBuffer {
  const little = options.little ?? true
  const tiff: number[] = []
  const u16 = (value: number): number[] => little ? [value & 0xFF, value >> 8] : [value >> 8, value & 0xFF]
  const u32 = (value: number): number[] => little
    ? [value & 0xFF, (value >> 8) & 0xFF, (value >> 16) & 0xFF, value >>> 24]
    : [value >>> 24, (value >> 16) & 0xFF, (value >> 8) & 0xFF, value & 0xFF]
  const ascii = (text: string): number[] => [...text].map(char => char.charCodeAt(0)).concat(0)

  // Layout: header (8) | IFD0 (2 + 2*12 + 4 = 30) | Exif IFD (2 + 12 + 4 = 18) | strings
  const ifd0 = 8
  const exifIfd = ifd0 + 30
  const strings = exifIfd + 18
  const changed = options.changed ?? '0000:00:00 00:00:00'
  const original = options.original ?? '0000:00:00 00:00:00'

  tiff.push(...(little ? [0x49, 0x49] : [0x4D, 0x4D]), ...u16(42), ...u32(ifd0))
  tiff.push(...u16(2))
  tiff.push(...u16(0x0132), ...u16(2), ...u32(20), ...u32(strings))
  tiff.push(...u16(0x8769), ...u16(4), ...u32(1), ...u32(exifIfd))
  tiff.push(...u32(0))
  tiff.push(...u16(1))
  tiff.push(...u16(0x9003), ...u16(2), ...u32(20), ...u32(strings + 20))
  tiff.push(...u32(0))
  tiff.push(...ascii(changed), ...ascii(original))

  const app1 = [0x45, 0x78, 0x69, 0x66, 0, 0, ...tiff]
  const bytes = [0xFF, 0xD8, 0xFF, 0xE1, (app1.length + 2) >> 8, (app1.length + 2) & 0xFF, ...app1, 0xFF, 0xDA, 0, 2]

  return new Uint8Array(bytes).buffer
}

describe('exifDate', () => {
  it('reads the day the picture was taken', () => {
    expect(exifDate(jpeg({ original: '2024:05:12 18:30:00', changed: '2025:01:01 10:00:00' }))).toBe('2024-05-12')
  })

  it('reads big-endian files from cameras as well', () => {
    expect(exifDate(jpeg({ original: '2019:07:04 09:00:00', little: false }))).toBe('2019-07-04')
  })

  it('falls back to the last change when the original day is missing', () => {
    expect(exifDate(jpeg({ changed: '2021:03:02 08:00:00' }))).toBe('2021-03-02')
  })

  // A camera whose clock was never set writes zeros — that is no date.
  it('ignores zeros and files without EXIF', () => {
    expect(exifDate(jpeg({}))).toBeNull()
    expect(exifDate(new Uint8Array([0xFF, 0xD8, 0xFF, 0xDA, 0, 2]).buffer)).toBeNull()
    expect(exifDate(new Uint8Array([0x89, 0x50, 0x4E, 0x47]).buffer)).toBeNull()
  })

  it('does not run past a cut-off file', () => {
    expect(exifDate(jpeg({ original: '2024:05:12 18:30:00' }).slice(0, 40))).toBeNull()
  })
})

describe('nameDate', () => {
  it('knows how phones name their pictures', () => {
    expect(nameDate('IMG_20240512_183000.jpg')).toBe('2024-05-12')
    expect(nameDate('PXL_20220701_090000123.jpg')).toBe('2022-07-01')
    expect(nameDate('2023-12-24 18.03.11.jpg')).toBe('2023-12-24')
  })

  it('finds no date where there is none', () => {
    expect(nameDate('IMG_1234.jpg')).toBeNull()
    expect(nameDate('20241399.jpg')).toBeNull()
  })
})

describe('fitWithin', () => {
  it('shrinks the long edge and keeps the aspect ratio', () => {
    expect(fitWithin(4032, 3024)).toEqual({ width: 1920, height: 1440 })
    expect(fitWithin(3024, 4032)).toEqual({ width: 1440, height: 1920 })
  })

  it('never enlarges a small picture', () => {
    expect(fitWithin(800, 600)).toEqual({ width: 800, height: 600 })
  })
})

describe('isPicture', () => {
  it('takes pictures and skips videos in a folder', () => {
    expect(isPicture({ name: 'a.jpg', type: 'image/jpeg' })).toBe(true)
    expect(isPicture({ name: 'IMG_1.HEIC', type: '' })).toBe(true)
    expect(isPicture({ name: 'clip.mp4', type: 'video/mp4' })).toBe(false)
  })
})

describe('hex', () => {
  it('writes bytes as lowercase hex', () => {
    expect(hex(new Uint8Array([0, 15, 255]).buffer)).toBe('000fff')
  })
})
