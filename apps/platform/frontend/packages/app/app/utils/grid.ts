/** Columns of the dashboard grid. The view computes the row height. */
export const COLUMNS = 12

/** This many rows are searched when looking for a free cell. */
const MAX_ROWS = 40

export interface Placement {
  x: number
  y: number
  w: number
  h: number
}

export interface Tile extends Placement {
  id: number
  type: string
  settings: Record<string, unknown>
}

export function clamp(value: number, min: number, max: number): number {
  return Math.min(max, Math.max(min, value))
}

/** Two areas overlap when they intersect in both directions. */
export function overlaps(a: Placement, b: Placement): boolean {
  return a.x < b.x + b.w && a.x + a.w > b.x && a.y < b.y + b.h && a.y + a.h > b.y
}

/**
 * Does the placement fit into the grid without touching another tile?
 *
 * Overlaps are prevented instead of resolved: a grid that rearranges itself
 * on drop surprises everyone watching.
 */
export function fits(placement: Placement, tiles: Tile[], exceptId?: number): boolean {
  if (placement.x < 0 || placement.y < 0 || placement.x + placement.w > COLUMNS) {
    return false
  }

  return !tiles.some(tile => tile.id !== exceptId && overlaps(placement, tile))
}

/** The first free cell from the top left — null when nothing fits anymore. */
export function freeCell(tiles: Tile[], w: number, h: number): Placement | null {
  for (let y = 0; y < MAX_ROWS; y++) {
    for (let x = 0; x + w <= COLUMNS; x++) {
      const placement = { x, y, w, h }

      if (fits(placement, tiles)) {
        return placement
      }
    }
  }

  return null
}

/**
 * An offset in pixels becomes an offset in grid cells.
 *
 * Rounded, not truncated: whoever drags a tile by half a column means the
 * next one — with truncation it would stay put until the full column and
 * feel sluggish.
 */
export function cellOffset(pixel: number, cellWidth: number): number {
  return cellWidth <= 0 ? 0 : Math.round(pixel / cellWidth)
}

/** The placement after a drag, kept within the grid. */
export function shifted(placement: Placement, dx: number, dy: number): Placement {
  return {
    ...placement,
    x: clamp(placement.x + dx, 0, COLUMNS - placement.w),
    y: Math.max(0, placement.y + dy),
  }
}

/** The placement after dragging the handle; at least one cell in size. */
export function resized(placement: Placement, dw: number, dh: number): Placement {
  return {
    ...placement,
    w: clamp(placement.w + dw, 1, COLUMNS - placement.x),
    h: Math.max(1, placement.h + dh),
  }
}

/** How many rows the grid needs so everything fits. */
export function rows(tiles: Tile[], atLeast = 6): number {
  return Math.max(atLeast, ...tiles.map(tile => tile.y + tile.h))
}

/** Below phone width the tiles stack in reading order: row by row, left to right. */
export function stacked<T extends Placement>(tiles: T[]): T[] {
  return [...tiles].sort((a, b) => a.y - b.y || a.x - b.x)
}
