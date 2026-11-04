import { defineConfig } from 'vitest/config'

export default defineConfig({
  test: {
    // Pure logic in utils, no DOM needed.
    environment: 'node',
    include: ['test/**/*.test.ts'],

    /*
     * Deliberately UTC and NOT Europe/Vienna: the wall computes in the
     * household's time zone, which the API tells it. If the household's time
     * zone were set here, the tests would also pass when the code silently
     * uses the device's time zone — exactly that bug initially went
     * unnoticed this way.
     */
    env: { TZ: 'UTC' },
  },
})
