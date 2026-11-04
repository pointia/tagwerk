<script setup lang="ts">
const props = defineProps<{ today: string }>()

// A person's row leads to the tasks, filtered to that person.
const emit = defineEmits<{ person: [number] }>()

const { occurrences, load } = useTasks()
const { agenda } = useAgenda()

onMounted(() => {
  if (occurrences.value.length === 0) {
    load(props.today, props.today)
  }
})

/** "2/5" per person — the number asked for at the wall. */
const snapshot = computed(() => (agenda.value?.people ?? [])
  .map((person) => {
    const own = occurrences.value.filter(entry => entry.date === props.today
      && entry.person_id === person.id)

    return {
      person,
      total: own.length,
      completed: own.filter(entry => entry.done).length,
    }
  })
  .filter(row => row.total > 0))
</script>

<template>
  <ul v-if="snapshot.length > 0" class="space-y-2 overflow-y-auto">
    <li v-for="row in snapshot" :key="row.person.id">
      <button
        type="button"
        data-testid="widget-tasks-person"
        class="relative flex min-h-11 w-full items-center gap-2.5 rounded-xl px-1 text-left transition-colors hover:bg-surface-2"
        @click="emit('person', row.person.id)"
      >
        <TwAvatar
        :name="row.person.name"
        :color="row.person.color"
        :avatar="row.person.avatar"
        :size="32"
        aria-hidden="true"
      />

      <span class="min-w-0 flex-1 truncate text-lg font-medium">{{ row.person.name }}</span>

      <!-- Spelled out for screen readers below; "0/5" would be read as a fraction. -->
      <span
        aria-hidden="true"
        class="shrink-0 text-lg font-semibold tabular-nums"
        :class="row.completed === row.total ? 'text-success' : 'text-ink-2'"
      >
        {{ row.completed }}/{{ row.total }}
      </span>
      <span class="sr-only">{{ $t('people.progress', { done: row.completed, total: row.total }) }}</span>
      </button>
    </li>
  </ul>

  <p v-else class="text-base text-ink-3">
    {{ $t('tasks.nothingOpenToday') }}
  </p>
</template>
