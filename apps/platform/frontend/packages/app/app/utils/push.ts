/**
 * The public VAPID key comes from the server as Base64-URL and is required
 * by the browser as a byte sequence.
 *
 * Base64-URL is not the same as Base64: `-` and `_` stand for `+` and `/`,
 * and the padding is missing. Without both corrections atob() throws — or,
 * worse, silently returns wrong bytes, and the subscription only fails at
 * the delivery attempt.
 */
export function keyAsBytes(base64: string): Uint8Array<ArrayBuffer> {
  const filled = base64.padEnd(base64.length + (4 - base64.length % 4) % 4, '=')
  const raw = atob(filled.replace(/-/g, '+').replace(/_/g, '/'))

  /*
   * Via an explicit ArrayBuffer: Uint8Array.from returns
   * Uint8Array<ArrayBufferLike>, and that includes SharedArrayBuffer —
   * which applicationServerKey does not accept.
   */
  const bytes = new Uint8Array(new ArrayBuffer(raw.length))

  for (let position = 0; position < raw.length; position++) {
    bytes[position] = raw.charCodeAt(position)
  }

  return bytes
}
