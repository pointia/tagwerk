# Language packs — design

## Intent

Tagwerk speaks German and English. Families elsewhere should be able to add
their language without waiting for the core: a Composer package that brings
French (or Croatian, or Turkish) and nothing else — offered through the
catalog like a provider, installed by the operator, chosen per household,
account and tablet like `de` and `en` today.

A language pack is data, not code that changes behaviour. It depends on
`tagwerk/plugin-api` only and may therefore be MIT, which keeps the barrier
for translators low.

## Decisions already made (with licensor)

- **English is the last fallback.** A pack that lags behind a release shows
  the missing texts in English. Refusing an incomplete pack would take a
  household's language away on every update; showing raw keys is worse than
  English. The gap is made visible to the operator instead (Operations →
  Plugins), never hidden.
- **No flash of another language.** Someone who chose French sees French
  from the first text on — on the wall after a power cut, on the phone, on
  the sign-in and pairing screens. Not German first, not English first, not
  a mix during a switch.
- **Frontend texts come from the server at runtime**, not from a Nuxt layer.
  `plugin-layers.ts` only takes layers from path packages under `plugins/`;
  a pack from Packagist has no sources at build time. Built-in `de` and `en`
  stay bundled.

## Goals

- `composer require acme/tagwerk-fr` + restart → “Français” appears in every
  language select (household, managed household, device, profile, phone
  settings), and choosing it shows wall, phone, admin, mails and push in
  French.
- A pack can be written and tested without a Tagwerk checkout:
  `PluginAssertions` checks completeness, placeholders and plural forms.
- No `=== 'de'` left in the core: formats come from `Intl`/Carbon via the
  pack's BCP 47 tag.

## Anti-goals

- **Packs never replace `de` or `en`.** A package offering a built-in code is
  skipped at boot. Corrections to the built-in languages go into the core.
- **No partial override of single keys** (“I prefer ‘Erledigt’ over
  ‘Fertig’”). That is a different feature with different risks.
- **No translations of stored content.** Starter tasks, demo data and Google
  events stay in the language they were written in (unchanged rule).
- **No translation of plugin areas by the pack.** A pack covers the core's
  keys. A plugin area brings its own languages (see “Plugins with own
  texts”).
- No UI to edit translations in the admin.
- No right-to-left languages (see “Settled during review”).

## The contract

`Tagwerk\PluginApi\Languages\LanguagePack`:

```php
interface LanguagePack
{
    /** ISO 639-1, lower case, never 'de' or 'en': 'fr' */
    public function code(): string;

    /** The language's name in itself — someone who cannot read the current one still finds theirs: 'Français' */
    public function name(): string;

    /** BCP 47 for Intl, Carbon and Yasumi: 'fr-FR' */
    public function intlTag(): string;

    /** Directory in Laravel's layout: fr.json, fr/validation.php, fr/auth.php */
    public function backendPath(): string;

    /** Path to the vue-i18n file with nested keys, same shape as shared/i18n/locales/en.json */
    public function frontendMessages(): string;
}
```

Offered as:

```php
$catalog->offer('languages', 'acme/french', FrenchPack::class);
```

- `languages` joins `ProviderContracts::BASE` with `LanguagePack` as its
  contract. Wrong class, invalid code (`/^[a-z]{2}$/`), a built-in code or a
  code a second package already offers → `LoadReport::fail()`, the package
  is skipped (existing rule for duplicate identifiers).
- `intlTag()` is validated with `\Locale::canonicalize()` and must start with
  `code()`. Invalid → the package fails.
- `name()` is a literal, not `__()` — the name of a language does not
  change with the reader's language.
- Methods do no I/O; the constructor contract test applies as for providers.
- `tagwerk:make-plugin acme/tagwerk-fr --language=fr` generates a pack: the
  class, `lang/fr.json`, `lang/fr/{auth,validation}.php` and
  `frontend/fr.json` as copies of the English sources (keys = English text
  for the backend, so a translator starts from readable text), and a test
  calling `assertLanguagePackComplete()`. The generated pack's `composer.json`
  names MIT.

## Backend

### Which languages exist

`Locale::SUPPORTED` becomes `Locale::supported(): array<string, string>` —
`de`, `en`, then the catalog's packs ordered by code. Every current reader
(`HouseholdForm`, `ListManagedHouseholds`, `DeviceForm`, `EditProfile`,
`AuthController` validation and `locales` answer) switches to it.
`Locale::intlTag(string $code)` answers `de-AT`, `en-US` or the pack's tag.

A stored code whose pack is gone counts as not set: `Locale::for()` already
skips unsupported codes and walks on to the household and the server
default. Nothing is rewritten in the database — reinstalling the pack brings
the choice back (same idea as areas).

### Loading the texts

`PluginServiceProvider` registers every pack's `backendPath()` in
`register()` via `$this->app['translation.loader']->addPath()` (unnamespaced,
so `__('Save')` and `validation.required` find `fr`). In `boot()` it would
be too late — the existing rule for `loadJsonTranslationsFrom`.

`config('app.fallback_locale')` stays `en`; Laravel then answers a missing
key with the English text (the key itself for JSON strings, `lang/en` files
for PHP groups — Laravel ships English validation messages).

Filament ships its own translations for many languages; a code it knows is
used as is, others fall back to English inside Filament.

### Formats instead of `=== 'de'`

| Today | Then |
|---|---|
| `AdminPanelProvider:116-117` `'d.m.Y'` / `'M j, Y'` | Carbon `isoFormat('L')` / `'L LT'` with `Locale::intlTag()` |
| `RecurrenceRule:240` `'D. MMMM'` / `'MMMM D'` | `isoFormat('LL')` without year: Carbon's `MMMM D`-style per locale via `getIsoFormats()` |
| `TaskForm:191` suffix `'.'` for German | ordinal from `NumberFormatter::ORDINAL` |
| `TaskForm:408` `'dd D. MMM'` | Carbon's per-locale short format |
| `YasumiHolidays::LOCALES` | `str_replace('-', '_', Locale::intlTag())`; Yasumi falls back to English names where it lacks one |

German and English output must stay byte-identical where tests or users rely
on it; where Carbon's German differs from today's (`D. MMMM`), German keeps
an explicit format and every other language takes Carbon's.

### Languages a pack cannot reach

- **`Household::defaultStaples()`** and other per-language defaults: an
  unknown code takes the English defaults. A pack may add them later through
  an optional interface (`ProvidesDefaults`); not part of this spec.
- **Emoji search words** (`catalog.json`, built by `scripts/emoji/build.mjs`
  for `de`, `en`): an unknown language searches the English words. Extending
  the build to every Emojibase language is a separate, pack-free change.

## Frontend: no flash

### Why there is a flash today

The app is `ssr: false`; `index.html` holds no text. A text appears only once
Vue renders, and today it renders with the browser language before the
server's answer (agenda for the wall, `/api/auth/me` for the phone), then
switches. With `de`/`en` this is rare and short. With a pack there would be
three more gaps: language known but texts not loaded, a mix during a switch,
and missing keys.

### Rule: language and texts before the first text

1. **`GET /api/language`** — public, behind `device.cookie` and
   `user.cookie`, authentication optional (`Auth::guard('sanctum')->user()`,
   no `auth:sanctum` middleware: the pairing and sign-in screens need an
   answer too). It resolves:
   - a device or account → `Locale::for($actor)` (the existing chain);
   - nobody → the best match of `Accept-Language` among
     `Locale::supported()`, else `config('app.locale')`.

   Answer:

   ```json
   { "code": "fr", "intl": "fr-FR", "messages": "/api/language/fr/3f9a1c.json" }
   ```

   `messages` is `null` for `de` and `en` (bundled). Throttled like the
   other public routes. A `Device` is not `Authenticatable` — no
   `getAuthIdentifier()` on this path (limiter keyed by IP).

2. **`GET /api/language/{code}/{hash}.json`** — the pack's
   `frontendMessages()` as JSON, `hash` = content hash (first 12 hex of
   sha256), `Cache-Control: public, max-age=31536000, immutable`. A wrong
   hash → 404, so an old tablet asks `/api/language` again. Served from a
   cache entry keyed by package version, not read from disk per request.

3. **A Nuxt plugin in the layer (`language.client.ts`) holds the mount.**
   It runs before `app.vue` mounts, in parallel with `useEntry().decide()`:
   - fetch `/api/language`;
   - if `messages` is set, fetch it and `setLocaleMessage(code, …)`;
   - `setLocale(code)`, set `intlLocale` to `intl`;
   - only then let the app mount. Until then `<body>` shows only the
     background (theme from `theme.js`), no text and no spinner label.

4. **Offline and kiosk restart:** the last answer and its messages are kept
   - by the service worker (`/api/language` network-first,
     `/api/language/*/*.json` cache-first — the hash makes that safe), and
   - as a convenience copy in `localStorage` (`tagwerk-language`: code,
     intl, hash, messages), like `tagwerk-entry`. Read in try/catch; without
     it the server decides.

   With neither network nor copy the app falls back to the browser language
   among the bundled ones — the only remaining path to a non-chosen
   language, and only on a device that never had an answer.

5. **Switching is one step.** `useLanguage().apply(code)` — called from the
   agenda, `/api/auth/me` and the settings — loads the messages first
   (through `/api/language` with the new code, or the cached file), then
   calls `setLocale`. vue-i18n never renders with a locale whose messages
   are missing. Agenda and `/api/auth/me` deliver `language` in the same
   shape as `/api/language` (code, intl, messages) instead of a bare
   `locale`, so a switch after a household change needs no extra round
   trip.

6. **`fallbackLocale: 'en'`** in the i18n config, so a missing key in a pack
   shows the English text. Missing keys are not logged in the browser
   (`missingWarn: false`, `fallbackWarn: false` for pack codes) — the
   operator sees them on the server instead.

`useEntry` and the language plugin are independent: the entry decides the
shell, the language its texts; both finish before the first render.

### Plugins with own texts

A plugin area with a Nuxt layer keeps its own `i18n/locales/{de,en}.json`.
It may add `fr.json`; `/api/language/{code}/{hash}.json` merges every
installed plugin's file for that code under its own keys (hash over the
merged result). An area without `fr` shows English there — the same rule.
Backend texts of a plugin load through its own `loadJsonTranslationsFrom`,
which already covers any code.

## Completeness: visible, not enforced

- **The reference is `en`**: backend keys of `lang/de.json` (the English text
  is the key, so `de.json` lists every key), PHP groups `auth`, `validation`
  as in `lang/de/`, frontend keys of `shared/i18n/locales/en.json`.
- **`App\Plugins\LanguageCoverage`** compares a pack against them: missing
  keys, extra keys (ignored at runtime), placeholders that differ
  (`:name`, `{count}`), plural forms that differ. Cached per package
  version and core version.
- **Operations → Plugins** shows, for a language pack, “Français — 97 %,
  23 texts missing” with the list in a modal. A placeholder mismatch counts
  as missing: that text falls back to English at runtime, because a broken
  placeholder would show `{count}` to a child. (Frontend: the server drops
  such keys from the served JSON; backend: the pack's path is registered
  through `PackTranslationLoader`, a decorator of Laravel's `FileLoader`
  that leaves those keys out, so the translator falls back to English.)
- **`extra.tagwerk.language.complete-for`** in the pack's `composer.json`
  names the Tagwerk version it was complete for; the Plugins page shows it
  next to the running version.

## Testkit

`PluginAssertions::assertLanguagePackComplete(LanguagePack $pack)`:

- the reference files travel with `tagwerk/plugin-api`
  (`resources/reference/{backend.json,frontend.json,groups/*.php}`, English
  only, regenerated by a core command at release and checked by a core test
  so they never drift);
- checks keys, placeholders, plural forms, no `@`/`$`/`|` outside plural
  separators (vue-i18n rules from AGENTS.md), valid `intlTag`;
- fails with the list of differences — a translator's CI tells them what a
  new Tagwerk version added.

## Tests (core)

- `LanguagePackTest` — a fixture pack (`tests/Fixtures/language-pack`, code
  `fr`, three keys) offered in a test; appears in
  `Locale::supported()`, validation accepts it, `__()` answers French, a
  missing key answers English, a duplicate or built-in code fails the
  package, an invalid tag fails it.
- `LanguageEndpointTest` — `/api/language` for device, account, nobody with
  `Accept-Language: fr-CH,fr;q=0.9,en;q=0.5`, nobody with an unsupported
  language; hash 404; placeholder-broken keys are absent from the served
  JSON. Cookie tests use `withCredentials()` (AGENTS.md).
- `LocaleFormatsTest` — German and English admin dates and
  `describeRule()` output unchanged; French uses Carbon's formats.
- Vitest — `language.client.ts` logic as a pure util (`utils/language.ts`):
  decides from answer, stored copy, browser; never resolves to a code
  without messages.
- e2e `languages.spec.ts` — with the fixture pack installed in DDEV
  (dev-only path package, like `example-area`): a device set to `fr` reloads
  and the first visible text is French (assert on the first frame via a
  `MutationObserver` that records every text node added before
  `networkidle`, none German); switching in the settings shows no mixed
  frame; offline reload keeps French.
- `ProviderContractTest` covers `languages` automatically.
- `PluginApiBoundaryTest` — the new namespace names no `App\`.

## Rollout

- No migration: codes are already free strings (`households.locale`,
  `users.locale`, `devices.locale`).
- Agenda and `/api/auth/me` keep `locale` next to the new `language` for one
  release — an old cached app reads `locale`.
- An older app without the language plugin still works: it sees `de`/`en`
  only, and a `fr` it does not know falls back to its default.

## Docs

- `docs/plugins.md`: section “Language packs” with the generator call, the
  reference files and the coverage page; “good first plugin” list gains
  “a language”.
- `AGENTS.md` LANGUAGES: `Locale::supported()` instead of `SUPPORTED`,
  `/api/language`, the no-flash rule (“the app mounts after language and
  texts”), no `=== 'de'`.
- `CHANGELOG.md` `[Unreleased]`.
- README (both) mentions that further languages come as packages.

## Settled during review

- **Right-to-left languages are out of scope.** A pack for Arabic or Hebrew
  would need `dir="rtl"` and a layout audit. When someone wants one, the
  contract gains `direction()` (minor release of the API, default `ltr` in an
  abstract base class).
- **`Accept-Language` decides for nobody, `de` and `en` included.** The
  browser no longer picks a bundled language client-side before the server
  answers; `/api/language` is the one source, the browser only the fallback
  without network and without a stored copy.
