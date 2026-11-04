<script setup lang="ts">
const { people, load } = usePoints()

// Most stars first; Array.sort is stable, so ties keep the family order
const ranked = computed(() => [...people.value].sort((a, b) => b.balance - a.balance))

onMounted(() => {
  if (people.value.length === 0) {
    load()
  }
})
</script>

<template>
  <ul v-if="ranked.length > 0" class="space-y-2 overflow-y-auto">
    <li v-for="person in ranked" :key="person.id" class="flex items-center gap-2.5">
      <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="32" />

      <span class="min-w-0 flex-1 truncate text-lg font-medium">{{ person.name }}</span>

      <span class="flex shrink-0 items-center gap-1 text-xl font-semibold tabular-nums">
        {{ person.balance }} <TwStar />
      </span>
    </li>
  </ul>

  <p v-else class="text-base text-ink-3">
    {{ $t('points.noChildren') }}
  </p>
</template>
