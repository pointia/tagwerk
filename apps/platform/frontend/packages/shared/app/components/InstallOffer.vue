<script setup lang="ts">
/*
 * A band, not a dialog: it may wait while someone looks something up.
 * Chrome and Edge get a button that opens the browser's own prompt; iOS has
 * none, so the band says where Safari hides it.
 */
const { way, install, later } = useInstallOffer()
</script>

<template>
  <div
    role="region"
    :aria-label="$t('install.title')"
    data-testid="install-offer"
    class="fixed inset-x-3 bottom-[calc(env(safe-area-inset-bottom)+0.75rem)] z-20 mx-auto flex max-w-2xl flex-wrap items-center gap-3 rounded-3xl bg-surface p-4 shadow-floating sm:flex-nowrap"
  >
    <TwLogo class="size-11 shrink-0" />

    <p class="min-w-0 flex-1">
      <span class="block text-lg font-semibold">{{ $t('install.title') }}</span>
      <span class="block text-sm text-ink-2">
        {{ way === 'ios' ? $t('install.ios') : $t('install.hint') }}
      </span>
    </p>

    <div class="flex w-full gap-2 sm:w-auto">
      <button
        type="button"
        data-testid="install-later"
        class="min-h-12 flex-1 rounded-2xl px-4 text-base font-semibold text-ink-2 transition-colors hover:bg-surface-2 sm:flex-none"
        @click="later"
      >
        {{ $t('install.later') }}
      </button>
      <button
        v-if="way === 'prompt'"
        type="button"
        data-testid="install-now"
        class="min-h-12 flex-1 rounded-2xl bg-accent px-5 text-base font-semibold text-accent-ink sm:flex-none"
        @click="install"
      >
        {{ $t('install.now') }}
      </button>
    </div>
  </div>
</template>
