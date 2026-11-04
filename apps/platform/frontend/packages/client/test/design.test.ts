import { describe, expect, it } from 'vitest'
import { storedDesign } from '../src/rules/design'

describe('storedDesign', () => {
  const design = { key: 'acme/nordlicht', name: 'Nordlicht', url: '/api/design/acme/nordlicht/0123456789ab.css' }

  it('reads back what was stored', () => {
    expect(storedDesign(JSON.stringify(design))).toEqual(design)
  })

  it('refuses a stylesheet from elsewhere', () => {
    expect(storedDesign(JSON.stringify({ ...design, url: 'https://evil.example/x.css' }))).toBeNull()
    expect(storedDesign(JSON.stringify({ ...design, url: '/api/design/acme/nordlicht/../../x.css' }))).toBeNull()
  })

  it('treats nothing and rubbish as no design', () => {
    expect(storedDesign(null)).toBeNull()
    expect(storedDesign('{')).toBeNull()
    expect(storedDesign('"text"')).toBeNull()
    expect(storedDesign(JSON.stringify({ key: 'Acme', name: 'x', url: design.url }))).toBeNull()
  })
})
