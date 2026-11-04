import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import type { RequestFn } from './http.ts'
import { gatherAnnouncements } from './rules/announcements.ts'

/** What `GET /api/realtime/config` answers. */
export interface RealtimeConfig {
  enabled: boolean
  key: string
  host: string | null
  port: number
  scheme: string
  channel: string
}

/** The response of /broadcasting/auth, as Pusher expects it. */
interface ChannelAuth {
  auth: string
  channel_data?: string
  shared_secret?: string
}

export interface Realtime {
  disconnect: () => void
}

declare global {
  interface Window {
    Pusher: typeof Pusher
  }
}

/**
 * Announcements of what changed, so a frontend does not wait for its next
 * poll. They carry no data, only the area (`tasks`, `lists`, `notifications`,
 * … or `all`); load it the way you load it anyway.
 *
 * Bursts are gathered: while one `onChange` runs, everything announced
 * meanwhile becomes one more call with all those areas, and while this
 * device writes (`holdAnnouncements()`) they wait until it has been quiet.
 * When the socket comes back after being lost, `all` is announced — what was
 * said meanwhile is gone. Without Reverb on the server nothing connects and
 * polling is all there is; that is not an error.
 */
export async function connectRealtime(request: RequestFn, onChange: (areas: string[]) => unknown): Promise<Realtime> {
  const announce = gatherAnnouncements(onChange)
  const settings = await request<RealtimeConfig>('/api/realtime/config').catch(() => null)

  if (!settings?.enabled || !settings.host) {
    return { disconnect: () => undefined }
  }

  // laravel-echo expects Pusher in the global scope.
  window.Pusher = Pusher

  const echo = new Echo({
    broadcaster: 'reverb',
    key: settings.key,
    wsHost: settings.host,
    wsPort: settings.port,
    wssPort: settings.port,
    forceTLS: settings.scheme === 'https',
    enabledTransports: ['ws', 'wss'],

    // Echo's own authorizer reaches for axios; the cookie goes along by itself.
    authorizer: (channel: { name: string }) => ({
      authorize: (socketId: string, callback: (error: Error | null, data: ChannelAuth | null) => void) => {
        request<ChannelAuth>('/broadcasting/auth', {
          method: 'POST',
          body: { socket_id: socketId, channel_name: channel.name },
        })
          .then(data => callback(null, data))
          .catch((error: Error) => callback(error, null))
      },
    }),
  })

  echo.private(settings.channel)
    .listen('.updated', (event: { area?: string }) => announce(event.area ?? 'all'))

  let lost = false

  echo.connector.onConnectionChange((status) => {
    if (status === 'connected') {
      if (lost) {
        announce('all')
      }

      lost = false
    }
    else if (status !== 'connecting') {
      lost = true
    }
  })

  return { disconnect: () => echo.disconnect() }
}
