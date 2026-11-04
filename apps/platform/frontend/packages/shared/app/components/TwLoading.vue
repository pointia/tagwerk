<script setup lang="ts">
/*
 * In place of a view whose first answer is still on its way. Without it an
 * empty view reads "nothing there" ("Nichts zu tun") until the data arrive.
 */
</script>

<template>
  <div
    role="status"
    data-testid="loading"
    class="flex flex-1 flex-col items-center justify-center gap-3 py-16 text-lg text-ink-3"
  >
    <TwSpinner :size="32" class="text-accent" />
    {{ $t('common.fetching') }}
  </div>
</template>
