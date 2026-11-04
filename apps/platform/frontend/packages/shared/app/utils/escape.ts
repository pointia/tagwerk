/**
 * The open popups, newest last. Escape closes the one on top: a PIN prompt
 * over a form goes first, the form stays with what was typed.
 */
export class EscapeStack {
  private readonly entries: Array<() => void> = []

  push(close: () => void): void {
    this.entries.push(close)
  }

  remove(close: () => void): void {
    const index = this.entries.lastIndexOf(close)

    if (index !== -1) {
      this.entries.splice(index, 1)
    }
  }

  /** @returns whether a popup took the key */
  escape(): boolean {
    const top = this.entries.at(-1)

    top?.()

    return top !== undefined
  }
}
