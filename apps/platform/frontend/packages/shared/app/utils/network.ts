/**
 * Writing is blocked offline, reading is not.
 *
 * The wall's ground rule only applies to the display: there must never be an
 * empty page. Silently swallowing an input, on the other hand, would be worse
 * than rejecting it -- nobody types an entry twice that they believe is
 * saved. Hence no retry queue: the write is refused while offline.
 */
export function isOffline(): boolean {
  return typeof navigator !== 'undefined' && !navigator.onLine
}

/**
 * Whether a response requires the parent PIN — 423 is the only case.
 *
 * Deliberately not 403: access is not denied but locked. The wall then asks
 * for the PIN and repeats the call.
 */
export function requiresPin(error: unknown): boolean {
  return (error as { response?: { status?: number } })?.response?.status === 423
}

/**
 * What the server said about a refused write: the first field message of a
 * validation, otherwise its message; null when nothing came along. Only a
 * refusal (4xx) explains itself — a crash says "Server Error", which tells
 * a family less than the app's own line.
 */
export function serverMessage(error: unknown): string | null {
  const failure = error as { statusCode?: number, data?: { message?: string, errors?: Record<string, string[]> } } | null

  if (failure?.statusCode === undefined || failure.statusCode >= 500) {
    return null
  }

  const data = failure.data

  return Object.values(data?.errors ?? {})[0]?.[0] ?? data?.message ?? null
}
