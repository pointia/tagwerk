import type { Component } from 'vue'

/*
 * The views of plugin areas, keyed by area (`vendor/name`).
 *
 * A plugin's layer registers its view from a Nuxt plugin; the shell draws it
 * when the server lists the area. Module state, not `useState`: components
 * are not serialisable, and the app has no server rendering to hand them
 * over anyway.
 */
const views = shallowReactive(new Map<string, Component>())

export function registerAreaView(area: string, view: Component): void {
  views.set(area, view)
}

export function useAreaViews() {
  return {
    view: (area: string): Component | null => views.get(area) ?? null,
  }
}
