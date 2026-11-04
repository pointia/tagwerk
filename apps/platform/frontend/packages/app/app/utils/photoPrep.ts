/*
 * Getting a phone photo ready for the frame: its day, its size, a
 * fingerprint of the original. Plain functions on bytes and numbers, so they
 * can be tested without a browser.
 */

/** Long edge sent to the server — the same the server stores. */
export const EDGE = 1920

/** EXIF sits at the start of a JPEG; this much is always enough. */
export const EXIF_BYTES = 128 * 1024

const DATE_TIME = 0x0132
const EXIF_POINTER = 0x8769
const DATE_TIME_ORIGINAL = 0x9003

/**
 * The day a JPEG was taken, from its EXIF block: `DateTimeOriginal`, else
 * `DateTime`. Null for anything else — the canvas drops EXIF, so it has to
 * be read before shrinking.
 */
export function exifDate(buffer: ArrayBuffer): string | null {
  const view = new DataView(buffer)

  if (view.byteLength < 4 || view.getUint16(0) !== 0xFFD8) {
    return null
  }

  let offset = 2

  while (offset + 4 <= view.byteLength) {
    const marker = view.getUint16(offset)
    const length = view.getUint16(offset + 2)

    // APP1 with "Exif\0\0"
    if (marker === 0xFFE1 && offset + 10 <= view.byteLength && view.getUint32(offset + 4) === 0x45786966) {
      return tiffDate(view, offset + 10)
    }

    // Start of scan: the picture data begins, no EXIF came.
    if (marker === 0xFFDA || (marker & 0xFF00) !== 0xFF00) {
      return null
    }

    offset += 2 + length
  }

  return null
}

function tiffDate(view: DataView, tiff: number): string | null {
  if (tiff + 8 > view.byteLength) {
    return null
  }

  const little = view.getUint16(tiff) === 0x4949
  const u16 = (at: number): number => view.getUint16(at, little)
  const u32 = (at: number): number => view.getUint32(at, little)

  const entries = (ifd: number): Map<number, number> => {
    const found = new Map<number, number>()

    if (ifd + 2 > view.byteLength) {
      return found
    }

    const count = u16(ifd)

    for (let index = 0; index < count; index++) {
      const entry = ifd + 2 + index * 12

      if (entry + 12 > view.byteLength) {
        break
      }

      // The value field: an offset for strings longer than four bytes.
      found.set(u16(entry), u32(entry + 8))
    }

    return found
  }

  const text = (pointer: number | undefined): string | null => {
    if (pointer === undefined || tiff + pointer + 10 > view.byteLength) {
      return null
    }

    let value = ''

    for (let index = 0; index < 10; index++) {
      value += String.fromCharCode(view.getUint8(tiff + pointer + index))
    }

    return value
  }

  const first = entries(tiff + u32(tiff + 4))
  const exifIfd = first.get(EXIF_POINTER)
  const original = exifIfd === undefined ? null : text(entries(tiff + exifIfd).get(DATE_TIME_ORIGINAL))

  return stamp(original) ?? stamp(text(first.get(DATE_TIME)))
}

/** "2024:05:12" → "2024-05-12"; a camera without a set clock writes zeros. */
function stamp(value: string | null): string | null {
  const match = value?.match(/^(\d{4}):(\d{2}):(\d{2})$/)

  return match ? isoDate(Number(match[1]), Number(match[2]), Number(match[3])) : null
}

/**
 * A date in the file name, as phones write it: "IMG_20240512_183000.jpg",
 * "PXL_20220701_…", "2023-12-24 18.03.11.jpg". The same rule as the server's
 * `TakenOn::fromName`.
 */
export function nameDate(name: string): string | null {
  const match = name.match(/(?<!\d)((?:19|20)\d{2})[-_.]?(\d{2})[-_.]?(\d{2})(?!\d)/)

  return match ? isoDate(Number(match[1]), Number(match[2]), Number(match[3])) : null
}

function isoDate(year: number, month: number, day: number): string | null {
  const date = new Date(Date.UTC(year, month - 1, day))

  if (year < 1900 || date.getUTCMonth() !== month - 1 || date.getUTCDate() !== day) {
    return null
  }

  return `${year}-${String(month).padStart(2, '0')}-${String(day).padStart(2, '0')}`
}

/** Size within EDGE, aspect ratio kept, never enlarged. */
export function fitWithin(width: number, height: number, edge = EDGE): { width: number, height: number } {
  const factor = Math.min(1, edge / Math.max(width, height))

  return {
    width: Math.max(1, Math.round(width * factor)),
    height: Math.max(1, Math.round(height * factor)),
  }
}

/** Only what a browser can draw; videos and the like in a folder are skipped. */
export function isPicture(file: { name: string, type: string }): boolean {
  return /^image\/(jpeg|png|webp|heic|heif)$/.test(file.type)
    || /\.(jpe?g|png|webp|heic|heif)$/i.test(file.name)
}

export function hex(buffer: ArrayBuffer): string {
  return [...new Uint8Array(buffer)].map(byte => byte.toString(16).padStart(2, '0')).join('')
}
