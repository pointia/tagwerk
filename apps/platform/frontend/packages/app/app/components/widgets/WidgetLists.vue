<script setup lang="ts">
const { lists, load } = useLists()

onMounted(() => {
  if (lists.value.length === 0) {
    load()
  }
})

/*
 * The list with the most open items: without settings that is the list
 * someone looks at the dashboard for.
 */
const list = computed(() => [...lists.value]
  .sort((a, b) => b.open_count - a.open_count)[0] ?? null)

const openCount = computed(() => (list.value?.items ?? []).filter(item => !item.done).slice(0, 8))
</script>

<template>
  <div v-if="list" class="flex h-full min-h-0 flex-col">
    <p class="mb-1.5 truncate text-sm font-bold text-ink-3">
      {{ list.name }}
    </p>

    <ul class="min-h-0 flex-1 space-y-1 overflow-y-auto">
      <li v-for="item in openCount" :key="item.id" class="truncate text-lg font-medium">
        {{ item.title }}<span v-if="item.quantity" class="text-ink-3"> · {{ item.quantity }}</span>
      </li>
    </ul>

    <p v-if="openCount.length === 0" class="text-base text-ink-3">
      {{ $t('lists.allChecked') }}
    </p>
  </div>

  <p v-else class="text-base text-ink-3">
    {{ $t('lists.noneYet') }}
  </p>
</template>
