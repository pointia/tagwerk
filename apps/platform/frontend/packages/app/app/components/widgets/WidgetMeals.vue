<script setup lang="ts">
const props = defineProps<{ today: string }>()

const { meals, load } = useMeals()

onMounted(() => {
  if (meals.value.length === 0) {
    load(props.today, props.today)
  }
})

const today = computed(() => meals.value.filter(meal => meal.date === props.today))
</script>

<template>
  <ul v-if="today.length > 0" class="space-y-2 overflow-y-auto">
    <li v-for="meal in today" :key="meal.id">
      <span class="block text-sm font-bold text-ink-3">{{ meal.kind_label }}</span>
      <span class="block truncate text-lg font-medium">{{ meal.title }}</span>
    </li>
  </ul>

  <p v-else class="text-base text-ink-3">
    {{ $t('meals.nothingToday') }}
  </p>
</template>
