/**
 * Whether movement stays still.
 *
 * Once the app has set the device's `animations` switch on `<html>`, that
 * switch alone decides: kiosk tablets often have the system's animations
 * turned off for speed, and that silently took the sparks of every tick.
 * Before the switch is known (sign-in, pairing) the system's choice counts.
 */
export function motionStill(setting: string | undefined, systemReduces: boolean): boolean {
  return setting === undefined ? systemReduces : setting === 'off'
}

export function isMotionStill(): boolean {
  return motionStill(
    document.documentElement.dataset.motion,
    window.matchMedia('(prefers-reduced-motion: reduce)').matches,
  )
}
