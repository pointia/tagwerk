<script setup lang="ts">
/* The drawn part of a switch; the surrounding button carries role and state. */
defineProps<{ on: boolean }>()
</script>

<template>
  <span
    aria-hidden="true"
    class="relative h-7 w-12 shrink-0 rounded-full transition-colors"
    :class="on ? 'bg-accent' : 'bg-surface-3'"
  >
    <span class="absolute top-1 size-5 rounded-full bg-surface shadow transition-all" :class="on ? 'left-6' : 'left-1'" />
  </span>
</template>
