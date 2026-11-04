/*
 * Builds the icons for tasks and rewards: Microsoft's Fluent Emoji (MIT) in
 * two styles, plus a catalog with German and English search words from CLDR
 * (via emojibase-data, MIT). Run once when the set changes; the output in
 * apps/platform/public/emoji is committed.
 *
 *   git clone --depth 1 --filter=blob:none --sparse https://github.com/microsoft/fluentui-emoji.git /tmp/fluent
 *   git -C /tmp/fluent sparse-checkout set --no-cone '/LICENSE' '/assets/*\/metadata.json' \
 *     '/assets/*\/Flat/*.svg' '/assets/*\/High Contrast/*.svg' \
 *     '/assets/*\/Default/Flat/*.svg' '/assets/*\/Default/High Contrast/*.svg'
 *   pnpm install --ignore-workspace && node build.mjs /tmp/fluent
 *
 * Its own package outside the workspace: the app needs neither dependency,
 * and adding them there reshuffled the app's lockfile.
 */
import { copyFileSync, existsSync, mkdirSync, readdirSync, readFileSync, rmSync, writeFileSync } from 'node:fs'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'
import { optimize } from 'svgo'

const fluent = process.argv[2]

if (!fluent || !existsSync(join(fluent, 'assets'))) {
  console.error('Usage: node build.mjs <fluentui-emoji checkout>')
  process.exit(1)
}

const here = dirname(fileURLToPath(import.meta.url))
const target = join(here, '../../../public/emoji')
const require = createRequire(import.meta.url)

/** The file name: code points without the variation selector, as the app computes it. */
const hexOf = codes => codes.toLowerCase().split(/[\s-]+/).filter(code => code !== 'fe0f').join('-')

/** Lower case, each word once — what the pickers search in. */
const words = list => [...new Set(list.join(' ').toLocaleLowerCase().split(/\s+/).filter(Boolean))].join(' ')

const cldr = {}

for (const language of ['de', 'en']) {
  for (const entry of require(`emojibase-data/${language}/data.json`)) {
    const known = cldr[hexOf(entry.hexcode)] ??= { emoji: entry.emoji, order: entry.order ?? Infinity, words: [] }
    known.words.push(entry.label, ...(entry.tags ?? []))
  }
}

/** Default skin tone where an emoji has several; otherwise the style folder itself. */
function svgIn(folder, style) {
  for (const base of [join(folder, 'Default', style), join(folder, style)]) {
    if (existsSync(base)) {
      const file = readdirSync(base).find(name => name.endsWith('.svg'))

      if (file) {
        return join(base, file)
      }
    }
  }

  return null
}

const small = svg => optimize(svg, { multipass: true }).data

rmSync(target, { recursive: true, force: true })
mkdirSync(join(target, 'flat'), { recursive: true })
mkdirSync(join(target, 'mono'), { recursive: true })

const icons = []

for (const name of readdirSync(join(fluent, 'assets'))) {
  const folder = join(fluent, 'assets', name)
  const meta = JSON.parse(readFileSync(join(folder, 'metadata.json'), 'utf8'))
  const flat = svgIn(folder, 'Flat')
  const mono = svgIn(folder, 'High Contrast')

  if (!meta.unicode || !flat || !mono) {
    continue
  }

  const hex = hexOf(meta.unicode)
  const known = cldr[hex]

  writeFileSync(join(target, 'flat', `${hex}.svg`), small(readFileSync(flat, 'utf8')))
  writeFileSync(join(target, 'mono', `${hex}.svg`), small(readFileSync(mono, 'utf8')))

  icons.push({
    emoji: known?.emoji ?? meta.glyph,
    hex,
    order: known?.order ?? Infinity,
    words: words([...(known?.words ?? []), meta.cldr ?? '', ...(meta.keywords ?? [])]),
  })
}

icons.sort((a, b) => a.order - b.order || a.hex.localeCompare(b.hex))

// Rows instead of objects: the pickers load this whole.
writeFileSync(join(target, 'catalog.json'), JSON.stringify({
  source: 'Fluent Emoji (Microsoft, MIT); search words: CLDR via emojibase-data (MIT)',
  icons: icons.map(icon => [icon.emoji, icon.hex, icon.words]),
}))

copyFileSync(join(fluent, 'LICENSE'), join(target, 'LICENSE-fluent-emoji'))

console.log(`${icons.length} icons written to ${target}`)
