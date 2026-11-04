<script setup lang="ts">
/* Something is on its way. Drawn in the current text colour. */
withDefaults(defineProps<{ size?: number }>(), { size: 20 })
</script>

<template>
  <span
    aria-hidden="true"
    class="tw-spinner inline-block shrink-0 animate-spin rounded-full border-2 border-current border-r-transparent"
    :style="{ width: `${size}px`, height: `${size}px` }"
  />
</template>
