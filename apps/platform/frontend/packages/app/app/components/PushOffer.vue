<script setup lang="ts">
/*
 * Asked once per phone, right after signing in: notifications on or not.
 * The browser's own prompt follows the tap on "Einschalten" — it shows only
 * for a user action. While it runs the button spins; afterwards the sheet
 * says how it went, and a sample notification arrives from the server.
 */
const { status, busy, accept, closeOffer } = usePush()

useEscape(closeOffer)

const done = computed(() => status.value?.ok === true)
</script>

<template>
  <div class="fixed inset-0 z-30 flex items-end bg-scrim">
    <div
      role="dialog"
      aria-modal="true"
      aria-labelledby="push-offer-title"
      data-testid="push-offer"
      class="w-full rounded-t-[2rem] bg-surface px-6 pt-6 pb-[calc(1.5rem+env(safe-area-inset-bottom))] text-center shadow-floating"
    >
      <span
        class="mx-auto flex size-16 items-center justify-center rounded-3xl"
        :class="done ? 'bg-success-soft text-success' : 'bg-accent-soft text-accent'"
        aria-hidden="true"
      >
        <TwIcon :name="done ? 'check' : 'bell'" :size="32" />
      </span>

      <h2 id="push-offer-title" class="mt-4 font-display text-2xl font-semibold">
        {{ done ? $t('push.offer.done') : $t('push.offer.title') }}
      </h2>

      <template v-if="done">
        <p role="status" data-testid="push-status" class="mt-2 text-base text-ink-2">
          {{ $t('push.offer.sample') }}
        </p>

        <button
          type="button"
          data-testid="push-offer-close"
          class="mt-6 min-h-14 w-full rounded-2xl bg-accent text-lg font-semibold text-accent-ink"
          @click="closeOffer"
        >
          {{ $t('push.offer.close') }}
        </button>
      </template>

      <template v-else>
        <p class="mt-2 text-base text-ink-2">
          {{ $t('push.hint') }}
        </p>
        <p class="mt-1 text-sm text-ink-3">
          {{ $t('push.offer.later') }}
        </p>

        <p v-if="status" role="alert" data-testid="push-status" class="mt-4 rounded-2xl bg-warning px-4 py-2.5 text-base font-medium text-warning-ink">
          {{ status.text }}
        </p>

        <button
          type="button"
          data-testid="push-offer-yes"
          :disabled="busy"
          :aria-busy="busy"
          class="mt-6 flex min-h-14 w-full items-center justify-center gap-3 rounded-2xl bg-accent text-lg font-semibold text-accent-ink disabled:opacity-80"
          @click="accept"
        >
          <TwSpinner v-if="busy" />
          {{ busy ? $t('push.switching') : $t('push.on') }}
        </button>
        <button
          type="button"
          data-testid="push-offer-no"
          :disabled="busy"
          class="mt-2 min-h-12 w-full rounded-2xl text-lg font-medium text-ink-2 hover:bg-surface-2 disabled:opacity-50"
          @click="closeOffer"
        >
          {{ $t('push.offer.no') }}
        </button>
      </template>
    </div>
  </div>
</template>
