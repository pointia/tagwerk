# A Tagwerk frontend

A starting point for your own interface to [Tagwerk](https://github.com/tagwerk-family/tagwerk)
— for a wall tablet, a phone, an e-ink display, whatever your family needs.
It is a plain Vite + TypeScript app on [`@tagwerk/client`](https://www.npmjs.com/package/@tagwerk/client):
pair a wall or sign in, then the coming week, reloaded when something changes.
Swap in Vue, React or Svelte as you like.

## Develop

```bash
npm install
TAGWERK_URL=https://tagwerk.test npm run dev    # your Tagwerk server
```

Vite passes `/api` and `/broadcasting` on to the server, so the sign-in
cookie belongs to the dev server's origin — exactly as it will when Tagwerk
serves your files. Pair with a code from Tagwerk's admin (Setup → Devices).

## Ship

```bash
npm run build      # writes dist/, which you commit
git tag v1.0.0 && git push --tags
```

Rename `acme/tagwerk-frontend` in `composer.json` and publish the repository
on Packagist (or name it in `TAGWERK_COMPOSER_REPOSITORIES`). An operator
then sets in Tagwerk's `deploy/.env`:

```
TAGWERK_FRONTEND=acme/tagwerk-frontend:^1.0
```

The container installs it at start and serves `dist/` at `/` instead of the
official app; `/api`, `/admin` and the rest stay Tagwerk's.

## The rules

- **`dist/index.html` plus `dist/assets/`.** Tagwerk serves `index.html` at
  `/` and everything else from `/assets/`. A `sw.js`, `manifest.webmanifest`,
  `icon.svg`, `icon-192.png`, `icon-512.png` beside `index.html` are served
  too, if you have them.
- **No inline scripts, nothing from other hosts.** The server's Content
  Security Policy allows script files of its own origin only; the start
  refuses a page that would stay blank. Fonts and pictures belong in
  `assets/` as well.
- **The server decides.** What someone may do arrives as `permissions` and
  per-item `can`; a wall's write may answer `pin` (ask for the parent PIN,
  then repeat). Hide what is not allowed — the server refuses it anyway.
- **Days are the household's** (`agenda.household.timezone`), never the
  device's.
- **The contract** is the route list in Tagwerk's
  `apps/platform/routes/api-contract.txt` and the guide `docs/frontends.md`.

MIT licensed — make it yours.
