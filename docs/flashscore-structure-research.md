# Flashscore structure: evidence and catalog implications

Inspected: 7 October 2026. Research only; no importer, configuration or database changes.

## Scope and method

Inspected public desktop navigation, country and competition pages, national-team profiles, search results, tennis doubles and a Formula 1 race page. Also compared two ordinary HTTP HTML responses with the rendered website. This is a representative structural audit, not a complete crawl or a guarantee of coverage across every sport, locale or historical edition. Search-engine page extracts can be cached; rendered pages and the two HTTP captures provide stronger evidence for current extraction behavior.

The implementation plan is [sports-catalog-reorganization-plan.md](sports-catalog-reorganization-plan.md). The updated implementation plan requires team sports and tennis in the core wave and wider sports before IPTV review. References below to a team-only first release or future athlete support record the earlier scope and are superseded by that plan; the observed source evidence remains applicable.

## 1. Navigation is sport-specific

The general pattern is **sport → browsing category → competition → edition → events**. A browsing category is often a country, but not always.

| Area inspected                                                              | Observed organization                                                                                   | Consequence for our model                                               |
| --------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------- |
| [Football / England](https://www.flashscore.com/football/england/)          | Domestic leagues, cups, youth and women's competitions                                                  | Country browsing works; category variants are distinct competitions     |
| [Hockey](https://www.flashscore.com/hockey/)                                | Countries plus Asia, Europe and World; NHL under USA; World Championship, U20 and Olympics under World  | League category must not set each participant's nationality             |
| [Volleyball](https://www.flashscore.com/volleyball/)                        | Countries and regions; separate men's/women's international competitions                                | Team matches need aggregate sets and individual set scores              |
| [Handball](https://www.flashscore.com/handball/)                            | Domestic and regional competitions, including national-team and club competitions                       | International scope does not imply national-team-only participants      |
| [Tennis](https://www.flashscore.com/tennis/)                                | ATP/WTA, singles/doubles, Challenger, ITF, exhibition categories; separate rankings/calendars           | Tour, gender and entry format replace country as primary navigation     |
| [Golf](https://www.flashscore.com/golf/)                                    | Tours, tournaments, rankings and calendars                                                              | Tournament leaderboard and rounds; not two opposing teams               |
| [Motorsport / Formula 1](https://www.flashscore.com/auto-racing/formula-1/) | Motorsport groups auto/moto series and individual races                                                 | Separate series, race meeting, sessions and entrants                    |
| [Winter sports](https://www.flashscore.com/winter-sports/)                  | Alpine, cross-country, biathlon and ski jumping; discipline, gender, individual/team/relay distinctions | Umbrella navigation differs from sporting discipline and event format   |
| [MMA](https://www.flashscore.com/mma/)                                      | Weight classes and gender                                                                               | Fighters are athletes; country is not the primary category              |
| [Cricket](https://www.flashscore.com/cricket/)                              | Countries/regions plus bilateral series and Test/ODI/T20 categories                                     | Competition format and series matter even though participants are teams |
| [Esports](https://www.flashscore.com/esports/)                              | Counter-Strike, Dota 2 and League of Legends                                                            | Game title is an identity boundary for squads and competitions          |

The football sidebar links the same World Championship URL, `/football/world/world-championship/`, from more than one continental section. A sidebar placement is therefore not necessarily exclusive ownership. Preserve one competition with multiple navigation placements, rather than cloning it for each region.

Sport labels also differ between surfaces: the visible Football section uses `football` routes, while the inspected page metadata calls it `soccer`. Formula 1 is promoted in the top menu although its breadcrumb is Motorsport → Formula 1. Keep our canonical sport keys and explicit provider mappings; do not derive the domain model solely from top-menu labels.

## 2. National teams are independent identities

The following identities were observed through profiles or the site's search for France. Search-only discoveries still need profile validation before production ingestion.

| Team       | Sport/category       | Provider ID | Evidence                                                                 |
| ---------- | -------------------- | ----------- | ------------------------------------------------------------------------ |
| France     | Football, senior men | `QkGeVG1n`  | [Profile and raw HTML](https://www.flashscore.com/team/france/QkGeVG1n/) |
| France W   | Football, women      | `G0E4tMc9`  | [Profile](https://www.flashscore.com/team/france/G0E4tMc9/)              |
| France U21 | Football, youth      | `ShmZavVI`  | [Profile](https://www.flashscore.com/team/france/ShmZavVI/)              |
| France Ol. | Football, Olympic    | `txHbYtHJ`  | Site search result                                                       |
| France U19 | Football, youth      | `YL4McSPu`  | Site search result                                                       |
| France U20 | Football, youth      | `no6QnTnJ`  | Site search result                                                       |
| France     | Basketball           | `dS5MsX25`  | [Profile](https://www.flashscore.com/team/france/dS5MsX25/)              |
| France W   | Handball, women      | `O4dLT2gI`  | [Rendered profile](https://www.flashscore.com/team/france/O4dLT2gI/)     |
| France W   | Rugby                | `OO1bzV73`  | Site search result; discipline/profile confirmation pending              |

The football men's and handball women's profiles use **Sport → Europe** breadcrumbs. Europe is their source browsing category, not their represented country. A generic breadcrumb parser would produce incorrect team geography.

Store sport, represented territory, gender, age category and squad category separately. Olympic sides require an explicit designation: do not merge them into senior or U21 teams, or infer a universal age rule from the Olympic label. National teams need no artificial parent club. Link variants through their common territory and verified attributes; organizational parentage is optional and requires evidence.

The same `france` URL slug is reused for several identities. Slug, name, flag and country name are not unique identifiers. Use the opaque source ID plus a verified namespace, retaining case.

For wider cricket coverage, allow a future represented sporting area comprising multiple countries; never force every representative side to be a sovereign country. This audit did not verify a structured membership source for such areas.

## 3. Language and assets

The [English France profile](https://www.flashscore.com/team/france/QkGeVG1n/) and [Russian-language profile](https://www.flashscorekz.com/team/france/QkGeVG1n/) share `QkGeVG1n`; the latter displays Франция. This supports matching localized names by provider identity in this example. It does not prove that every regional domain shares every ID or supports every translation.

Store the actual source locale, hostname, URL and observation time. Do not guess language from domain suffix, classify all Cyrillic as Russian, or manufacture official aliases through machine translation.

The France profile contains an actual logo image URL in both rendered DOM and ordinary HTML. The Premier League profile also contains a competition logo. These are distinct from the site's generic Open Graph image. Extract the entity header image, keep provenance, and treat logos as optional assets whose URLs may change. A flag fallback must remain distinguishable from a verified logo.

## 4. Competitions, editions and stages have different IDs

The [Premier League page](https://www.flashscore.com/football/england/premier-league/) contains these values in the inspected HTTP response:

| Source field                                | Observed value | Interpretation                                                                  |
| ------------------------------------------- | -------------- | ------------------------------------------------------------------------------- |
| `pageinfo.tournament`                       | `dYlOSQOD`     | Matches the recurring template ID below and the site search result              |
| `leaguePageHeaderData.tournamentTemplateId` | `dYlOSQOD`     | Candidate recurring competition identity                                        |
| `leaguePageHeaderData.tournamentId`         | `SY30SsKF`     | Edition-related identity; verify against an archive sample before final mapping |
| `leaguePageHeaderData.tournamentStageId`    | `CfoA8Dmm`     | Stage identity; not interchangeable with competition ID                         |
| Header edition label                        | `2026/2027`    | Explicit source edition label                                                   |

Read these as data; never evaluate downloaded JavaScript. The field names and same-page evidence strongly support separate identity scopes, but one sample does not establish stability across years. A production proof must compare current and archived editions and multiple stages.

The [UEFA Nations League](https://www.flashscore.com/football/europe/uefa-nations-league/) rendered page exposes League A/B/C/D and groups. These belong below the recurring competition. Competition pages also expose separate summary/results/fixtures/archive views. A summary is not a complete match history.

## 5. Events and participants vary by sport

### Team matches

Rendered football match links expose participant IDs and a separate `mid` value. One observed link was `/match/football/croatia-K8aznggo/england-j9N9ZNFA/?mid=OKNATSve` while the displayed fixture put England first. **Do not infer home/away from URL component order.** Resolve sides from explicit row/detail structure.

`mid` is event identity; participant IDs identify entities. Keep these namespaces separate. Display dates and times also require source timezone context; a browser's local display is not proof of UTC.

### Tennis and doubles

The [Beijing doubles page](https://www.flashscore.com/tennis/atp-doubles/beijing/) displays two named athletes per side, aggregate sets, individual set results and a deciding match tiebreak. It distinguishes qualification from the main draw, and rounds from the tournament identity. Location and surface are separate from the ATP Doubles category.

Future design: athletes, event entries/sides and entry members. A temporary doubles partnership must not become an ordinary club team. National-team ties such as Davis Cup require a separate follow-up to verify tie → rubber → singles/doubles relationships; those detail relationships were not inspected here.

### Motorsport

The [Singapore Grand Prix](https://www.flashscore.com/auto-racing/formula-1/singapore-grand-prix/) rendered page shows practice, qualifying and race sessions, including qualifying subdivisions. Tables have many drivers with separate team names, time, laps, grid and pit fields. Venue/circuit information is also present.

Future design: series/season → meeting → session → entries/results. Driver identity and team affiliation are separate. Session labels should retain source wording until explicitly mapped. A race cannot be represented faithfully by nullable home/away teams and a two-number score.

### Other event families

Winter relay/team events need entry membership and discipline-specific results. Golf needs leaderboard/round results. MMA needs athlete bouts and eventually card/promotion relationships; this audit verified weight-class navigation, not a reliable card identity. Cricket needs innings/runs/wickets/balls and format-specific outcomes before claiming detailed score support. Those are proposed domain requirements, not claims that their complete metadata was extracted in this audit.

## 6. What an HTML parser can actually obtain

Two public pages were fetched once with ordinary HTTP, without browser rendering: France football and Premier League. Both returned their names, header logos and profile metadata. Neither response contained rendered `event__match` rows, although rendered browser pages showed schedules. The responses contained other match links, including promotional/footer links; counting those is not evidence of a complete schedule.

| Capability                                             | Evidence level                                                          | Remaining work                                                       |
| ------------------------------------------------------ | ----------------------------------------------------------------------- | -------------------------------------------------------------------- |
| Team ID, display name, logo                            | Confirmed in one raw team response                                      | More sports/categories, missing-logo cases, fixtures and tests       |
| Competition name, logo, edition label, identity fields | Confirmed in one raw competition response                               | Archive/stage identity comparison                                    |
| Country/region/category navigation                     | Seen in public pages                                                    | Explicit mapping; distinguish placement from factual geography       |
| National-side represented country                      | Human meaning clear in samples; breadcrumb insufficient                 | Verify explicit metadata or maintain reviewed mappings               |
| Localized team name linked by ID                       | Confirmed English/Russian example                                       | Locale/domain coverage and fallback tests                            |
| Complete team/competition schedules                    | Visible after rendering, not ordinary event-row HTML in the two samples | Choose and validate an acquisition method; no completeness claim yet |
| All historical records, rosters, live details          | Not established                                                         | Separate capability work if needed                                   |

Conclusion: **an HTML metadata parser is technically plausible and has concrete evidence. A full multi-sport ingestion system is not established by that evidence.** Keep the existing lite match-list parser and a new metadata adapter separate, joining only through verified IDs. Names-only observations remain provisional when no safe join exists. Browser rendering is a possible further experiment, not an implemented or proven bulk solution.

Do not fetch undocumented signed services merely because URLs appear in page scripts. Recheck access requirements and source restrictions for the exact routes selected. The [desktop robots file](https://www.flashscore.com/robots.txt) and lite-host rules are separate; the prior source plan records the applicable access considerations. This audit did not bypass challenges or introduce an automated crawler.

## 7. Required changes to the proposed catalog plan

1. Keep Football → England → Premier League as the primary user flow.
2. Add sport-scoped navigation categories with explicit kinds and a competition/category join. Geography remains its own reference data.
3. Preserve national-team variants, including Olympic designation, with independent source IDs.
4. Separate source category, represented territory and event venue. Never fill one automatically from another.
5. Verify recurring competition/edition/stage identity against archives before migration.
6. Retain the first release's team-match scope. Add other sports by event family after capability tests; do not label unimplemented sports as supported.
7. Keep a roadmap for athletes, entry members, meetings/sessions and typed results instead of putting all participants into `teams`.

## 8. Concrete implementation proof before catalog cutover

Use a small, bounded sample: England/Premier League; France senior, women, U21 and Olympic football; France basketball and women's handball; one international competition; and English/Russian names for the same verified ID.

Capture minimal parser fixtures, not entire megabyte pages with ads. Tests should verify exact identity namespaces, missing fields, distinct variants, source locale, category/geography separation, and idempotent reimport. Compare a current and archived competition edition. Then demonstrate two fixtures linked to verified teams and a competition without IPTV or another API.

Keep tennis doubles and Formula 1 as schema boundary examples: reject them from a team-only importer with a clear unsupported-format reason rather than creating fake teams. No runtime tests were run for this documentation-only research.
