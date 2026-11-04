import type { IconName } from '../../../shared/app/components/TwIcon.vue'

/**
 * An area a household has switched on: a built-in one, or a plugin's as
 * `vendor/name`. Plugins make the list open, so it is a string.
 */
export type FeatureKey = string

export type Mode = string

/** A plugin may name an icon the set lacks; `TwIcon` then draws a puzzle piece. */
type AreaIcon = IconName

/**
 * One entry of the bar, as the server sends it.
 *
 * Names, order and icons live in the server's `Feature` enum — there is no
 * second list here to be forgotten with the next area.
 */
export interface AgendaArea {
  feature: FeatureKey
  mode: Mode
  label: string
  icon: AreaIcon
}

export interface Area {
  value: Mode
  feature: FeatureKey
  label: string
  icon: AreaIcon
}

/** The bar shows what the server sends — in its order. */
export function asAreas(entries: AgendaArea[]): Area[] {
  return entries.map(entry => ({
    value: entry.mode,
    feature: entry.feature,
    label: entry.label,
    icon: entry.icon,
  }))
}

/**
 * Which area applies after a change.
 *
 * If the one currently shown is switched off, the wall would otherwise stay
 * on a view that no longer exists — and whose API has answered with 404
 * since the switch.
 */
export function validMode(current: Mode, visible: Area[]): Mode {
  if (visible.some(entry => entry.value === current)) {
    return current
  }

  return visible[0]?.value ?? current
}
