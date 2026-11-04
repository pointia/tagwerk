<script setup lang="ts">
/*
 * The face of a finished day. It stands still: a looping emoji trembled on
 * the wall tablet no matter how it was layered. It also stays — with done
 * tasks hidden the column is otherwise empty, and a column without anything
 * in it reads like an error, not a reward.
 */
const props = defineProps<{ face: string }>()
</script>

<template>
  <span aria-hidden="true" data-testid="celebration" class="tw-tada inline-block leading-none">{{ props.face }}</span>
</template>
