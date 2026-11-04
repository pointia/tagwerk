<script setup lang="ts">
const { rewards, load } = usePoints()

onMounted(() => {
  if (rewards.value.length === 0) {
    load()
  }
})
</script>

<template>
  <ul v-if="rewards.length > 0" class="space-y-2 overflow-y-auto">
    <li v-for="reward in rewards" :key="reward.id" class="flex items-center gap-2.5">
      <span v-if="reward.icon" aria-hidden="true" class="text-2xl"><TwEmoji :icon="reward.icon" /></span>

      <span class="min-w-0 flex-1 truncate text-lg font-medium">{{ reward.name }}</span>

      <span class="flex shrink-0 items-center gap-1 text-lg font-semibold tabular-nums text-ink-2">
        {{ reward.cost }} <TwStar />
      </span>
    </li>
  </ul>

  <p v-else class="text-base text-ink-3">
    {{ $t('points.noRewards') }}
  </p>
</template>
