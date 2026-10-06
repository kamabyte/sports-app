# Operations

## Requirements

PHP 8.4 (intl, zlib, xmlreader, gd with WebP), Composer, Node + npm, SQLite.
Locally the app runs under Laravel Valet at `http://sports-app.test`.

## Setup

```bash
composer setup              # deps, .env, key, migrations, frontend build
php artisan storage:link    # logos are served from storage/app/public
php artisan sports:user you@example.com --name="Name"   # account (no registration)
php artisan fixtures:import # schedule
php artisan guide:import    # channels + guides + broadcasts
php artisan logos:sync      # logos to local storage
composer dev                # server, queue, logs, scheduler, Vite
```

`php artisan db:seed` creates `demo@sports.test` / `password` — development only.

## Configuration (`.env`, `config/sports.php`)

| Variable                                                                                        | Default                     | Meaning                                                |
| ----------------------------------------------------------------------------------------------- | --------------------------- | ------------------------------------------------------ |
| `SPORTS_SOURCE`                                                                                 | `dispatcharr`               | `fake` = generated catalog (tests, demos)              |
| `SPORTS_TIMEZONE`                                                                               | `Europe/Moscow`             | how days are split and times shown                     |
| `DISPATCHARR_URL`                                                                               | `http://iptv.home.internal` | Dispatcharr base URL                                   |
| `DISPATCHARR_USERNAME` / `_PASSWORD`                                                            | —                           | Xtream (XC) login; **only in `.env`**, never committed |
| `DISPATCHARR_NAME`                                                                              | `Dispatcharr`               | provider label on broadcasts                           |
| `GUIDES_REFRESH_HOURS`                                                                          | `12`                        | how often XMLTV files are re-downloaded                |
| `GUIDES_DAYS_AHEAD`                                                                             | `7`                         | how far ahead guide programmes are read                |
| `FIXTURES_DAYS_BACK` / `FIXTURES_DAYS_AHEAD`                                                    | `0` / `6`                   | schedule window                                        |
| `ESPN_ENABLED`, `KHL_ENABLED`, `EUROLEAGUE_ENABLED`, `LIVESCORE_ENABLED`, `THESPORTSDB_ENABLED` | `true`                      | toggle schedule sources                                |
| `THESPORTSDB_KEY` / `_PAUSE_SECONDS` / `_DAYS_AHEAD`                                            | `123` / `21` / `1`          | free key limits                                        |

Lists that are not env variables live in `config/sports.php`: ESPN leagues,
LiveScore sports, TheSportsDB sports, guide sources (`guides.sources`), manual
channel ↔ guide links (`guides.channels`), channel languages by Dispatcharr
category (`providers.dispatcharr.category_languages`), preferred broadcast
languages.

## Scheduled jobs (`routes/console.php`)

| Command           | When             | Typical run                                                                              |
| ----------------- | ---------------- | ---------------------------------------------------------------------------------------- |
| `guide:import`    | every 30 minutes | ~45 s (6 s reading guides, rest is matching); first run also downloads ~130 MB of guides |
| `fixtures:import` | hourly           | a few minutes (TheSportsDB pauses 21 s between requests)                                 |
| `logos:sync`      | hourly at :20    | seconds once logos are cached                                                            |

All use `withoutOverlapping()`. `composer dev` runs `schedule:work`; on a
server add the usual cron entry `* * * * * php artisan schedule:run`.

`guide:import` output:

```
WARN  Guide not updated: Чехия: cURL error 28 …      ← used the previous file
INFO  Channels: 269 (with a guide: 99), sports programmes: 1981, matched airings: 204, matches with broadcasts: 106.
```

It **fails without touching data** when Dispatcharr returns no channels or no
guide file can be loaded at all.

## Files

| Path                                                 | What                                                          |
| ---------------------------------------------------- | ------------------------------------------------------------- |
| `database/database.sqlite`                           | all data                                                      |
| `storage/app/private/guides/{key}`                   | downloaded XMLTV (gzip or plain), 16 files                    |
| `storage/app/public/logos/{teams,competitions}`      | WebP logos 256×256                                            |
| `storage/logs/laravel.log`                           | errors (failed sources, downloads)                            |
| `storage/app/dispatcharr-epg-backup-2026-10-06.json` | Dispatcharr channel → guide map before the 2026-10-06 changes |

## Troubleshooting

| Symptom                               | Check                                                                                                                                                                                                              |
| ------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| A match is on TV but not in the app   | Is it in the schedule? `fixtures` table / `fixtures:import`. Does the channel have a guide? `channels.guide_source`. Does `TitleParser` understand the title? Try it in `php artisan tinker`.                      |
| Channel linked to the wrong guide     | Add `'Channel name' => 'source:guide-id'` (or `null`) to `sports.guides.channels`.                                                                                                                                 |
| Broadcast attached to the wrong match | Name similarity: add an exonym/generic word in `NameSimilarity`, add a dataset row to `FixturesTest` / `MatchingTest`. Wrong team aliases may need a rebuild (delete `teams`/`team_aliases`, re-run both imports). |
| Studio show shown as a match          | Add the word to `TitleParser::SHOWS`.                                                                                                                                                                              |
| Player stalls                         | Keep mpegts.js settings in `live-player.tsx` (no latency chasing).                                                                                                                                                 |
| Logos missing                         | `storage:link`, then `logos:sync`; failures retry after 24 h (`logo_failed_at`).                                                                                                                                   |
| Few channels have a guide             | Most "Спорт — мир" channels have no public guide at all. New file → add to `guides.sources`.                                                                                                                       |

## Homelab Dispatcharr

- Host `iptv.home.internal` (SSH `lenar@10.20.1.100`), container `iptv-wmfz8f-dispatcharr-1`.
- The app needs only the Xtream API, `/output/m3u?tvg_id_source=tvg_id` (no
  login) and stream URLs. It does not need admin access.
- Admin REST API (`/api/...`) needs a JWT. Create one through the Django shell
  inside the container (`source /etc/profile.d/dispatcharr.sh` first — it holds
  `DJANGO_SECRET_KEY`): `RefreshToken.for_user(<admin>).access_token`. Never
  print or commit the token.
- **Changes made on 2026-10-06** (additive, unused by the app since it reads
  guides itself): guide sources 4–16 (epgshare01 files) and guide mappings for
  57 previously unmapped channels. **Rollback:** restore `epg_data_id` per
  channel from `storage/app/dispatcharr-epg-backup-2026-10-06.json`, then delete
  sources 4–16.
- Rule for future changes: additive and reversible only; back up before; don't
  change admin passwords.

## Quality checks

```bash
php artisan test                         # Pest, 215 tests (fake HTTP, no network)
vendor/bin/phpstan analyse               # Larastan level 7
vendor/bin/pint                          # code style
npm run check && npm run types:check     # vp check (lint/format), tsc
```

CI (`.github/workflows/tests.yml`) runs `composer ci:check` on PHP 8.4.
Tests never hit the network: `fakeDispatcharr()` / `fakeGuide()` in
`tests/Helpers.php`, local disk faked per test.
