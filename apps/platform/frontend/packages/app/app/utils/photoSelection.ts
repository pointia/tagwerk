/** What the phone's photo grid needs to know about a picture to select it. */
export interface Selectable {
  id: number
  can_delete: boolean
}

/** In or out of the selection; a photo the person may not change stays out. */
export function toggled(selected: number[], photo: Selectable): number[] {
  if (selected.includes(photo.id)) {
    return selected.filter(id => id !== photo.id)
  }

  return photo.can_delete ? [...selected, photo.id] : selected
}

/** "Alle": every loaded photo the person may change. */
export function everySelectable(photos: Selectable[]): number[] {
  return photos.filter(photo => photo.can_delete).map(photo => photo.id)
}

/** After a reload: keep only what is still there. */
export function stillThere(selected: number[], photos: Selectable[]): number[] {
  const present = new Set(photos.map(photo => photo.id))

  return selected.filter(id => present.has(id))
}
