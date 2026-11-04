# Language packs — Implementation Plan

**For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:executing-plans
to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for
tracking.

**Goal:** A Composer package can bring a language (`offer('languages', …)`)
that wall, phone, admin, mails and push use like `de` and `en` — with English
as the fallback for missing texts and without ever showing another language
first.

**Architecture:** Contract and testkit in `packages/plugin-api` (MIT). The
core turns `Locale::SUPPORTED` into a list that includes the catalog's packs,
loads their backend files through Laravel's translation loader and serves
their frontend files from `/api/language`. A layer plugin holds the app's
mount until language and texts are there.

**Tech Stack:** PHP 8.5, Laravel 13, Filament 5, Nuxt 4, @nuxtjs/i18n 10.5.0
(pinned), Vitest, Playwright.

**Spec:** `docs/superpowers/specs/2026-11-02-language-packs-design.md`.

## Global Constraints

- The API package never names `App\` (`PluginApiBoundaryTest`).
- German and English output stays as it is: every format change keeps
  today's `de`/`en` strings (assert with `format`/exact strings, not dates
  only).
- A broken pack never stops the app: `LoadReport::fail()`, never an
  exception out of boot.
- No `as any` / `@ts-ignore`; `const props: Props = defineProps<Props>()` in
  layer components.
- After every task: the relevant suite green
  (`ddev exec 'cd apps/platform && php artisan test --filter=…'`,
  `pnpm test`, `pnpm typecheck`), Pint on changed files.

## Acceptance

1. With the fixture pack installed, “Français” appears in the household,
   managed household, device and profile selects and in the phone's
   language settings.
2. A device set to `fr` reloads; the first text node added to the DOM is
   French (e2e).
3. A key missing in the pack shows the English text, front and back.
4. A key with a broken placeholder shows English, never `{count}`.
5. Operations → Plugins lists the pack with coverage and missing keys.
6. Removing the pack: households that chose `fr` show the next language in
   the chain; their stored `locale` stays `fr`.
7. `assertLanguagePackComplete()` passes for a complete pack outside
   Tagwerk and names every difference for an incomplete one.

---

### Task 1: Contract and catalog area

**Files:** Create `packages/plugin-api/src/Languages/LanguagePack.php`;
extend `app/Integrations/ProviderContracts.php` (`BASE['languages']`);
validation in `app/Integrations/Plugins/PluginCatalog.php`; fixture
`tests/Fixtures/language-pack/` (class, `lang/fr.json` with three keys,
`lang/fr/validation.php` with `required`, `frontend/fr.json`); test
`tests/Feature/Plugins/LanguagePackTest.php`.

- [ ] Interface as in the spec (`code`, `name`, `intlTag`, `backendPath`,
  `frontendMessages`), docblocks in English.
- [ ] Catalog checks on `offer('languages', …)` — on instantiation at boot,
  because the code is only known from the object: code matches
  `/^[a-z]{2}$/`, is not `de`/`en`, not offered twice; `intlTag` canonical
  and starting with the code. Any failure → `LoadReport::fail()` with a
  reason (“Language pack offers the built-in code de”).
- [ ] `ProviderContractTest` picks up `languages` (constructor without I/O).
- [ ] Tests: valid pack listed; `de` refused; duplicate `fr` refuses the
  second package only; `fr-XX-invalid` refused.

### Task 2: `Locale::supported()` and loading the backend texts

**Files:** `app/Support/Locale.php`; every reader of `SUPPORTED`
(`HouseholdForm`, `ListManagedHouseholds`, `DeviceForm`, `EditProfile`,
`AuthController:240,298`); `app/Providers/PluginServiceProvider.php`;
create `app/Support/PackTranslationLoader.php`.

- [ ] `Locale::supported(): array<string,string>` — `de`, `en`, then packs
  by code. `Locale::intlTag(string)` — `de-AT`, `en-US`, pack tag. Keep
  `SUPPORTED` as the built-in pair (private), drop the public use.
- [ ] `isSupported()` asks `supported()` — a removed pack's stored code is
  skipped by the existing chain (test: household `fr`, pack absent →
  `config('app.locale')`).
- [ ] In `register()`: `PackTranslationLoader` decorates the
  `translation.loader` and adds each pack's `backendPath()`. It leaves out
  keys whose placeholders differ from the reference (Task 5 computes them;
  until then it passes everything).
- [ ] Tests: `Locale::using('fr', fn () => __('Save'))` French; a missing
  key English; `validation.required` French; `PUT /api/auth/locale` accepts
  `fr`; the language selects list “Français” (`assertFormFieldExists` with
  options).

### Task 3: Formats from Carbon and Intl

**Files:** `app/Providers/Filament/AdminPanelProvider.php:116-117`,
`app/Services/RecurrenceRule.php:240`,
`app/Filament/Resources/Tasks/Schemas/TaskForm.php:191,408`,
`app/Integrations/Holidays/Yasumi/YasumiHolidays.php`; test
`tests/Unit/LocaleFormatsTest.php`.

- [ ] First write the test pinning today's `de`/`en` outputs (admin date,
  date-time, `describeRule()` for a yearly rule, ordinal suffix, task day
  label) — it must pass before the change.
- [ ] Replace each `=== 'de'` by a lookup: explicit format for `de`/`en`
  where Carbon's differs from today's, Carbon's `isoFormat` with
  `Locale::intlTag()` for every other code; ordinals via `NumberFormatter`.
- [ ] Yasumi: `str_replace('-', '_', Locale::intlTag(app()->getLocale()))`;
  test a French holiday name and the English fallback for a code Yasumi
  lacks.
- [ ] `grep -rn "getLocale() === 'de'" app` returns nothing.

### Task 4: `/api/language` and the message files

**Files:** create `app/Http/Controllers/Api/LanguageController.php`,
`app/Support/LanguageMessages.php`; `routes/api.php`; `AppServiceProvider`
limiter `language`; `WallAgendaController:106`, `AuthController:296`,
`DevicePairingController:68`; test
`tests/Feature/LanguageEndpointTest.php`.

- [ ] `LanguageMessages::for(string $code): ?array{hash, messages}` — pack
  file plus every plugin layer's `i18n/locales/<code>.json` merged, broken
  placeholders dropped (Task 5), cached per package versions; `null` for
  `de`/`en`. Hash: first 12 hex of sha256 over the encoded result.
- [ ] `GET /api/language` behind `device.cookie`, `user.cookie`, no
  `auth:sanctum`; actor from `Auth::guard('sanctum')->user()`; nobody →
  `$request->getPreferredLanguage(array_keys(Locale::supported()))`, else
  `config('app.locale')`. Answer `{code, intl, messages}`. Limiter keyed by
  IP (a `Device` has no `getAuthIdentifier()`).
- [ ] `GET /api/language/{code}/{hash}.json` → JSON with
  `Cache-Control: public, max-age=31536000, immutable`; wrong code or hash
  → 404.
- [ ] Agenda, `/api/auth/me`, `/api/devices/me`: add `language` in the same
  shape, keep `locale` for one release.
- [ ] `OpenApiTest` stays green (describe both routes).
- [ ] Tests: device `fr`, account `fr`, nobody with
  `Accept-Language: fr-CH,fr;q=0.9,en;q=0.5` → `fr`, nobody with `ja` →
  `de`, nobody with `en-GB` → `en`; stale hash 404; cookies via
  `withCredentials()`; `Auth::forgetGuards()` between requests.

### Task 5: Coverage and the Plugins page

**Files:** create `app/Plugins/LanguageCoverage.php`;
`app/Filament/Pages/Plugins.php` (+ view); `lang/de.json` for new admin
texts; test `tests/Feature/Plugins/LanguageCoverageTest.php`.

- [ ] Reference: backend keys of `lang/de.json`, groups of `lang/de/*.php`,
  frontend keys of `shared/i18n/locales/en.json`. Compare: missing, extra,
  placeholder mismatch (`:name` backend, `{name}` frontend), plural forms
  (`|` count).
- [ ] Result cached per pack version + `config('app.version')`. Feed the
  mismatched keys to `PackTranslationLoader` and `LanguageMessages`.
- [ ] Plugins page: for packs a coverage column (“97 % — 23 missing”) and a
  modal with the list; `complete-for` from `extra.tagwerk.language` next to
  the running version.
- [ ] Tests: fixture with one missing and one placeholder-broken key →
  both listed, the broken one falls back to English in `__()` and is absent
  from `/api/language/fr/{hash}.json`.

### Task 6: Frontend — hold the mount

**Files:** create `frontend/packages/shared/app/plugins/language.client.ts`,
`frontend/packages/shared/app/utils/language.ts` (+ Vitest);
`shared/nuxt.config.ts` (`fallbackLocale: 'en'`, no browser detection);
`shared/app/composables/useLanguage.ts`; `app/public/sw.js`; the callers of
`apply()` (agenda, `useAuth`, settings).

- [ ] `utils/language.ts` (pure, no Nuxt): `decide(answer, stored,
  browserLanguages)` → `{code, intl, messagesUrl | null}`; never resolves to
  a code whose messages are neither bundled nor available. Vitest with
  `TZ=UTC` as usual.
- [ ] `language.client.ts`: fetch `/api/language` (`baseURL: '/'`,
  `credentials: 'same-origin'`), fetch messages if set,
  `setLocaleMessage`, `setLocale`, `intlLocale`, `document.documentElement.lang`;
  store the copy in `localStorage` `tagwerk-language` (try/catch). The
  plugin awaits all of it, so Nuxt mounts afterwards. Failure → stored copy
  → browser language among `de`/`en`.
- [ ] `useLanguage().apply()` takes `{code, intl, messages}` (and still a
  bare code for one release), loads messages before `setLocale`.
  `LanguageCode` becomes `string`.
- [ ] `sw.js`: `/api/language` network-first, `/api/language/*/*.json`
  cache-first. Its scope rule (only navigations to `/` as shell) stays.
- [ ] Vue-i18n: `missingWarn`/`fallbackWarn` off for non-bundled codes.
- [ ] `pnpm test`, `pnpm typecheck`, `pnpm build`.

### Task 7: Testkit and reference files

**Files:** `packages/plugin-api/src/Testing/PluginAssertions.php`;
`packages/plugin-api/resources/reference/`; create
`app/Console/Commands/LanguageReferenceCommand.php`
(`tagwerk:language-reference`); test
`tests/Feature/Plugins/LanguageReferenceTest.php`; extend
`PluginTestkitTest`.

- [ ] Command writes `backend.json`, `frontend.json`, `groups/*.php` (English
  only) into the API package. `LanguageReferenceTest` fails when they differ
  from the core's files — run the command after adding texts.
- [ ] `assertLanguagePackComplete(LanguagePack $pack)` uses the shared
  comparison (move the pure part of `LanguageCoverage` into the API package
  so core and testkit run the same code; the core class becomes a thin
  wrapper) and fails listing every difference.
- [ ] Testkit test: complete fixture passes; incomplete names the keys.

### Task 8: Generator

**Files:** `app/Console/Commands/MakePluginCommand.php`; stub
`stubs/plugin/language.stub`; `tests/Feature/MakePluginTest.php`.

- [ ] `--language=<code>`: class implementing `LanguagePack`, `lang/<code>.json`
  and `lang/<code>/{auth,validation}.php` copied from the reference,
  `frontend/<code>.json`, test calling `assertLanguagePackComplete()`,
  `composer.json` with MIT, `extra.tagwerk.language.complete-for` set to the
  running version.
- [ ] `MakePluginTest` generates one, loads it, runs its PHPUnit with only
  the app's autoloader (existing pattern). `templates/plugin` stays a widget
  template — no change there.

### Task 9: e2e

**Files:** fixture pack as a dev-only path package (like
`tagwerk/example-area`, `require-dev`), `frontend/packages/e2e/tests/languages.spec.ts`.

- [ ] Device paired in an own household set to `fr`; `addInitScript` with a
  `MutationObserver` recording every text node until `networkidle`; reload;
  assert no recorded text is in the German or English file and at least
  one is French.
- [ ] Switch `de` → `fr` in the settings: no recorded frame mixes them.
- [ ] Offline reload (`context.setOffline(true)`) stays French.
- [ ] Remove the household in teardown (`E2E %`). `pnpm build` before the
  run.

### Task 10: Docs

- [ ] `docs/plugins.md`: “Language packs” (generator, reference command,
  coverage page, fallback rule); “good first plugin” gains a language.
- [ ] `AGENTS.md` LANGUAGES: `Locale::supported()`, `/api/language`, “the
  app mounts after language and texts”, no `=== 'de'`, reference files
  regenerated with `tagwerk:language-reference`. Update the date comment.
- [ ] `CHANGELOG.md` `[Unreleased]`: cause, not file list — why the server
  serves texts (Packagist packs have no build-time sources) and why
  English is the fallback.
- [ ] `README.md` + `README.de.md`: further languages as packages.
- [ ] `llms.txt` if a guide moved.
