<script setup lang="ts">
/*
 * A person's face — the same everywhere: calendar, tasks, stars.
 *
 * Without a picture the initial stays in the person's colour. Both are
 * needed: a freshly created child has no photo yet but should still be
 * recognisable.
 */
const props = withDefaults(defineProps<{
  name: string
  color?: string | null
  avatar?: string | null
  size?: number
}>(), { color: null, avatar: null, size: 36 })

const color = computed(() => props.color ?? '#8a8276')

const initial = computed(() => props.name.trim().slice(0, 1).toUpperCase())
</script>

<template>
  <span
    class="inline-flex shrink-0 items-center justify-center overflow-hidden rounded-full font-display font-semibold text-white select-none"
    :style="{
      width: `${size}px`,
      height: `${size}px`,
      fontSize: `${Math.round(size * 0.42)}px`,
      // With a picture the colour stays as a ring: otherwise the association
      // would be lost that the calendar ties to the colours.
      backgroundColor: color,
      boxShadow: avatar ? `inset 0 0 0 2px ${color}` : undefined,
    }"
    :title="name"
  >
    <img v-if="avatar" :src="avatar" :alt="name" class="size-full object-cover" loading="lazy">
    <template v-else>{{ initial }}</template>
  </span>
</template>
