<script setup lang="ts">
/*
 * Tagwerk's mark: a sun rising over two day bars, on a terracotta tile.
 *
 * Fixed colours instead of tokens — a mark does not change with the theme.
 * The same shape lives as icon.svg in wall and phone and as logo.svg in
 * Laravel (admin, favicon); when changing it, adjust all four places.
 */
</script>

<template>
  <svg viewBox="0 0 512 512" aria-hidden="true" focusable="false">
    <rect width="512" height="512" rx="112" fill="#c2410c" />
    <g transform="translate(0 6)">
      <path fill="#fdba74" d="M116 230a140 140 0 0 1 280 0z" />
      <rect x="102" y="258" width="308" height="62" rx="31" fill="#ffffff" />
      <rect x="148" y="348" width="216" height="62" rx="31" fill="#ffffff" />
    </g>
  </svg>
</template>
