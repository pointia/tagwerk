<script setup lang="ts">
const { agenda } = useAgenda()

const people = computed(() => agenda.value?.people ?? [])
</script>

<template>
  <div class="flex h-full flex-wrap content-start items-start gap-3 overflow-y-auto">
    <span v-for="person in people" :key="person.id" class="flex flex-col items-center gap-1">
      <TwAvatar :name="person.name" :color="person.color" :avatar="person.avatar" :size="44" />
      <span class="max-w-20 truncate text-sm font-medium">{{ person.name }}</span>
    </span>

    <p v-if="people.length === 0" class="text-base text-ink-3">
      {{ $t('people.none') }}
    </p>
  </div>
</template>
