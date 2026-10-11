# Flashscore catalog: verified capabilities and coverage

> **Scope change, 8 October 2026:** the app is now a fixtures catalog (like a
> ticket site). Only upcoming events are imported and shown; scores, sets,
> innings, bout results, race classifications and standings are no longer
> parsed or displayed. The field notes below remain valid evidence; the
> result-related ones are kept for reference only. Day lists now cover today
>
> - 7 days (future days cached 12 h); 15 competition pages per hourly run.

Status: 7 October 2026 — waves A, B and C imported for a bounded set of pages. Companion to
[sports-catalog-reorganization-plan.md](sports-catalog-reorganization-plan.md)
and [flashscore-structure-research.md](flashscore-structure-research.md).
Everything below was checked on real public pages; anything not listed is
**not** supported yet.

## Acquisition method

Ordinary public competition and team pages on `www.flashscore.com` (English)
and `www.flashscorekz.com` (Russian, `<html lang="ru-KZ">`). The pages embed
their fixtures and results as text (`cjs.initialFeeds['fixtures'|'results']`):
records split by `¬~`, fields by `¬`, key/value by `÷`. The importer reads that
text with string operations. It does **not** execute scripts, call the signed
`/x/feed/` service, fetch `/match/`, `/standings/` or `/draw/` pages, follow
redirects, or bypass any check. robots.txt (checked 2026-10-07) allows the
competition and team pages used.

This **corrects the research note** "complete schedules are not in raw HTML":
the rendered rows are missing, but the data behind them is in the HTML.

The site terms restrict scraping; activation is the owner's call
(`FLASHSCORE_ENABLED`). Requests are paced (3 s), cached (3 h, only validated
pages), retried once on 5xx and stopped on 429.

## Field semantics verified on samples

| Field                           | Meaning                                                            | Evidence                                                                                                                              |
| ------------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------- |
| `ZL`                            | competition URL `/sport/category/slug/`                            | every block; used as the category key and competition slug                                                                            |
| `ZEE` / `ZE` / `ZC`             | template (competition) / tournament (edition) / stage ids          | Premier League 2025/26 archive vs 2026/27: same `ZEE dYlOSQOD`, different `ZE` and `ZC`                                               |
| `ZB` / `ZY`                     | block header category id / name                                    | can differ from `ZL` (France W: "EUROPE: World Cup Women" at `/football/world/…`) — trusted only when it names the URL category       |
| `ZK`                            | parent competition name when the header has a stage suffix         | "UEFA Nations League - League A"                                                                                                      |
| `heading__info`                 | edition label ("2026/2027", "2026")                                | competition pages only; team pages have none                                                                                          |
| `AA`                            | event id                                                           | same id on the Russian host                                                                                                           |
| `AD`                            | start, Unix time (UTC)                                             | cross-checked with displayed kickoffs                                                                                                 |
| `AB`                            | 1 scheduled, 2 live, 3 finished                                    | 1/3 seen in samples; 2 not yet observed live                                                                                          |
| `AC`                            | detail code                                                        | **not decoded**: 8, 54 (tennis, unfinished sets), 10, 11 (hockey OT/SO pattern; football 11 on a 3–0 win). Stored raw in `raw_status` |
| `PX` / `PY`                     | home / away participant id, doubles `id/id`                        | `/team/{slug}/{id}/`, `/player/{slug}/{id}/`                                                                                          |
| `AE`/`AF`, `FH`/`FJ`, `FK`/`FL` | names: side / doubles members                                      |                                                                                                                                       |
| `CA`/`GA`, `CB`/`GB` (+`FU`…)   | athlete nationality id (+ name)                                    | same id space as country categories (198 England, 77 France)                                                                          |
| `AG` / `AH` / `AS`              | final score / winner side                                          |                                                                                                                                       |
| `BA`…`BJ`                       | period/set scores per side                                         | complete for hockey and tennis; football halves are **missing** in these feeds                                                        |
| `DA`…`DJ`                       | tennis tiebreak points per side                                    | Djokovic 7-6: `DA 7`, `DB 3`                                                                                                          |
| `OA` / `OB` / `OAJ`             | logo files → `https://static.flashscore.com/res/image/data/{file}` | URL seen in page HTML                                                                                                                 |
| `SA`                            | Flashscore sport id                                                | football 1, tennis 2, hockey 4, cricket 13, golf 23, motorsport 32                                                                    |

Period scores are stored only when every period is present and they add up to
the final score (hockey: three regulation periods when OT/SO decided it).
Russian names are attached by id from the Russian page and never create
entities. Name similarity is never used to merge entities.

## Wave A — imported (bounded list in `config/sports.php`)

Run on 7 October 2026: 15 pages × 2 languages, 30 requests, 0 failures after
the category fix; a repeat run from cache created no duplicates (1 807 events
↔ 1 807 source ids).

| Sport      | Pages                                                                                                               | Events | Window                  | Editions | Notes                                                                                       |
| ---------- | ------------------------------------------------------------------------------------------------------------------- | ------ | ----------------------- | -------- | ------------------------------------------------------------------------------------------- |
| Football   | Premier League, Russian Premier League, Champions League, Nations League, France / France W / France U21 team pages | 867    | 2022-06 … 2027-04       | 4        | halves not available; 126 team-page events have no edition (season not named on team pages) |
| Basketball | NBA, Euroleague                                                                                                     | 262    | 2026-09 … 2026-11       | 2        | quarters stored only when consistent                                                        |
| Ice hockey | NHL, KHL                                                                                                            | 432    | 2026-09 … 2026-11       | 2        | periods stored; OT/SO not labelled (code not decoded)                                       |
| Tennis     | ATP Shanghai singles/doubles, WTA Beijing singles/doubles (incl. qualification stages)                              | 246    | 2026-09-28 … 2026-10-10 | 4        | sets + tiebreaks; doubles as pairs; retirements not labelled (code not decoded)             |

Identity: 329 teams (all with logos), 326 athletes (all with an observed
nationality where Flashscore gave one — neutral athletes have none), 64
doubles pairs, 21 competitions, Russian names for every team.

## Discovery and scheduled runs (8 October 2026)

- **Registry** (`catalog_pages`): `catalog:discover` reads the public sitemap
  (`/sitemap.xml` → `/sitemap/core.xml`, 2 requests, weekly) — 17 015
  competition pages of the 22 catalog sports.
- **Today everywhere**: each planned `catalog:import` reads the lite day lists
  (`www.flashscore.mobi/{sport}/?d=0|1`, ~34 requests, cached 60 min). The lite
  match id **is the same** as the desktop event id (verified on NHL/KHL), so:
  known events get fresh status and score; new ones are created
  **provisionally** — sides are the names from the list (marked `unlisted`, not
  clickable, no team/athlete created), the competition is attached by its
  registry URL. When the competition page is imported, the same event and
  competition are adopted (no duplicates) and get real participants.
- **Budget**: `FLASHSCORE_PAGES_PER_RUN` (default 20) competition pages per
  hourly run — configured pages first, then probes, then competitions listed
  today/tomorrow, oldest import first; pages fresher than `cache_hours` are
  skipped; Russian names refreshed weekly. Roughly 34 + 20–40 requests per
  hourly run.
- **Probes**: a day-list header with no registry URL gets one candidate URL
  (shortest name) checked by importing it. First runs: 14 confirmed (Albanian
  Cup, Copa Chile, China League One/Two, Oberliga BW…), 11 returned 404 — those
  matches stay out (no invented competition) and are retried after a week.
- Esports has no lite day list (404) — esports "today" comes only from imported pages.
- Day lists collapse after 20 competitions; long competition/team lists page by 60/30.

## Waves B and C — imported 7 October 2026

32 more pages × 2 languages (60 requests, 0 failures). Totals now: 3 573
events, 594 teams, 1 153 athletes, 3 778 classification rows. All 22 sports
have data and are no longer marked «нет данных».

| Sport             | Pages                                                 | Events                    | What is stored (verified on samples)                                                                                                             |
| ----------------- | ----------------------------------------------------- | ------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------ |
| Volleyball        | SuperLega, Russian Superleague, European Championship | 319                       | set points, only when they explain the sets won (all 107 finished matches)                                                                       |
| Handball          | Bundesliga, EHF Champions League                      | 251                       | halves (sum = total)                                                                                                                             |
| Baseball          | MLB                                                   | 74                        | innings (`X` — unplayed half, stored empty), hits, errors                                                                                        |
| American football | NFL                                                   | 216                       | quarters (OT games keep the total only)                                                                                                          |
| Rugby union       | Top 14, Premiership                                   | 237                       | halves. **Russian names missing**: the Russian host returns 404 for rugby-union paths                                                            |
| Rugby league      | NRL, Super League                                     | 210                       | halves (golden-point games keep the total only)                                                                                                  |
| Cricket           | CPL, Big Bash League                                  | 79                        | first innings per side: runs, wickets, legal balls, batting order (`BY`), result sentence (English). Test-match second innings: **not verified** |
| Table tennis      | China Smash singles, doubles                          | 130                       | game points; doubles as pairs                                                                                                                    |
| Badminton         | Arctic Open men, women                                | 43                        | game points                                                                                                                                      |
| Snooker / darts   | Shanghai Masters / World Grand Prix                   | 23 / 31                   | frames / sets won (no per-frame scores in the data)                                                                                              |
| Boxing / MMA      | WBO heavyweight / PFL lightweight                     | 2 / 21                    | bout: winner, method (TKO, KO, points), round. Cards are not identified by the source — bouts are not grouped into cards                         |
| Esports           | ESL Pro League (CS), LoL Worlds                       | 73                        | maps won (no per-map scores in the data)                                                                                                         |
| Motorsport        | F1 Azerbaijan, F1 Singapore, MotoGP Mandalika         | 27 sessions in 3 meetings | meeting → sessions; position, time, gap, laps, team. Non-finishers keep their position without a time; DNF is **not labelled** (no field)        |
| Golf              | BMW PGA Championship                                  | 1 tournament, 144 players | positions with ties (`WW`), rounds, to-par against the course par, holes played                                                                  |
| Cycling           | Vuelta (overall + 21 stages), Il Lombardia            | 23                        | tour → stages; position, time, gap. Stage 3 (code `5`) stored as status unknown, not "cancelled"                                                 |
| Winter sports     | Biathlon sprint Östersund, alpine Lake Louise         | 2                         | **entrants only**: these pages carry no positions or times                                                                                       |

## Not yet covered — honest gaps

- **Provisional events** (from day lists) show names only until their
  competition page is imported; categories/competitions created from day
  lists keep English names until then. Some countries have no flag (name not
  mapped to ISO).
- **Standings and draws**: not in the page data.
- **Status details** (`AC`): retirement, walkover, OT/SO, extra time,
  penalties, cancellation — stored raw, not labelled.
- **Winter sports results**, **cricket multi-innings**, **per-frame/per-map
  scores**, **combat cards**, **motorsport DNF labels**: not in the verified data.
- **Rugby union Russian names** (Russian host 404).
- Live minute/period, lineups, statistics: out of scope.
