<script setup lang="ts">
import { codeFromUrl } from '~/utils/pairing'

const { t } = useI18n()

const emit = defineEmits<{ paired: [] }>()

const { choose } = useEntry()

/*
 * Whoever opens the address without a pairing is not necessarily a tablet:
 * a phone or a laptop lands here too. They choose first; a scanned QR code
 * already says it is a tablet and skips the question.
 */
const step = ref<'choose' | 'code'>('choose')
const code = ref('')
const busy = ref(false)
const error = ref<string | null>(null)

async function submit(): Promise<void> {
  if (code.value.trim() === '' || busy.value) {
    return
  }

  busy.value = true
  error.value = null

  try {
    await $fetch('/api/devices/pair', {
      method: 'POST',
      body: { code: code.value.trim() },
      credentials: 'same-origin',
    })

    emit('paired')
  }
  catch {
    // Deliberately without distinction: whether the code is wrong, expired or
    // already redeemed helps nobody at the device.
    error.value = t('pairing.rejected')
    code.value = ''
  }
  finally {
    busy.value = false
  }
}

onMounted(() => {
  const scanned = codeFromUrl(window.location.search)

  if (scanned === null) {
    return
  }

  step.value = 'code'
  code.value = scanned

  /*
   * Remove it from the address before pairing: a code is only valid once,
   * and a reload would otherwise try again with a spent one.
   */
  window.history.replaceState({}, '', window.location.pathname)

  void submit()
})
</script>

<template>
  <div class="flex h-full items-center justify-center overflow-y-auto bg-canvas p-4 sm:p-8">
    <div class="w-full max-w-xl rounded-[2rem] bg-surface p-6 text-center shadow-floating sm:p-12">
      <TwLogo class="mx-auto size-16" />

      <template v-if="step === 'choose'">
        <h1 class="mt-6 font-display text-4xl font-semibold">
          {{ $t('pairing.welcome') }}
        </h1>

        <p class="mt-3 text-xl text-ink-2">
          {{ $t('pairing.how') }}
        </p>

        <div class="mt-10 flex flex-col gap-4 text-left">
          <button
            type="button"
            class="flex min-h-16 items-center gap-4 rounded-2xl bg-accent px-6 py-4 text-accent-ink"
            @click="step = 'code'"
          >
            <TwIcon name="dashboard" :size="32" />
            <span>
              <span class="block text-2xl font-semibold">{{ $t('pairing.wall') }}</span>
              <span class="block text-lg">{{ $t('pairing.wallHint') }}</span>
            </span>
          </button>

          <button
            type="button"
            data-testid="entry-phone"
            class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-line bg-surface-2 px-6 py-4 text-ink"
            @click="choose('user')"
          >
            <TwIcon name="today" :size="32" />
            <span>
              <span class="block text-2xl font-semibold">{{ $t('pairing.phone') }}</span>
              <span class="block text-lg text-ink-2">{{ $t('pairing.phoneHint') }}</span>
            </span>
          </button>

          <a
            href="/admin"
            class="flex min-h-16 items-center gap-4 rounded-2xl border-2 border-line bg-surface-2 px-6 py-4 text-ink"
          >
            <TwIcon name="settings" :size="32" />
            <span>
              <span class="block text-2xl font-semibold">{{ $t('pairing.admin') }}</span>
              <span class="block text-lg text-ink-2">{{ $t('pairing.adminHint') }}</span>
            </span>
          </a>
        </div>
      </template>

      <template v-else>
        <h1 class="mt-6 font-display text-4xl font-semibold">
          {{ $t('pairing.setUp') }}
        </h1>

        <p class="mt-3 text-xl text-ink-2">
          {{ $t('pairing.scan') }}
        </p>

        <form class="mt-10" @submit.prevent="submit">
          <label for="pairing-code" class="sr-only">{{ $t('pairing.code') }}</label>

          <input
            id="pairing-code"
            v-model="code"
            autofocus
            inputmode="text"
            autocapitalize="characters"
            autocomplete="off"
            spellcheck="false"
            maxlength="12"
            placeholder="ABCD1234"
            class="w-full rounded-2xl border-2 border-line bg-surface-2 px-6 py-5 text-center font-mono text-4xl tracking-[0.3em] text-ink uppercase outline-none placeholder:text-ink-3/60 focus:border-accent"
          >

          <button
            type="submit"
            :disabled="busy || code.trim() === ''"
            class="mt-5 flex min-h-16 w-full items-center justify-center gap-3 rounded-2xl bg-accent px-6 text-2xl font-semibold text-accent-ink transition-opacity disabled:opacity-40"
          >
            <TwPending :busy="busy" />
            {{ $t('pairing.connect') }}
          </button>
        </form>

        <p v-if="error" role="alert" class="mt-6 text-xl text-danger">
          {{ error }}
        </p>

        <button
          type="button"
          class="mt-6 min-h-11 text-lg text-ink-2 underline"
          @click="step = 'choose'; error = null"
        >
          {{ $t('common.back') }}
        </button>
      </template>
    </div>
  </div>
</template>
