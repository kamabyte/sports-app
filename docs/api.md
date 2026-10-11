# API for TV clients

JSON API for the Apple TV and Android TV apps. It serves the same sports
catalog as the web client: sport → category (country, region, tour) →
competition → upcoming events, with teams, national teams and players, plus TV
broadcasts from our own guides and their streams.

- Base URL: `http://sports.home.internal/api/v1` (homelab, see
  `deploy/dokploy/README.md`); locally `http://localhost:8000/api/v1`.
- Routes: `routes/api.php`, names `api.v1.*`. Controllers:
  `app/Http/Controllers/Catalog`. Payloads are built by
  `App\Sports\Catalog\Pages`, which the Inertia pages also use, so the web
  client and the API never drift apart.
- Tests: `tests/Feature/CatalogApiTest.php`.

**Catalog only, no results.** Events are future (and running) slots: no
scores, standings, brackets or history. Finished events disappear from every
list, and their `/events/{id}` returns `404`.

## Conventions

| Topic        | Rule                                                                                                                                                                                                                                                                                                               |
| ------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| Format       | JSON, UTF-8. Send `Accept: application/json`. Every error under `/api/*` is JSON: `{"message": "…"}`; validation adds `errors: {field: [..]}` (`422`).                                                                                                                                                             |
| Auth         | `Authorization: Bearer <token>` on everything except `POST /tokens` and `POST /device/…`. Missing or revoked token → `401`.                                                                                                                                                                                        |
| Times        | `starts_at`, `ends_at`: ISO 8601 with offset, in UTC. Format them in the device's time zone.                                                                                                                                                                                                                       |
| Days         | `date` params and fields are `YYYY-MM-DD` **in the server time zone** (`GET /me` → `timezone`, `Europe/Moscow`). A "day" in `/schedule` is that zone's midnight to midnight. Past dates are clamped to today.                                                                                                      |
| Language     | Names are in the catalog locale (`GET /me` → `locale`, `ru`) when a translation is known, otherwise as the source spells them. `original_name` is the source name when it differs (show it as a subtitle: «Арсенал» / Arsenal). UI labels from the server (`status_label`, `badges`, section `title`) are Russian. |
| Ids          | Integers. Sports and categories are addressed by `key` (string), everything else by `id`.                                                                                                                                                                                                                          |
| `url` fields | Paths of the web client's page (`/team/13/crystal-palace`). Use them for "open on the web" or share links. Navigate the app by `id`/`key`.                                                                                                                                                                         |
| Images       | `logo`, `flag`, `portrait` are absolute URLs (Flashscore static, flagcdn) or `null`. Broadcast `logo` is a Dispatcharr URL on the home network.                                                                                                                                                                    |
| Unknown data | `null`, never a guess: unknown kick-off time (`time_precision` ≠ `exact`), unknown opponent (`participant: null` + `label`), no flag. Show it as unknown.                                                                                                                                                          |
| Paging       | Long lists (`fixtures`, `upcoming`) take `limit` and return `limit` + `more`. To load more, repeat the request with `limit` + one step (60 for competitions, 30 for teams and players). `limit` is clamped to `[step, 1200]`.                                                                                      |
| Not found    | Unknown sport key, category key or id → `404`.                                                                                                                                                                                                                                                                     |

## Authentication

Sanctum personal access tokens, one per device. There is no registration:
accounts are created on the server with `php artisan sports:user`. A TV signs
in one of two ways; both end with the same token.

### Sign in with a code (recommended)

The TV shows a short code; the user approves it on the site from a phone or
laptop, where the normal web login (with 2FA) applies. Modeled on OAuth device
authorization ([RFC 8628](https://www.rfc-editor.org/rfc/rfc8628)).

1. Request a code (throttled to 10 per minute):

    ```http
    POST /api/v1/device/codes
    Content-Type: application/json
    Accept: application/json

    {"device_name": "Living room Apple TV"}
    ```

    `201 Created`:

    ```json
    {
        "device_code": "8yq0…40 chars",
        "user_code": "BCDF-GHJK",
        "verification_url": "http://sports.home.internal/device",
        "verification_url_complete": "http://sports.home.internal/device?code=BCDFGHJK",
        "expires_in": 600,
        "interval": 5
    }
    ```

2. Show `user_code` large and `verification_url` under it. Render
   `verification_url_complete` as a QR code: scanning it skips typing. Keep
   `device_code` secret; it is the only proof the TV owns the request.
3. The user opens the link, signs in if needed, sees `device_name` and the
   code, and taps «Подключить» or «Отклонить».
4. Meanwhile poll every `interval` seconds:

    ```http
    POST /api/v1/device/token
    Content-Type: application/json
    Accept: application/json

    {"device_code": "8yq0…"}
    ```

    | Response | `error`                 | Client action                                        |
    | -------- | ----------------------- | ---------------------------------------------------- |
    | `201`    | —                       | `{token, user}` as for `POST /tokens`. Signed in.    |
    | `400`    | `authorization_pending` | Keep polling.                                        |
    | `400`    | `slow_down`             | Polled sooner than `interval`: add 5 s and continue. |
    | `400`    | `access_denied`         | The user declined. Offer to start again.             |
    | `400`    | `expired_token`         | Over 10 minutes passed. Request a new code.          |
    | `400`    | `invalid_grant`         | Unknown or already used code. Request a new code.    |

    Errors are `{"error": "…", "message": "…"}` (`message` is Russian text
    for display). The token is issued once; after that the code is gone.
    Stop polling when the code expires (`expires_in`).

### Sign in with a password

```http
POST /api/v1/tokens
Content-Type: application/json
Accept: application/json

{"email": "you@example.com", "password": "…", "device_name": "Living room Apple TV"}
```

`201 Created`:

```json
{
    "token": "1|abc…",
    "user": { "id": 1, "name": "Lenar", "email": "you@example.com" }
}
```

2FA is not asked here: prefer the code flow.

Wrong credentials → `422` with `errors.email`. Throttled to 6 attempts per
minute (`429`).

### Token

Store the token in the Keychain (tvOS) or encrypted
preferences (Android); it does not expire, so revoke it on sign-out:

`DELETE /api/v1/tokens/current` → `204`.

`GET /api/v1/me` checks the token and returns display settings:

```json
{
    "user": { "id": 1, "name": "Lenar", "email": "you@example.com" },
    "timezone": "Europe/Moscow",
    "locale": "ru"
}
```

## Endpoints

| Method | Path                                         | Screen                                  |
| ------ | -------------------------------------------- | --------------------------------------- |
| POST   | `/device/codes`, `/device/token`             | Sign in with a code                     |
| POST   | `/tokens`                                    | Sign in with a password                 |
| DELETE | `/tokens/current`                            | Sign out                                |
| GET    | `/me`                                        | Token check, time zone                  |
| GET    | `/home`                                      | Home: on TV today, shelves per sport    |
| GET    | `/schedule`                                  | Day schedule with filters               |
| GET    | `/search?q=`                                 | Search                                  |
| GET    | `/sports`                                    | Sport menu                              |
| GET    | `/sports/{sport}`                            | Sport: popular competitions, categories |
| GET    | `/sports/{sport}/competitions`               | All competitions by category            |
| GET    | `/sports/{sport}/categories/{category}`      | Category: its competitions              |
| GET    | `/sports/{sport}/national-teams?country=`    | National teams                          |
| GET    | `/sports/{sport}/players?q=`                 | Players                                 |
| GET    | `/competitions/{id}?limit=`                  | Competition fixtures                    |
| GET    | `/teams/{id}?limit=`                         | Team or national team                   |
| GET    | `/players/{id}?limit=`                       | Player                                  |
| GET    | `/events/{id}`                               | Event with broadcasts                   |
| GET    | `/events/{id}/broadcasts/{broadcast}/stream` | Play a broadcast                        |

### `GET /home`

```json
{
    "date": "2026-10-11",
    "broadcast_count": 23,
    "broadcast_groups": [/* Group — first 8 events on TV today */],
    "sports": [
        {
            "key": "football",
            "name": "Футбол",
            "url": "/sport/football",
            "week": 412,
            "day": "2026-10-11",
            "groups": [/* Group — up to 6 events of `day` */]
        }
    ]
}
```

- `broadcast_count`: events with a live broadcast today. Show "All" with
  `GET /schedule?tv=1`.
- `sports`: only sports with data, in menu order. `week`: events in the next
  7 days. `day`: today, or the next day with events, or `null` if there are
  none.

### `GET /schedule`

| Param         | Example      | Notes                                                                      |
| ------------- | ------------ | -------------------------------------------------------------------------- |
| `date`        | `2026-10-12` | Day in the server zone. Default today; past → today. Other format → `422`. |
| `sport`       | `hockey`     | Sport key. Unknown key → no filter.                                        |
| `category`    | `russia`     | Category key of `sport`; ignored without `sport` or for another sport.     |
| `competition` | `42`         | Competition id of `sport`; ignored the same way.                           |
| `tv`          | `1`          | Only events with a live broadcast that day, ordered by broadcast start.    |

```json
{
    "filters": {
        "date": "2026-10-12",
        "sport": "hockey",
        "category": { "key": "russia", "name": "Россия" },
        "competition": null,
        "tv": false
    },
    "groups": [/* Group */],
    "next_date": null
}
```

`filters` echo what was **applied**: a dropped filter comes back `null`.
`next_date` is set only when `groups` is empty: the next day with events for
the same filters (offer a "Next: Mon 13" button), or `null` if nothing lies
ahead.

A day contains events that start that day, date-only events of that day, and
multi-day events (a golf tournament) that run through it. A parent event (a
Grand Prix weekend) is not listed itself: its sessions are, in a group with
`parent`.

### `GET /search?q=`

`q`: up to 100 characters (longer → `422`); Russian, English and aliases
match from the start of any word.

```json
{
    "query": "франц",
    "too_short": false,
    "results": {
        "sports": [{ "key": "…", "name": "…", "url": "…", "has_data": true }],
        "competitions": [/* Competition */],
        "teams": [/* Team — clubs */],
        "national_teams": [/* Team — all variants: senior, W, U21, Ol. */],
        "players": [/* Athlete */]
    }
}
```

`too_short` is `true` for a 1-character query; all lists are then empty. Each
list holds up to 40 results.

### `GET /sports`

```json
{
    "data": [
        {
            "key": "football",
            "name": "Футбол",
            "family": "team",
            "url": "/sport/football",
            "has_data": true,
            "wave": "A"
        }
    ]
}
```

All sports in menu order. `has_data: false`: the catalog does not cover
this sport yet. Show it greyed out or hide it, but don't present it as having
no events. `family` decides how events look (see [Event](#event)).

### `GET /sports/{sport}`

```json
{
    "sport": {/* Sport */},
    "popular": [/* Competition — up to 8 */],
    "categories": [
        {
            "key": "atp",
            "kind": "tour",
            "name": "ATP",
            "url": "/sport/tennis/atp",
            "flag": null,
            "competitions_count": 61,
            "children": [/* same shape, no children: "ATP - Doubles" */]
        }
    ],
    "has_national_teams": false,
    "has_players": true
}
```

Today's events of the sport: `GET /schedule?sport={sport}`.
`has_national_teams` / `has_players`: show the corresponding entries.

### `GET /sports/{sport}/competitions`

```json
{
    "sport": {/* Sport */},
    "sections": [
        {
            "key": "england",
            "name": "Англия",
            "kind": "country",
            "url": "…",
            "flag": "https://…",
            "parent_key": null,
            "competitions": [/* Competition */]
        }
    ]
}
```

A competition can be listed under several categories (its primary one plus,
for example, a region).

### `GET /sports/{sport}/categories/{category}`

```json
{
    "sport": {/* Sport */},
    "category": {
        "key": "england",
        "kind": "country",
        "name": "Англия",
        "flag": "https://flagcdn.com/w80/gb-eng.png",
        "url": "/sport/football/england",
        "national_teams_country": "england"
    },
    "sections": [{ "title": "Лиги", "competitions": [/* Competition */] }],
    "children": [
        { "key": "…", "name": "…", "url": "…", "competitions_count": 4 }
    ]
}
```

Section titles, in this order: Лиги, Кубки, Турниры, Женские турниры,
Молодёжные, Сборные. `national_teams_country`: country code for
`/national-teams?country=` when this country has national teams, else
`null`. The category's events: `GET /schedule?sport=…&category=…`.

### `GET /sports/{sport}/national-teams?country=`

```json
{
    "sport": {/* Sport */},
    "countries": [
        { "code": "france", "name": "Франция", "flag": "https://…", "teams": 4 }
    ],
    "country": { "code": "france", "name": "Франция", "flag": "https://…" },
    "teams": [/* Team — senior first, then youth, olympic, women */],
    "without_country": [/* Team — the source gave no country */]
}
```

Without `country`, `country` is `null` and `teams` is empty. Country codes are
catalog codes (`france`, `england`), not ISO codes.

### `GET /sports/{sport}/players?q=`

`{ "sport": {…}, "query": "…", "players": [/* Athlete */] }`: up to 120,
by name; `q` filters by any known name.

### `GET /competitions/{id}?limit=`

```json
{
    "competition": {
        "id": 1,
        "name": "Премьер-лига",
        "original_name": "Premier League",
        "logo": "https://…",
        "url": "/sport/football/england/premier-league",
        "badges": [],
        "category": {
            "key": "england",
            "name": "Англия",
            "flag": "https://…",
            "url": "…"
        }
    },
    "sport": {/* Sport */},
    "fixtures": [/* Event — upcoming, by start */],
    "limit": 60,
    "more": true
}
```

### `GET /teams/{id}?limit=`

```json
{
    "team": {/* Team */},
    "sport": {/* Sport */},
    "upcoming": [/* Event */],
    "limit": 30,
    "more": false,
    "competitions": [/* Competition — everything the team plays in */],
    "variants": [
        /* Team — other national teams of the same country, incl. this one */
    ]
}
```

### `GET /players/{id}?limit=`

```json
{
    "athlete": { /* Athlete */ "gender": "men" },
    "sport": {/* Sport */},
    "upcoming": [/* Event — singles, doubles and relays */],
    "limit": 30,
    "more": false,
    "competitions": [/* Competition */],
    "partnerships": [{ "kind": "pair", "partners": [/* Athlete */] }]
}
```

### `GET /events/{id}`

```json
{
    "event": {/* EventDetail */},
    "broadcasts": [/* Broadcast */],
    "sport": {/* Sport */},
    "related": [/* Event — up to 12 */],
    "related_title": "Ближайшие матчи турнира"
}
```

`related`: other sessions of the same meeting (`related_title` «Другие
сессии: …») or the next events of the competition.

### `GET /events/{id}/broadcasts/{broadcast}/stream`

```json
{
    "broadcast": {/* Broadcast */},
    "stream": {
        "type": "mpegts",
        "url": "http://iptv.home.internal/live/<user>/<pass>/165?output_format=ts"
    }
}
```

`404` when the broadcast does not belong to the event or has ended. The URL
contains the Xtream credentials: it is returned only here, never in lists or
in `/events/{id}`. Don't log it, cache it to disk or show it. Request it right
before playback.

## Objects

### Sport

`{ key, name, family, url, has_data, wave }`. Inside a Competition it is short:
`{ key, name, family }`.

| `family`  | Sports                                   | Event shape                  |
| --------- | ---------------------------------------- | ---------------------------- |
| `team`    | football, hockey, basketball, esports, … | two sides, team logos        |
| `racket`  | tennis, table tennis, badminton          | two sides, players or pairs  |
| `duel`    | snooker, darts                           | two sides, players           |
| `bout`    | boxing, MMA                              | two sides (fights)           |
| `cricket` | cricket                                  | two sides                    |
| `race`    | motorsport, cycling, winter sports       | meeting → sessions, entrants |
| `golf`    | golf                                     | tournament, entrants         |

`wave`: coverage priority (A, B, C). Informational only.

### Group

Events of one competition (and one parent event) under a heading. Groups come
sorted by sport, category, competition importance and name. Events inside a
group come sorted by start (by broadcast start with `tv=1`).

```json
{
    "key": "1:0",
    "competition": {/* Competition */},
    "edition": "2026/2027",
    "parent": null,
    "events": [/* Event */]
}
```

`parent`: `{ name, url }` of the meeting whose sessions these are («Гран-при
Сингапура»), else `null`. Heading: `category.name · competition.name ·
edition`.

### Competition

```json
{
    "id": 1,
    "name": "Премьер-лига",
    "original_name": "Premier League",
    "url": "/sport/football/england/premier-league",
    "logo": "https://static.flashscore.com/res/image/data/rN76jpW1-EcEfABe8.png",
    "sport": { "key": "football", "name": "Футбол", "family": "team" },
    "category": {
        "key": "england",
        "name": "Англия",
        "kind": "country",
        "url": "/sport/football/england",
        "flag": "https://flagcdn.com/w80/gb-eng.png"
    },
    "badges": []
}
```

`badges`: ready-made Russian labels: «Сборные», «Женщины», «U21», «Парный
разряд», …. Category `kind`: `country`, `region`, `tour`, `series`,
`discipline`, `weight_class`, `game`, `promotion`, `international`, `other`.

### Event

A row in any list.

```json
{
    "id": 7,
    "url": "/event/7",
    "kind": "match",
    "family": "team",
    "name": null,
    "round": "Тур 6",
    "parent": null,
    "status": "scheduled",
    "status_label": "Не начался",
    "time_precision": "exact",
    "starts_at": "2026-10-11T13:00:00+00:00",
    "broadcast_starts_at": null,
    "date": "2026-10-11",
    "competition": {/* Competition */},
    "sides": [
        {
            "side": 1,
            "participant": {
                "kind": "team",
                "id": 13,
                "name": "Кристал Пэлас",
                "url": "/team/13/crystal-palace",
                "logo": "https://static.flashscore.com/res/image/data/GfkvlLmC-drybTCiH.png",
                "flag": null,
                "national": false,
                "code": "CRY",
                "members": []
            },
            "label": null,
            "provisional": false
        },
        {
            "side": 2,
            "participant": {/* … */},
            "label": null,
            "provisional": false
        }
    ],
    "entrants": null,
    "on_tv": true
}
```

| Field                 | Meaning                                                                                                                                                                                                                                                     |
| --------------------- | ----------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| `kind`                | `match`, `bout`, `tie`: two `sides`, `entrants: null`. `meeting` (GP weekend), `session` (practice, race), `race`, `tournament`: `sides: null`, `entrants` is a count.                                                                                      |
| `name`                | Session or race name («Race», «Practice 1»); `null` for matches: build the title from the sides.                                                                                                                                                            |
| `round`               | «Тур 6», «1/8 финала», or `null`.                                                                                                                                                                                                                           |
| `parent`              | `{id, name, url}` of the meeting a session belongs to.                                                                                                                                                                                                      |
| `status`              | `scheduled`, `live`, `interrupted`, `postponed`, `cancelled`, `unknown` (`finished` and `abandoned` are never listed). `status_label` is the Russian text. The status comes from the catalog and is refreshed on import: it is not computed from the clock. |
| `time_precision`      | `exact`: show the time of `starts_at`. Otherwise (`date`, `unknown`) only `date` is known: show the day and «время уточняется», never 00:00.                                                                                                                |
| `starts_at`           | UTC; `null` if only the date is known.                                                                                                                                                                                                                      |
| `date`                | Day of the event in the server zone.                                                                                                                                                                                                                        |
| `broadcast_starts_at` | Only with `tv=1`: when the first live broadcast of that day starts.                                                                                                                                                                                         |
| `on_tv`               | A live broadcast that has not ended yet exists: show a TV badge. Replays don't count.                                                                                                                                                                       |

**Sides.** `participant: null` means the opponent is not known yet; show
`label` («Победитель: Челси — Тоттенхэм», or «Уточняется»).
`provisional: true` means the name is known from a day list, but there is no
profile yet: show `label` without a link.

**Participant.**

| `kind`       | Shape                                                                                                                |
| ------------ | -------------------------------------------------------------------------------------------------------------------- |
| `team`       | `id` (team), `name`, `logo`, `national` (national team), `code` («CRY»), `members: []`.                              |
| `individual` | `id` (athlete), `name`, `flag`, `logo: null`, `members: [itself]`.                                                   |
| `pair`       | `id: null`, `name` («Харрисон К. / Скупски Н.»), `members`: two athletes `{id, name, url, flag}`, each its own link. |
| `relay`      | Like `pair`; `id`/`logo` of the team if the relay represents one.                                                    |

Tennis doubles are pairs of athletes, never teams.

### EventDetail

Event plus:

| Field        | Meaning                                                                                                             |
| ------------ | ------------------------------------------------------------------------------------------------------------------- |
| `venue`      | Circuit or stadium, or `null`.                                                                                      |
| `ends_at`    | UTC or `null` (unknown).                                                                                            |
| `start_list` | Non head-to-head only: `[{participant, affiliation}]` (driver and team «Red Bull»).                                 |
| `children`   | Sessions of a meeting (Event list).                                                                                 |
| `siblings`   | For a session: all sessions of its meeting, `[{id, name, status, url, current}]`; build a session switcher from it. |

### Team

```json
{
    "id": 13,
    "name": "Кристал Пэлас",
    "original_name": "Crystal Palace",
    "url": "/team/13/crystal-palace",
    "logo": "https://…",
    "national": false,
    "code": "CRY",
    "badges": ["Клуб"],
    "sport": { "key": "football", "name": "Футбол" },
    "country": { "name": "Англия", "flag": "https://…", "code": "england" }
}
```

`badges` tell national-team variants apart: «Сборная», «Женщины», «U21»,
«Олимпийская». France, France W and France U21 are different teams with
different ids.

### Athlete

```json
{
    "id": 133,
    "name": "Харрисон К.",
    "original_name": "Harrison C.",
    "url": "/player/133/harrison-c",
    "portrait": null,
    "country": {
        "name": "США",
        "flag": "https://flagcdn.com/w80/us.png",
        "code": "usa"
    },
    "sports": [{ "key": "tennis", "name": "Теннис" }]
}
```

`country` is the flag the athlete plays under, only if the source showed it.
`sports` may be `null` in lists.

### Broadcast

```json
{
    "id": 3753,
    "channel": "Sky Sport Premier League DE",
    "channel_number": 1106,
    "country": null,
    "language": null,
    "quality": null,
    "provider": "Dispatcharr",
    "epg_title": "Fußball: England, PL - Crystal Palace - Nottingham Forest, 6. Spieltag.",
    "logo": "http://iptv.home.internal/api/channels/logos/358/cache/",
    "starts_at": "2026-10-11T12:50:00+00:00",
    "ends_at": "2026-10-11T15:00:00+00:00",
    "live": true,
    "replay": false
}
```

- Only broadcasts that have not ended. Order: those overlapping the event
  first (live before others, preferred language `ru`, `en` first), then the
  rest by time.
- `live`: a live broadcast; `replay`: a replay after the event.
- Channels have no archive: a broadcast can be watched only while it is on
  air (`starts_at` ≤ now < `ends_at`). Show future ones as a time, not as a
  play button.
- `id` stays stable across guide refreshes, but a broadcast disappears when
  the guide drops it: handle `404` on `/stream`.

## Playback

- Streams are MPEG-TS over HTTP from Dispatcharr (`type: "mpegts"`). They
  arrive in bursts (about 6 s every 7 s): use a generous buffer and don't
  chase the live edge.
- **Android TV**: Media3 ExoPlayer plays progressive MPEG-TS
  (`ProgressiveMediaSource`).
- **Apple TV**: AVPlayer does not play raw MPEG-TS over HTTP (HLS only). Use a
  player that does (TVVLCKit / VLCKit, KSPlayer, or an FFmpeg-based one).
  Whether Dispatcharr can serve HLS for these channels has not been checked.
- The stream host (`DISPATCHARR_URL`, `iptv.home.internal`) is behind the
  `nas-admin-only` middleware in the homelab. Make sure the TV's address is
  allowed there before blaming the player.

## Refresh

| Data                        | Changes                     | Suggested client refresh         |
| --------------------------- | --------------------------- | -------------------------------- |
| Catalog (events, statuses)  | on `catalog:import` runs    | on screen open; home every 5 min |
| Broadcasts (`on_tv`, lists) | `guide:import` every 30 min | event screen: on open            |
| Stream URL                  | Xtream credentials (rarely) | request before every playback    |

There are no ETags or push updates yet.

## Legacy API

`/api/home`, `/api/sports`, `/api/events…` and `/api/channels…` (no `v1`)
belong to the old IPTV app (`SPORTS_MODE=iptv`, `App\Guide`). In the default
standalone mode they answer `410 Gone` with `{"message": "Use /api/v1
(catalog API)."}`. `/api/tokens` still works; tokens are shared with v1.
