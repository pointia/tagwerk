<script setup lang="ts" generic="T extends string | null">
/*
 * One out of a few, as a row of chips. Test id and cell stay on every chip,
 * so the e2e tests find a choice by its value.
 */
const props = defineProps<{
  options: { value: T, label: string }[]
  modelValue: T
  labelledBy: string
  testId: string
  cell?: string
}>()

const emit = defineEmits<{ 'update:modelValue': [T] }>()
</script>

<template>
  <div role="group" :aria-labelledby="props.labelledBy" class="flex flex-wrap gap-1.5">
    <button
      v-for="option in props.options"
      :key="option.value ?? ''"
      type="button"
      :data-testid="props.testId"
      :data-cell="props.cell"
      :data-value="option.value ?? ''"
      :aria-pressed="props.modelValue === option.value"
      class="min-h-11 rounded-xl px-4 text-base font-semibold transition-colors"
      :class="props.modelValue === option.value ? 'bg-accent-soft text-accent' : 'bg-surface-2 text-ink-2'"
      @click="emit('update:modelValue', option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>
