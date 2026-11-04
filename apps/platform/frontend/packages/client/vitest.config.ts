import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    environment: 'node',
    include: ['test/**/*.test.ts'],

    // As on the wall: UTC keeps the tests honest, because the timestamps from
    // the API carry their own offset.
    env: { TZ: 'UTC' },
  },
})
