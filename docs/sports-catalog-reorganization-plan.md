# Sports app reorganization: product, UX/UI, data and implementation plan

Updated: 7 October 2026. Status: ready for implementation handoff; UX/UI and data acquisition still require validation during implementation.

## 1. Objective and current state

Build a standalone multi-sport scores and fixtures application with navigation inspired by Flashscore. Users must be able to find a sport, country or category, competition, team or player, and understand the schedule and match details without knowing the database or provider terminology.

The current football page is not an acceptable final design: an unexplained list of matches does not provide a usable catalog. Reorganizing the database alone does not complete this project.

The existing research is in [flashscore-structure-research.md](flashscore-structure-research.md). It covers representative public pages, national-team variants, tennis doubles, race sessions, navigation patterns and HTML metadata evidence. It is not proof of complete automated coverage of every sport or field.

Already implemented: channel navigation, channel labels, playback buttons and broadcast sections are hidden. Existing web channel/watch/playlist routes are guarded and redirect to the schedule when their route bindings resolve. Legacy code, data and TV APIs are preserved. This interface change alone has NOT disabled all background IPTV work or decoupled the existing catalog from airings.

This document supersedes the earlier team-sports-only release scope and the earlier gap-filling provider strategy. **Tennis singles, doubles and player profiles are required before IPTV is reconsidered.** Team-only assumptions in the research document describe the older plan, not current scope.

## 2. Non-negotiable product decisions

- Sports data and navigation come first. Events never require a channel, broadcast or airing to be discoverable.
- Use Flashscore as the sole active sports provider for this work. Disable ESPN, KHL, Euroleague, IBU, LiveScore and TheSportsDB imports in the standalone profile. LiveScore and Flashscore are different providers.
- No paid data APIs. Verify an ordinary public-page acquisition method per sport and field; missing capabilities must be reported, not invented.
- Keep IPTV, channels, EPG, playback and broadcast matching absent from all new user flows. Disable their background work and dependency on Dispatcharr in standalone mode. Preserve old data for later reuse.
- Deliver desktop and mobile UX/UI, review it with the user, and address that review. **Do not integrate or re-enable IPTV until the user explicitly approves the sports UX/UI and separately authorizes IPTV work.** Completing a milestone is not approval.
- Build one multi-sport catalog with explicit event types. Teams, athletes, doubles entries and race entrants are not interchangeable.
- Retain original names, verified language variants, logos and source identities. Unknown fields stay unknown. Never generate fake provider IDs or merge entities by name alone.
- Preserve current uncommitted work and use additive migrations. No destructive database reset.

## 3. Coverage and completion criteria

“Popular sports” is a delivery scope, not a promise of every worldwide fixture. The working target below is explicit so coverage gaps cannot disappear behind a generic claim of support. Establish availability per sport, competition, date window and metadata field.

| Delivery wave                   | Required sports                                                                                                                                      | Event model and UI                                                                        |
| ------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------- |
| A — core journeys               | Football, basketball, ice hockey, tennis                                                                                                             | Team matches; tennis singles and doubles, player profiles, sets/tiebreaks                 |
| B — wider head-to-head coverage | Volleyball, handball, baseball, American football, rugby union, rugby league, cricket, table tennis, badminton, snooker, darts, boxing, MMA, esports | Period/set/frame/map scores; cricket innings; athlete bouts; distinct esports game titles |
| C — multi-entrant coverage      | Motorsport (including Formula 1 and MotoGP), golf, cycling, winter sports                                                                            | Series/tour, meeting or tournament, sessions/stages/rounds, entrants and rankings/results |

Waves sequence implementation; B/C are not permission to silently omit these sports from the final pre-IPTV product. Each sport needs a capability report and at least one representative end-to-end flow. If the source cannot supply necessary data, mark that coverage blocked/incomplete and present the limitation for a scope decision. Do not call the whole catalog complete or proceed to IPTV on that basis.

Every supported sport must have usable discovery, competition pages, schedule/results and the correct event-detail layout. Detailed play-by-play, betting odds, news, predictions, full squad histories and advanced statistics are outside this scope. Standings/draws/leaderboards are included where relevant and verified; missing ones get a clear unavailable state rather than fabricated data.

## 4. Information architecture

The general hierarchy is **Sport → browsing category → Competition → Edition → Match/event**.

| Sport family              | User journey                                                                              |
| ------------------------- | ----------------------------------------------------------------------------------------- |
| Domestic team sports      | Football → England → Premier League → Fixtures → Match                                    |
| International team sports | Football → Europe → Nations League → Match → France national team                         |
| Tennis                    | Tennis → ATP/WTA → Singles/Doubles → Tournament → Match → Player                          |
| Combat sports             | Boxing/MMA → Category/promotion when verified → Event/card when verified → Bout → Fighter |
| Motorsport                | Motorsport → Formula 1/MotoGP → Season/meeting → Session → Results                        |
| Golf                      | Golf → Tour → Tournament → Round/leaderboard → Player                                     |
| Winter sports             | Winter sports → Discipline → Competition/meeting → Race/relay                             |
| Esports                   | Esports → Game → Competition → Match → Squad                                              |

Country is a first-class entity, but not a forced parent for every sport. Tour, discipline and weight class are not countries. Competition category, represented country and event venue are separate facts.

England, Scotland, Wales and Northern Ireland may be sporting territories. Europe and World are regions. One competition can appear in several browsing sections without being duplicated. National-team profile breadcrumbs such as “Football → Europe” must not set the represented country to Europe.

National teams have a prominent discovery path: Sport → National teams → Country → Senior/women/youth/Olympic selection. Their fixtures still belong to their actual competitions. “National teams” is a directory/filter, not an invented league.

## 5. Required pages and behavior

### Application shell and home

Desktop: persistent sport navigation, searchable category/country panel, and clearly titled content. Mobile: sport selector and accessible category/filter drawer; preserve navigation state when it closes. Use the project's language conventions (currently Russian UI) and locale-aware dates.

Home emphasizes today's events, live scores, upcoming fixtures and sport discovery. “Live” describes the sporting event, not a broadcast. No television icons, watch actions, channel names or EPG copy. Main actions open the match/event page.

### Sport overview

Always show the selected sport name, selected date and active filters. Provide visible destinations for Competitions, Countries/categories, Teams/National teams or Players as appropriate. The schedule groups rows under labeled competition headers with country/category, competition name and edition where known. Show team/player names, score or kickoff, and status consistently.

Do not mix unlabeled live, completed and upcoming fixtures. Supply All / Live / Upcoming / Results controls with explicit date behavior. Show clear filter chips and Reset. The default is today's events; changing status retains the selected date. Offer an explicit next-fixtures action when the user wants future dates.

Catalog navigation remains available even when there are no matches today. Empty fixture lists must not erase countries or competitions.

### Country or category page

Show sport + country/category in the heading and breadcrumb. List competitions grouped sensibly (leagues/cups and men/women/youth where verified). Provide a separate route/tab to national teams for that country and sport. Country entries must not duplicate across sports.

For tennis show tour, gender and singles/doubles categories with actual tournaments, not a country selection that blocks access. Tournament location is secondary metadata.

### Competition page

Header: name, logo/fallback, sport, category/country and edition selector. Tabs: Overview, Fixtures, Results, and Standings or Draw when relevant. For multi-entrant events use Calendar/Results/Leaderboard terminology.

Group fixtures by day and round/stage. Explain qualification/main draw or league/group/playoff distinctions. A selected season scopes all tabs. If a season is unknown, show the available events without guessing a year. Missing standings or draws must be explicitly unavailable.

### Team and national-team pages

Header: verified name, crest/fallback, sport, represented/home territory, and badges for club/national, gender, age and Olympic/reserve designation. Show Upcoming, Results and Competitions. Link country and competitions. Keep men's, women's, youth and Olympic selections separate even if their names/logos match.

### Player pages

Required for tennis from wave A, and reusable for individual sports. Show name, optional portrait, verified represented nationality, sport, upcoming events and results. Display rankings only when observed with their date and ranking type. No fabricated age, biography or nationality. Search accepts verified aliases and localized names.

### Match/event detail

Shared header: sport/category/competition breadcrumb, edition/stage, date/time and timezone, status and participants. Relevant team/player names are links. Below: Summary and sport-specific score breakdown; related fixtures stay in the same competition context.

- Team match: two sides, score, periods/sets, known venue and round.
- Tennis: individual athletes or two athletes on each side; sets, games and tiebreak scores; retirement, walkover and suspension states where supplied. Do not label tennis sides home/away in the UI.
- Combat: two fighters, observed result/method/round when available; link card only when its identity is verified.
- Cricket: innings with runs/wickets and legal-ball counts, result text and match format. Overs are not decimal arithmetic.
- Motorsport/cycling/winter: meeting and session/stage selection, multiple entrants, positions, time/gap/points with units and meaningful source status.
- Golf: leaderboard and round scores, preserving ties, completion and cut/withdrawal status when available.

Unknown participants show “To be confirmed”; unknown time shows “Time TBD”, not midnight. A failed fetch is not an empty schedule, cancellation or final result.

### Global search and schedule

Search groups results by Sports, Competitions, Teams, National teams and Players. Each result has enough context to distinguish same-name entities: sport, country/category, gender and age where applicable. Search across verified translations/aliases without merging identities.

Schedule supports date/date range, sport, category/country, competition and status. Filters use URL parameters so refresh, share, back and forward retain context. Breadcrumbs and return links preserve the originating browse context; direct links still have a canonical breadcrumb. Avoid applying a stale competition filter after changing sport.

### UX quality requirements

- Desktop and mobile layouts at representative 1440px, 768px and 390px widths; no unintended horizontal page scrolling.
- Keyboard-accessible navigation and filters, visible focus, labeled controls and status conveyed through text as well as color.
- Consistent row columns, readable team/player names, restrained logos and reliable fallbacks. Prioritize scanning over large promotional cards.
- Explicit loading, no matches, no search results, unavailable data and source-error states.
- Show last successful update/stale data unobtrusively; never claim live freshness from an old import.
- Keep provider IDs, parsing diagnostics and internal schema terms out of ordinary user flows.

## 6. Normalized domain model

Use relational FKs and internal IDs. Source IDs are case-sensitive strings in explicit namespaces; provider IDs never become application primary keys. JSON is allowed for raw/staged observations, not as a substitute for queryable relationships or scores.

### Reference and identity tables

- `sports`: unique key, default name, active state and display order; `sport_translations` unique sport/locale.
- `sport_disciplines`: sport, stable key and name for genuine discipline/game distinctions; do not equate every navigation category with a discipline.
- `countries`: unique internal code, optional ISO codes, kind (country/territory/sporting territory), optional parent and flag; translations unique country/locale.
- `regions`: continental/global groupings with optional parent and translations. If a representative side spans countries, model a reviewed sporting territory and membership explicitly; do not invent ISO codes.
- `navigation_categories`: sport, optional parent, kind, stable key, name, optional country OR region FK. Country/region kinds require the matching reference; other kinds have neither. Parents share the sport and are acyclic.
- `competition_categories`: unique competition/category pair; validate sport scope. Separate browsing placement from geographic facts.
- `providers`, `assets`: provenance, validated image URL, optional cached asset, freshness/failure metadata. Distinguish logo, flag and portrait.
- Each entity with names has translations and aliases with locale, original name, normalized lookup value, source and curated override marker. Aliases are unique per entity/locale/name, never globally unique by name.
- Typed source mapping tables for teams, athletes, competitions, editions, stages and events: unique `(provider_id, external_scope, external_id)`, internal FK, URL and first/last seen. Preserve mappings through renames.

### Participants

- `teams`: sport, optional discipline, name, verified country/territory, club/national/unknown type, gender, age category, squad level, Olympic designation, optional parent team and logo, provisional/verified identity state.
- `athletes`: name, optional portrait and verified personal metadata. `athlete_sports` records sport/discipline participation. Represented nationality is a separate sourced association, with dates when known; do not infer citizenship or birthplace from a flag.
- `entries`: sport/discipline and kind (team/individual/pair/relay), optional team FK. Team entries reference a team; non-team entries use `entry_members` linking athletes with member order. Validate required cardinality at publication: singles one, tennis doubles two, relay according to discipline rules.
- Entries are participation units, not replacement identities. A changing doubles partnership is a different entry while the athletes retain their identities. Store explicit source entry IDs only when verified. A source without an entry ID can use a persisted internal resolution within the edition/event; never hash names into a fake provider ID.
- `edition_entries`: unique edition/entry membership; one team/player can participate in several competitions. Motorsport entrant/team affiliation belongs to that entry/edition rather than overwriting the athlete's identity.

### Competitions and events

- `competitions`: sport, optional discipline, name/logo, format, domestic/international scope, optional country/region, gender/age and entry format; explicit identity state. Recurring competition identity is separate from an edition.
- `competition_editions`: competition, stable internal key, display label and optional dates; unique competition/key. Verify source edition IDs against current/archive samples.
- `competition_stages`: edition, parent stage, kind and order; supports group, qualification, main draw, playoff and rounds.
- `catalog_events`: competition, optional edition/stage and parent event, event kind (match/tie/bout/meeting/session/race/round/stage), name when needed, known date, optional UTC start, source timezone, time precision, canonical status, raw status observation, result state, publication state and freshness.
- `event_entries`: event, entry, side/order, optional home/away role, seed and observed entry status. Head-to-head events have at most two sides; race/leaderboard events have many. TBD sides are unresolved slots/labels, not universal fake participants.
- A tennis national-team tie can contain singles/doubles child matches, and a meeting can contain sessions. Parent links must be acyclic, sport-consistent and semantically validated. Verify source parent identities before automatic linking.
- Never duplicate canonical country/sport/name fields on events. Preserve source labels only in observations. Keep old `fixtures`/`sport_events` until mapped and safely cut over.

### Sport-specific results

Share schedule and identity infrastructure, but use explicit typed result tables:

- `match_score_segments`: event, segment kind/order, entry/side, numeric value, completion and provenance. Supports totals, periods, sets, penalties, frames and maps; unique event/segment/entry.
- `tennis_set_scores`: event/set/side, games and nullable tiebreak points, set kind (regular/match tiebreak), completion. Current point labels (0/15/30/40/AD) belong to separate live state, not integer totals.
- `cricket_innings`: event, batting entry, innings order, runs, wickets, legal balls, declared/completed state and observed result text.
- `bout_results`: event, winner entry nullable, outcome, optional method, round and elapsed time.
- `classification_results`: event/entry, nullable position, tie marker, completion status, elapsed milliseconds, gap milliseconds and points as applicable. Unknown/DNF/DNS/disqualified are not zero times.
- `golf_round_scores`: event/round/entry, strokes, relative-to-par score, holes completed and status.
- Standings and draw edges use edition/stage/entry FKs with observation timestamps. Do not reconstruct official ranking rules from raw scores unless explicitly implemented and verified.

Finalize fields and invariants against captured samples for each wave before migrations. Do not use arbitrary EAV attributes to avoid modeling score semantics. Enforce simple invariants in the database and cross-entity/cardinality rules transactionally with tests. Athletes cannot appear twice on opposing sides; participants, stages and editions must match the sport/competition.

## 7. Flashscore acquisition and provenance

Existing lite parsing yields daily rows, not a complete rich catalog. Research confirmed basic team/competition header metadata in ordinary HTML, but did not confirm complete schedule rows in those responses. The provider exposes distinct template, tournament and stage IDs; verify their lifetimes rather than treating them as interchangeable.

Pipeline: fetch → validate response → stage observation → resolve identities → transactionally update catalog → record coverage and freshness.

Required infrastructure: import runs/pages, HTTP/validation diagnostics, bounded retries, cached valid responses, parser versions, source observations and explicit resolution links. Do not cache challenge/error pages as successful empty results. Preserve partial-page success and existing regression fixes.

Metadata discovery must be independent of today's fixture count. Support bounded category/competition discovery, edition fixtures/results, participant enrichment and locale observations. Reimports and concurrent runs must be idempotent through locks and unique source identities. Never clear known metadata because a lighter response omits it.

For every target sport report: discovered competitions, covered date window, stable event/participant IDs, actual acquisition method (raw HTML/rendered public page), country/category semantics, logos/locales, editions/stages, statuses, score details and unsupported fields. Verify access for selected routes; no challenge bypass, paid scraping service or assumed undocumented API contract.

Use provider IDs before names. Name similarity proposes a candidate, not an automatic merge. Persist provisional resolution links and auditable merges. Curated corrections survive imports. Missing pages cannot delete teams or cancel events. A locale fallback is not an official translation.

## 8. Delivery sequence and review gates

### A. Product and source proof

Inventory current code, data dependencies and uncommitted changes. Confirm route/source capability per event family. Deliver a coverage matrix and proposed schema, including team sports AND tennis. Preserve existing parser fixes.

### B. Navigable UX/UI prototype — before large-scale import work

Build the application shell and all page templates above using deterministic, clearly labeled demo data in a separate demo mode/database. Include football club and national-team flows, tennis singles/doubles/player flows, cricket scoring and a multi-entrant race. The prototype must support real navigation, filters, empty/error states and mobile layouts, not just static screenshots.

Use minimal implementation-independent demo fixtures; do not present invented data as imported Flashscore facts. Source validation can continue alongside design work. Show the user the prototype and collect UX feedback. Iterate before treating page structure as settled.

### C. Additive normalized schema and catalog services

Create reference, identity, athlete/entry, competition, event and typed-result tables. Use `catalog_*` names where old tables conflict. Leave historical migrations untouched. Implement one domain namespace such as `app/Sports`, with provider adapters separate from persistence and presentation. Web and JSON endpoints share catalog services and stable typed responses.

Index source identity tuples, normalized names, category membership, event dates/status/competition and entry-event membership. Provide staging-only/dry-run import mode and explicit bounds.

### D. Integrate real data in coverage waves

Complete wave A end-to-end, then B and C with appropriate score/detail adapters. Replace prototype data only after identity and metadata checks pass. Standings/draws/leaderboards must declare availability. Each wave includes UI verification, importer regressions and coverage reports; no “all sports” claim based solely on menu entries.

### E. Safe migration and standalone cutover

Back up SQLite consistently including WAL concerns. Produce legacy-to-new ID maps, reviewed counts, unresolved rows and a resumable migration. Legacy athletes become athlete candidates, not teams. Preserve original provider provenance; disabled APIs do not become fresh Flashscore observations.

Introduce the standalone catalog mode, disable all other sports importers and IPTV jobs, and remove airing-dependent queries. The app must boot/import/browse/search with zero channels and no Dispatcharr credentials or connectivity. Pause old writers during cutover. Map old event links where possible; keep obsolete data recoverable.

Document rollback to preserved tables/configuration and export/replay requirements for new-only data. Do not drop channels, airings or old event tables in this work.

### F. Review the completed sports product

Present the integrated desktop/mobile app, evidence for required journeys, data coverage gaps and known limitations. Address the user's UX/UI feedback. Obtain explicit acceptance of the sports experience. Source gaps remain visible and require an explicit scope decision if they prevent completion.

### G. IPTV — separate future project

Only after UX/UI acceptance AND a subsequent instruction to integrate IPTV, propose optional channel/broadcast associations attached to canonical events. They must never control discovery or fixture visibility. No automatic reactivation, playback placeholders or channel-first redesign during phases A–F.

## 9. Acceptance checklist

- A new user can explain what a football list contains from its heading, selected date, filters and competition groups.
- Find Football → England → Premier League → fixture → match → team, then return without losing context.
- Find France national teams and distinguish senior men, women, U21 and Olympic selections; region breadcrumbs never corrupt nationality.
- Find Tennis → ATP/WTA → tournament → singles/doubles match → player. Two athletes per doubles side remain individually clickable and correctly scored.
- Navigate competition fixtures/results/standings or draw with consistent edition and stage selection.
- Find a race session with many entrants; it never displays fictitious home/away teams.
- Each target sport has an appropriate discovery, schedule and detail flow plus documented data coverage, or an explicitly disclosed unresolved blocker.
- Search distinguishes teams, national teams and players with the same name; verified English/Russian variants resolve to one identity.
- Sport/category/date/status filters persist in URLs, refresh and browser navigation. Empty days retain catalog discovery.
- Desktop/mobile, keyboard, long names, missing logos, loading/error/empty/stale states are reviewed.
- Country, venue, browsing category and represented nationality remain distinct; cross-sport entries and cyclic hierarchies are rejected.
- IDs survive renames/rescheduling; repeated/concurrent imports do not duplicate entities; missing fields do not erase richer facts.
- Exact time, date-only, timezone/DST, zero/null score and sport-specific result tests pass. Status is never finalized by estimated duration alone.
- The standalone app works with no airings/channels or IPTV connectivity; disabled providers and IPTV jobs make zero requests.
- No channel links, playback controls, EPG copy or IPTV settings appear in the new interface.
- Migration/rollback is rehearsed on a copy; relevant PHP tests, static analysis, frontend type/lint checks and production build pass.
- The user reviews and accepts the integrated sports UX/UI before IPTV work can begin.

## 10. Copyable implementation handoff

> Implement `docs/sports-catalog-reorganization-plan.md`, using `docs/flashscore-structure-research.md` as evidence. This is a multi-sport product and UX/UI reorganization, not merely a database refactor. Start with a navigable desktop/mobile prototype and source capability checks. Include team sports, national-team variants, tennis singles/doubles and player profiles from the core wave; complete the wider sports waves and disclose acquisition gaps. Build normalized identities, entries, competitions, editions, events and sport-specific results through additive migrations. Make Flashscore the sole active sports source and fully decouple the catalog from broadcasts. Preserve existing work and legacy data. Keep channels/IPTV absent and inactive in standalone mode. Present the sports UX/UI for user review, address feedback, and do not integrate IPTV until the user explicitly accepts that experience and separately authorizes IPTV work. Report completed features, verified coverage, tests and remaining limitations at each milestone.
