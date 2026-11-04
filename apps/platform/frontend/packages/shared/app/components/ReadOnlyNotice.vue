<script setup lang="ts">
const { refusal, dismiss } = useReadOnly()

useEscape(dismiss, () => refusal.value !== null)
</script>

<template>
  <div
    v-if="refusal !== null"
    class="fixed inset-0 z-50 flex items-center justify-center bg-scrim p-6"
    @click.self="dismiss"
  >
    <div
      role="alertdialog"
      aria-modal="true"
      aria-labelledby="read-only-title"
      data-testid="read-only"
      class="w-full max-w-sm rounded-3xl bg-surface p-7 shadow-floating"
    >
      <h2 id="read-only-title" class="font-display text-2xl font-semibold">
        {{ $t('readOnly.title') }}
      </h2>

      <p class="mt-2 text-base text-ink-2">
        {{ refusal.message || $t('readOnly.hint') }}
      </p>

      <a
        v-if="refusal.url"
        :href="refusal.url"
        data-testid="read-only-action"
        class="mt-6 flex min-h-11 w-full items-center justify-center rounded-2xl bg-accent px-5 text-lg font-bold text-accent-ink"
      >
        {{ refusal.action || $t('notice.more') }}
      </a>

      <button
        type="button"
        :class="refusal.url ? 'mt-3 bg-surface-2 text-ink' : 'mt-6 bg-accent text-accent-ink'"
        class="min-h-11 w-full rounded-2xl px-5 text-lg font-bold"
        @click="dismiss"
      >
        {{ $t('readOnly.ok') }}
      </button>
    </div>
  </div>
</template>
