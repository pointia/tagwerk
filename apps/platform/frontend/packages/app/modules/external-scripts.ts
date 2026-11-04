import { createHash } from 'node:crypto'
import { mkdir, writeFile } from 'node:fs/promises'
import { join } from 'node:path'
import { defineNuxtModule } from 'nuxt/kit'

/**
 * Moves the inline scripts of the generated pages into files of their own.
 *
 * The Content Security Policy allows scripts from this origin only
 * (`script-src 'self'`, see App\Security\ContentSecurityPolicy). Nuxt writes
 * its runtime config and the layer's theme line inline, and the config
 * carries the build id — hashes of them would change with every build and
 * the server's header with them. As files under the build directory they
 * are covered by 'self' and cached like every other build artefact. A
 * blocking `<script src>` runs in the same order as the inline one did.
 */
export default defineNuxtModule({
  meta: { name: 'external-scripts' },

  setup(_options, nuxt) {
    const dir = nuxt.options.app.buildAssetsDir

    nuxt.hook('nitro:init', (nitro) => {
      nitro.hooks.hook('prerender:generate', async (route) => {
        if (typeof route.contents !== 'string' || !route.fileName?.endsWith('.html')) {
          return
        }

        const files: Array<{ name: string, body: string }> = []

        const html = route.contents.replace(/<script>([\s\S]*?)<\/script>/g, (_match, body: string) => {
          const name = `inline-${createHash('sha256').update(body).digest('hex').slice(0, 12)}.js`
          files.push({ name, body })

          return `<script src="${dir}${name}"></script>`
        })

        const left = [...html.matchAll(/<script(\s[^>]*)?>/g)]
          .map(match => match[1] ?? '')
          .filter(attributes => !/\ssrc=/.test(attributes) && !/type="application\/json"/.test(attributes))

        if (left.length > 0) {
          throw new Error(`${route.fileName} still carries inline scripts the policy would block: ${left.join(', ')}`)
        }

        const target = join(nitro.options.output.publicDir, dir)
        await mkdir(target, { recursive: true })
        await Promise.all(files.map(file => writeFile(join(target, file.name), file.body)))

        route.contents = html
      })
    })
  },
})
