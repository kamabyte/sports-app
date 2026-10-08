# CLAUDE.md

Sports — an events-first sports section over the homelab IPTV (Dispatcharr):
matches from sports APIs, broadcasts attached from our own XMLTV guides,
streams played in the browser. Laravel 13 + Inertia 3 + React 19 + TS,
Tailwind 4, shadcn/ui, Fortify, Sanctum, SQLite.

**Current direction (8 Oct 2026): a fixtures catalog, like a ticket site.**
Flashscore-like structure (sport → country → competition → fixtures → event,
teams, national teams, players, logos) but **only upcoming events**: no
results, scores, standings, brackets or history. Events are the future slots
for IPTV. Result tables from the first iteration stay in the DB, unused.

**Standalone sports catalog.**
`docs/sports-catalog-reorganization-plan.md` is authoritative; coverage and
verified Flashscore fields are in `docs/flashscore-catalog-coverage.md`.
`SPORTS_MODE=standalone` (default) serves the catalog (`app/Sports`, tables
`catalog_*`, pages `resources/js/pages/catalog`); `SPORTS_MODE=iptv` restores
the legacy IPTV app unchanged. IPTV stays off until the owner approves the
sports UX/UI **and** separately authorizes IPTV work.

Read first: `docs/architecture.md` (pipeline, matching rules, where to change
what), `docs/api.md` (TV-client API), `docs/operations.md` (commands, config,
troubleshooting, homelab). `README.md` is the Russian overview.

## Invariants

- **Schedule first.** Events come only from `fixtures` → `MatchBuilder`. TV
  guides only attach `airings`; unmatched programmes are dropped, matches
  without airings are hidden. Don't make the guide create events.
- **Own guides.** Dispatcharr gives channels, `tvg-id`s and streams; its XMLTV
  is not used. Guide sources live in `config/sports.php` → `guides`.
- **Secrets.** Xtream login only in `.env` (`.env.example` stays blank). Stream
  URLs contain it: expose them only after login on the watch page and the
  stream / channel-detail API — never in event lists or resources for lists.
- **Homelab Dispatcharr changes** must be additive and reversible, backed up
  first; never print the admin JWT; never change admin passwords.
- **Catalog identity.** Provider ids before names, namespaced and
  case-sensitive (`catalog_*_sources`); never merge by name, never invent ids,
  translations, countries or scores. Tennis pairs/relays are `entries` of
  athletes, never teams. Unknown stays unknown (time, opponent, status code).
- **No anti-bot circumvention.** Championat and the signed Flashscore feed
  (`/x/feed/`) are off-limits. The catalog reads data embedded in ordinary
  public competition/team pages only (`PageFetcher` allowlist). The Flashscore lite HTML source is allowed only as plain public
  pages: a challenge fails the day, and the source stays disabled by default
  (site terms; enabling it is the owner's call).
- Commit or push only when the user asks.

## Conventions

- Code style from the sibling project mytube: API controllers in
  `App\Http\Controllers`, web in `App\Http\Controllers\Web`; resources to
  Inertia via `->resolve()`; FormRequests with typed accessors; typed class
  constants; flat returns; small readonly result objects.
- **Comments and UI text are in Russian**; identifiers in English.
- Every parser/matching fix gets a dataset row from real guide data
  (`tests/Unit/TitleParserTest.php`, `tests/Feature/FixturesTest.php`,
  `MatchingTest.php`, `ChannelMatcherTest.php`).

## Commands

```bash
php artisan test --compact                 # 300+ tests, no network
vendor/bin/phpstan analyse --memory-limit=1G
vendor/bin/pint                            # no git --dirty needed
npm run check && npm run types:check
php artisan catalog:discover               # page registry from the Flashscore sitemap
php artisan catalog:import [--sport=tennis] [--dry-run]   # planned run without options (needs FLASHSCORE_ENABLED)
php artisan guide:import | fixtures:import | logos:sync   # legacy, no-ops in standalone
```

Screenshots: `php artisan serve --port=8123` + Playwright with installed
Chrome (`channel: 'chrome'`).

## Gotchas

- `Http::fake()` stacks: use `resetHttpFakes()` (tests/Helpers.php) before
  faking a new "version" of a source.
- Raw Cyrillic in test URLs must be `rawurlencode`d.
- Guide files are cached by a cache flag, not mtime; tests set
  `refresh_hours = 0` and fake the `local` disk per test (tests/Pest.php).
- Wrong team aliases poison matching; fix the rule, then rebuild teams/aliases.
