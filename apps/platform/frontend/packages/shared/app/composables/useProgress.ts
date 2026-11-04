export interface Progress {
  person_id: number
  done: number
  total: number
}

/**
 * How far each person is in the displayed range.
 *
 * The reference is deliberately the calendar view's range and not "today":
 * whoever pages to the week wants to know how the week stands. Only what is
 * assigned to someone counts.
 */
export function useProgress() {
  const snapshot = useState<Progress[]>('task-progress', () => [])

  async function load(from: string, until: string): Promise<void> {
    try {
      const data = await $fetch<{ progress: Progress[] }>('/api/wall/tasks/progress', {
        params: { from: from, to: until },
        baseURL: '/', credentials: 'same-origin',
      })

      snapshot.value = data.progress
    }
    catch {
      snapshot.value = []
    }
  }

  function forPerson(personId: number): Progress | null {
    return snapshot.value.find(entry => entry.person_id === personId) ?? null
  }

  return { snapshot, load, forPerson }
}
