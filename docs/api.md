# API for TV clients

JSON API in `routes/api.php`, prefix `/api`, route names `api.*`. Every error
under `/api/*` is JSON. Times are ISO 8601 in UTC; the client formats them in
the viewer's time zone (`SPORTS_TIMEZONE`, default Europe/Moscow, is what the
web app uses).

## Authentication

Sanctum personal access tokens. There is no registration; accounts are created
with `php artisan sports:user`.

```http
POST /api/tokens
Content-Type: application/json

{"email": "you@example.com", "password": "…", "device_name": "Living room TV"}
```

`201 Created`:

```json
{
    "token": "1|abc…",
    "user": { "id": 1, "name": "Lenar", "email": "you@example.com" }
}
```

Wrong credentials → `422` with `errors.email`. Throttled to 6 attempts per
minute. 2FA is **not** asked here (a device-code flow is planned).

All other endpoints need `Authorization: Bearer <token>`.

`DELETE /api/tokens/current` — revoke the token in use.

## Endpoints

| Method | Path                                             | Returns                                                                 |
| ------ | ------------------------------------------------ | ----------------------------------------------------------------------- |
| GET    | `/api/home`                                      | `featured`, `live`, `starting_soon` (events), `sports[{sport, events}]` |
| GET    | `/api/sports`                                    | `data[]`: sport + `competitions[]`                                      |
| GET    | `/api/events`                                    | `data[]`: events (filters below)                                        |
| GET    | `/api/events/{id}`                               | `data`: event with broadcasts                                           |
| GET    | `/api/events/{id}/broadcasts/{broadcast}/stream` | `broadcast` + `stream {type, url}`                                      |
| GET    | `/api/channels`                                  | `data[]`: channels with what is on now                                  |
| GET    | `/api/channels/{id}`                             | `data` channel, `stream {type, url}`, `schedule[]`                      |

### `GET /api/events` filters

| Param         | Example         | Notes                                  |
| ------------- | --------------- | -------------------------------------- |
| `sport`       | `hockey`        | sport key (see `/api/sports`)          |
| `competition` | `khl`           | competition key                        |
| `status`      | `live,upcoming` | comma list or array of `upcoming       | live | finished` |
| `date`        | `2026-10-06`    | day in `SPORTS_TIMEZONE`               |
| `q`           | `цска`          | search, max 100 chars (longer → `422`) |

## Objects

### Event

```json
{
    "id": "1002",
    "title": "Independiente Medellin — Santa Fe",
    "sport": {
        "key": "football",
        "name": "Футбол",
        "emoji": "⚽",
        "color": "#22c55e"
    },
    "competition": {
        "key": "football-colombia-primera-a-clausura",
        "sport": "football",
        "name": "Colombia: Primera A: Clausura",
        "short_name": "Colombia: Primera A: Clausura",
        "country": null,
        "logo": null
    },
    "home": {
        "name": "Independiente Medellin",
        "short_name": "IM",
        "color": "#296bae",
        "logo": "/storage/logos/teams/5485….webp",
        "flag": false
    },
    "away": {
        "name": "Santa Fe",
        "short_name": "SF",
        "color": "#6b29ae",
        "logo": "/storage/logos/teams/8ce1….webp",
        "flag": false
    },
    "round": null,
    "venue": null,
    "starts_at": "2026-10-06T00:00:00+00:00",
    "ends_at": "2026-10-06T01:55:00+00:00",
    "status": "finished",
    "live_label": null,
    "progress": 1,
    "score": { "home": 0, "away": 1 },
    "broadcasts": [/* Broadcast */]
}
```

- `id` is a string (fake catalog ids look like `epl-20261006-2`).
- `logo` paths are relative to the site root; `flag: true` means the logo is a
  country flag and should fill the circle.
- `status` is computed from kick-off and the sport's typical duration;
  `progress` is 0…1; `score` is present only when live or finished.

### Broadcast

```json
{
    "id": 22253,
    "channel": "Win Sports",
    "channel_number": 276,
    "country": null,
    "language": null,
    "quality": null,
    "provider": "Dispatcharr",
    "epg_title": "Fútbol Colombiano Primera División",
    "logo": "http://iptv.home.internal/api/channels/logos/198/cache/",
    "starts_at": "2026-10-06T00:00:00+00:00",
    "ends_at": "2026-10-06T02:00:00+00:00",
    "live": true,
    "replay": false
}
```

- `live` — shown during the match; `replay` — shown after it (up to 14 days).
- Channels have no archive: a replay can only be watched while it is on air.
  Clients should show future replays as times, not as a play button.
- Broadcasts are sorted by preferred language, then quality.

### Stream

`GET /api/events/{id}/broadcasts/{broadcast}/stream`:

```json
{
    "broadcast": {/* Broadcast */},
    "stream": {
        "type": "mpegts",
        "url": "http://iptv.home.internal/live/<user>/<pass>/165?output_format=ts"
    }
}
```

`type` is `demo` with `url: null` for the fake catalog. The URL contains the
Xtream credentials — it is returned only here and in `/api/channels/{id}`,
never in lists. Unknown broadcast id → `404`.

### Channel

```json
{
    "id": 12,
    "number": 227,
    "name": "Sport1",
    "logo": "http://…/logos/143/cache/",
    "category": "Спорт — мир",
    "now": {
        "event_id": 1201,
        "title": "Liberec — Frolunda HC",
        "replay": false
    }
}
```

`now` is `null` when nothing from the schedule is on air. `/api/channels/{id}`
adds `stream` and `schedule[] {starts_at, ends_at, replay, event}` (now and upcoming).

## Notes for client authors

- Recompute `status` locally from `starts_at`/`ends_at` if you keep data open
  for long; the web client does.
- Streams are MPEG-TS that Dispatcharr delivers in bursts (~6 s every ~7 s):
  use a generous buffer and don't chase the live edge.
