/** One icon of the catalog: the glyph to store, its file name, its search words. */
export interface EmojiEntry {
  emoji: string
  hex: string
  words: string
}

/**
 * Every icon the pickers offer, with German and English search words
 * (`public/emoji/catalog.json`, built by `frontend/scripts/emoji`).
 *
 * Loaded on first use only and then kept: some 190 KB that the wall needs
 * only while someone picks an icon.
 */
export function useEmojiCatalog() {
  const icons = useState<EmojiEntry[]>('emoji-catalog', () => [])
  const failed = ref(false)

  async function load(): Promise<void> {
    if (icons.value.length > 0) {
      return
    }

    try {
      const data = await $fetch<{ icons: [string, string, string][] }>('/emoji/catalog.json', {
        baseURL: '/',
      })

      icons.value = data.icons.map(([emoji, hex, words]) => ({ emoji, hex, words }))
    }
    catch {
      failed.value = true
    }
  }

  return { icons, failed, load }
}
