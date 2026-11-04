/**
 * Runs `task` at most once at a time. Calls that arrive while it runs
 * collapse into a single run afterwards — the last change is never lost, but
 * a burst of fifty becomes two runs instead of fifty overlapping ones.
 */
export function coalesce(task: () => unknown): () => Promise<void> {
  let running: Promise<void> | null = null
  let again = false

  async function loop(): Promise<void> {
    do {
      again = false

      try {
        await task()
      }
      catch {
        // The task reports its own failures; the next call simply tries again.
      }
    } while (again)

    running = null
  }

  return () => {
    if (running) {
      again = true

      return running
    }

    running = loop()

    return running
  }
}
