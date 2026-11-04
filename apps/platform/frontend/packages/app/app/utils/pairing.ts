/**
 * The pairing code from the address bar.
 *
 * The QR code in the admin points to the wall and appends the code. Whoever
 * scans it with the tablet lands here and should not have to type anything.
 */
export function codeFromUrl(searchPart: string): string | null {
  const raw = new URLSearchParams(searchPart).get('pair')

  if (raw === null) {
    return null
  }

  // Upper case, because the codes' alphabet has no lower-case letters
  // and some scanners lower-case the address.
  const code = raw.trim().toUpperCase()

  return code === '' ? null : code
}
