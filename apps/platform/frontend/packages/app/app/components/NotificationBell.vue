<script setup lang="ts">
import { intlLocale } from '../../../shared/app/utils/intlLocale'

const { items, unread, loaded, markRead } = useNotifications()

const open = ref(false)

useEscape(() => {
  open.value = false
}, () => open.value)

// A phone's own zone: the account reads when it reached them, not the household's clock.
function when(iso: string): string {
  return new Date(iso).toLocaleString(intlLocale.value, { dateStyle: 'medium', timeStyle: 'short' })
}

function toggle(): void {
  open.value = !open.value

  if (open.value) {
    markRead()
  }
}
</script>

<template>
  <div class="relative">
    <button
      type="button"
      data-testid="notifications-open"
      :aria-label="unread > 0 ? $t('notifications.openUnread', { count: unread }) : $t('notifications.open')"
      aria-haspopup="dialog"
      :aria-expanded="open"
      class="relative flex size-11 shrink-0 items-center justify-center rounded-full text-ink-2 transition-colors hover:bg-surface-2"
      @click="toggle"
    >
      <TwIcon name="bell" :size="22" />
      <span
        v-if="unread > 0"
        data-testid="notifications-unread"
        class="absolute top-1 right-1 flex min-w-5 items-center justify-center rounded-full bg-accent px-1 text-xs font-bold text-accent-ink"
      >{{ unread > 9 ? '9+' : unread }}</span>
    </button>

    <div
      v-if="open"
      role="dialog"
      :aria-label="$t('notifications.title')"
      data-testid="notifications"
      class="absolute right-0 z-40 mt-2 max-h-[70vh] w-[min(22rem,calc(100vw-1.5rem))] overflow-y-auto rounded-2xl bg-surface p-2 shadow-floating"
    >
      <TwLoading v-if="!loaded" class="m-4" />
      <p v-else-if="items.length === 0" class="p-4 text-base text-ink-2">
        {{ $t('notifications.none') }}
      </p>
      <ul v-else>
        <li v-for="item in items" :key="item.id">
          <component
            :is="item.url ? 'a' : 'div'"
            :href="item.url ?? undefined"
            data-testid="notification"
            class="block min-h-11 rounded-xl px-3 py-2"
            :class="item.url ? 'hover:bg-surface-2' : ''"
          >
            <span class="block font-semibold">{{ item.title }}</span>
            <span v-if="item.body" class="block text-sm text-ink-2">{{ item.body }}</span>
            <span v-if="item.created_at" class="block text-xs text-ink-3">{{ when(item.created_at) }}</span>
          </component>
        </li>
      </ul>
    </div>
  </div>
</template>
