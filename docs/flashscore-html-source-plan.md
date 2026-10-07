# Plan: add a Flashscore lite HTML fixture source

Status: **implemented on 2026-10-07** (`FlashscoreSource`, disabled by default). See the evidence note at the end and `docs/operations.md`.

## Objective

Add a no-subscription schedule source using the publicly linked Flashscore lite site, `https://www.flashscore.mobi/`. Prioritize gaps in handball, volleyball, rugby union, futsal, bandy and water polo. Preserve existing sources and the rule that schedules create events while XMLTV only attaches broadcasts.

Implement and test the adapter, integration and documentation. Do not treat this plan as evidence that every sport, date or match is available. Do not replace existing work or introduce paid services.

## Evidence and limits

During this investigation on 2026-10-06:

- A normal HTTP GET of the lite homepage returned HTTP 200 and approximately 41 KB of HTML.
- A disposable Python HTML-parser prototype extracted 221 football match rows. This demonstrated structural extraction, not validated timestamps, completeness or production readiness.
- The list was inside `div#score-data`. Competition headings used `h4`; rows contained a time/progress `span`, opponent text, a `/match/{id}/` link, and a `br` separator. Observed match-link classes included `sched`, `live` and `fin`.
- Example observed structure: `<span>20:45</span>Croatia - Spain <a href="/match/GYG5XytT/" class="sched">&nbsp;-&nbsp;</a><br />`. This is a parsing example, not an authoritative fixture for a test date.
- Live rows sometimes replace kickoff with elapsed time such as `18'`. Original kickoff recovery remains unverified.
- The public handball and volleyball pages exposed fixtures through the research browser. The “More sports” page listed additional sports; the calendar showed dates up to seven days ahead. Raw HTTP access and date semantics must still be checked for those routes.
- No login, signed requests or browser automation was needed for the successful lite homepage fetch.
- Championat `/stat/` returned a 642-byte SberID JavaScript redirect page to a direct request. It is not the implementation target for this phase.
- The main Flashscore desktop homepage was accessible, but a usable schedule extractor for it was not demonstrated. Do not generalize the old documentation's signed-request claim to the lite HTML route.

Reference pages:

- [Lite homepage](https://www.flashscore.mobi/)
- [Handball](https://www.flashscore.mobi/handball/)
- [Volleyball](https://www.flashscore.mobi/volleyball/)
- [Additional sports](https://www.flashscore.mobi/others/)
- [Calendar](https://www.flashscore.mobi/calendar/)
- [Flashscore terms](https://www.flashscore.com/terms-of-use/)

Technical accessibility is separate from permission: Flashscore's published website terms restrict scraping/aggregation without consent, subject to legal exceptions. Record this accurately in documentation; do not describe personal use as a blanket exemption. Default the new source to disabled and leave activation to the project owner. Implementation and offline verification can proceed without enabling scheduled scraping.

## Current repository baseline — inspect before editing

The workspace already contains substantial uncommitted changes. Preserve them. The current code has advanced since the first discussion:

- `FixtureData` already supports nullable opponents and a `title` for events without opponents.
- ESPN configuration already includes tennis, UFC and F1; `IbuSource` is present for biathlon.
- `tests/Feature/FixtureSourcesTest.php` already tests these additions.
- Do not reimplement these features or base the work on the older claim that every event requires two teams.

Read the current `README.md`, `docs/architecture.md`, `docs/operations.md`, and applicable repository instructions. Use CodeGraph first if `.codegraph/` exists; otherwise inspect the code normally.

Relevant integration points:

| File | Purpose |
| --- | --- |
| `app/Guide/Fixtures/FixtureSource.php` | Source contract: UTC day to a list of `FixtureData` |
| `app/Guide/Fixtures/FixtureData.php` | Normalized data shape |
| `app/Guide/Fixtures/FixtureImporter.php` | Imports each enabled source/day and upserts fixtures |
| `app/Providers/AppServiceProvider.php` | `fixtureSources()` registration and importer construction |
| `config/sports.php` | Enable flag, sports mapping and source options |
| `app/Guide/Matches/MatchBuilder.php` | Source priority and deduplication |
| `app/Guide/Matches/AiringMatcher.php` | Matches guide programmes to events |
| `app/Guide/SportRegistry.php` | Sport keys and recognition |
| `app/Guide/Listings/TitleParser.php` | Guide-title parsing |
| `tests/Feature/FixtureSourcesTest.php` | Existing HTTP-faked source tests |

## 1. Verify the source contract with a small sample

Use a bounded number of ordinary public requests. Inspect real links rather than inventing date URL patterns.

1. Fetch handball and volleyball pages, their tomorrow/date links, and one match detail page linked from a list.
2. Verify actual raw HTML structure, match IDs, status labels and competition boundaries. Determine whether date pages explicitly identify their date or only use relative offsets.
3. Determine the source timezone from evidence, including DST behavior. Do not assume Moscow, UTC or a fixed European offset from the displayed clock.
4. Establish how to recover kickoff when a row displays live progress. Inspect normal match details for a full date/time. If unavailable, preserve an already known kickoff; skip previously unseen fixtures with unknown time and report the reason. Never manufacture kickoff from elapsed minutes or fetch time.
5. Establish valid empty-day markup and distinguish it from an error, challenge, redirect or changed layout.
6. Save small, sanitized HTML fixtures with capture URL, date and verified timezone notes. Do not commit cookies, credentials, unrelated advertising or whole-site dumps.

Deliver an evidence note in this document or a companion document. If a required field cannot be verified, implement the supported subset and document the limitation; do not silently guess.

## 2. Implement a bounded HTML adapter

Add `app/Guide/Fixtures/FlashscoreSource.php`, implementing `FixtureSource` with key `flashscore`. Extract a separate parser only if it makes fetching, parsing and tests clearer.

- Use Laravel's HTTP client and PHP DOM/XPath, or an HTML parser already installed. Avoid a browser runtime dependency for markup already available through HTTP.
- Parse only the verified schedule container. Walk headings and row boundaries; ignore standings links, adverts, navigation and icons. Do not parse an entire document with one regular expression.
- Keep match IDs stable and namespaced by sport, for example `handball:{match-id}`. Do not put date, score or status in identity.
- Normalize entities, non-breaking spaces and whitespace. Preserve accents, women's/youth markers and meaningful punctuation. Split opponents only on the verified separator; ambiguous rows should be skipped with a diagnostic.
- Normalize competition names conservatively through the existing registry. Add catalog aliases only where needed; do not expand the catalog speculatively.
- Map observed statuses to existing model constants. Handle postponed/cancelled/abandoned fixtures according to current project conventions. Explicitly handle unsupported states instead of treating all unknown labels as live.
- Parse only verified aggregate scores into integer score fields. Avoid confusing set details, extra-time labels or penalties with the primary score.
- Logos may remain null. They are not required for the first implementation.
- Validate discovered detail URLs against the expected public host and match-path format before fetching them.

Use the verified source timezone to convert to UTC. Since `fixtures($day)` means a UTC day, fetch any adjacent source-local date needed at midnight boundaries, then filter by the normalized UTC date. Cache shared pages so adjacent importer days do not repeat requests.

## 3. Scope and configuration

Start with handball and volleyball. Add rugby union, futsal, bandy and water polo only after verifying each sport's route and representative response. Use existing internal sport keys; discover external slugs from actual navigation.

Suggested settings under `sports.fixtures.sources.flashscore`:

- `enabled`: env name `FLASHSCORE_ENABLED`, default `false`.
- `sports`: explicit map of internal keys to verified routes/slugs.
- `days_ahead`: a verified supported horizon, bounded by the global importer horizon.
- Cache lifetime, timeout and request pacing as a small set of clear options where useful.

Do not enable football/hockey/basketball by default just because they are available. Existing providers cover those; this source's initial purpose is filling coverage gaps. Do not claim motorsport or winter-sport coverage from the lite site's general sports list. Leave tennis and combat extensions outside the initial scope unless needed and separately verified.

## 4. Import safety and integration

- Register the adapter in `AppServiceProvider::fixtureSources()` and wire its source-specific horizon through the existing importer mechanism.
- Give Flashscore an explicit lower priority than existing providers in `MatchBuilder`; inspect other source-priority maps before changing them. The new source should add missing fixtures without overwriting preferred API metadata.
- Allow the existing match deduplication to merge overlaps. Verify this with an integration test rather than adding a second independent matching system.
- Use finite timeouts, bounded retries and slow request pacing. Honor `Retry-After`; stop/back off on rate limiting. Cache validated pages and avoid fetching every detail page when the list has all required fields.
- Treat authentication/challenge pages, missing schedule structure and malformed responses as source failures. Never interpret them as a successful empty schedule.
- Isolate failure to the affected source/day and retain existing fixtures. If per-sport partial success needs additional handling, keep that change narrow and tested; do not discard successful sports because another sport failed.
- The current importer upserts rows; do not assume it deletes missing fixtures automatically. Inspect the actual importer/builder behavior. Do not add broad deletion or reconciliation as part of this adapter.
- Emit concise counts and diagnostics: fetched/cached pages, parsed fixtures, skipped unknown times, malformed rows and failed requests. Do not log full HTML responses or cookies.

## 5. Tests and acceptance criteria

Use captured, minimized HTML and `Http::fake`; prevent stray requests. Cover behavior rather than exact implementation details:

1. Scheduled handball and volleyball rows become correctly identified fixtures with UTC kickoff and competition.
2. Competition boundaries, HTML entities, icons and standings links do not corrupt opponents.
3. Live/finished/cancelled/postponed states and score variants behave according to documented mapping.
4. Unknown kickoff is not fabricated; verified detail-page recovery or preservation of known kickoff works.
5. UTC midnight and DST boundaries select the correct date and do not duplicate fixtures.
6. A valid empty day succeeds; a challenge page with HTTP 200, changed markup, 429 and timeout fail safely.
7. Repeat imports update the same fixture; overlapping API/HTML fixtures merge into one event and preserve preferred source metadata.
8. Disabling the source produces no requests; repeated page use respects caching and the configured horizon.
9. A representative parsed fixture can acquire an airing through existing matching, while an event without an airing stays hidden as before.

Run focused source/import/matching tests first, then the relevant project PHP checks (`vendor/bin/pint --test`, `vendor/bin/phpstan analyse`, and `php artisan test`). Follow current repository instructions if commands differ. Identify pre-existing failures separately; do not rewrite unrelated uncommitted work to make checks pass. Frontend checks are only needed if frontend code changes.

Acceptance: tested adapter and registration, disabled-by-default configuration, explicit verified sport/date coverage, no paid dependency, correct UTC handling, failure-safe imports, and honest documentation of remaining limits. Use a local/test database for verification; do not run a production import or change production settings as part of this handoff.

## 6. Documentation and final handoff

- Update the source tables in `README.md` and `docs/architecture.md` once implemented.
- Replace the blanket Flashscore exclusion with the precise distinction between its desktop site and verified lite HTML adapter. Retain the observed Championat limitation without claiming it is universally impossible.
- Document configuration, cache/rate-limit behavior, supported sports, date horizon, timezone evidence, missing-time handling and troubleshooting in `docs/operations.md`.
- Record terms/access considerations without claiming approval or a scraping license.
- In the implementation report, list changed files, checks and results, verified coverage, limitations, and the explicit configuration needed for owner-controlled activation.

Suggested instruction to Claude: “Implement docs/flashscore-html-source-plan.md against the current workspace. Preserve existing uncommitted changes. Verify the unresolved HTML/timezone contract before making assumptions, keep the source disabled by default, and complete the adapter, tests and documentation.”

## Evidence note — contract verified on 2026-10-07

About 25 ordinary GET requests, 2 s apart, through the homelab's normal exit (Estonian VPN IP). No cookies, no challenge pages, no browser runtime.

- **robots.txt** allows the sport list pages and disallows `/match/`, `/standings/`, `/draw/`. The adapter never fetches match detail pages.
- **Identification:** a plain `sports-app/1.0 (personal schedule importer)` User-Agent gets the same HTML as a browser UA, so no spoofing is needed.
- **Routes** (from the "More sports" navigation): `/handball/`, `/volleyball/`, `/rugby-union/`, `/futsal/`, `/bandy/`, `/water-polo/`. All of them use the same markup.
- **Dates:** `?d=-1…7` are relative to the site's "today". The `<h2>` heading says `Yesterday` / `Today` / `Tomorrow`, or the absolute date `13.10.2026` for `d ≥ 2`. `?d=8` returns a different, non-schedule page, so the horizon is 7 days ahead.
- **Timezone:** 27 football kickoffs on the lite homepage matched LiveScore/ESPN UTC kickoffs in the local database, all exactly **UTC+2**. The request left from Estonia (UTC+3 in summer), so times are not shown in the visitor's zone. This fits a fixed Central European zone (`Europe/Prague`, Livesport's home), with CEST in effect on that day. Rows dated after the switch to winter time on 2026-10-25 could not be observed yet. The zone is configurable (`FLASHSCORE_TIMEZONE`).
- **Rows:** `<span>HH:MM</span>Home - Away <a href="/match/{8 chars}/[?d=…]" class="sched|live|fin">score</a><br />`, grouped under `<h4>COUNTRY: Competition</h4>`. An `<h4>` may contain a standings `<a>`, which is ignored.
- **Statuses:** `sched` → upcoming. `live` → live: the kickoff `<span class="live">` is replaced by progress (`77'`). `fin` → finished. A nested `<span class="status canceled">Postponed|Abandoned</span>` → cancelled. A trailing ` WO` on the opponents with `fin` and no score is a walkover → cancelled.
- **Scores:** `fin`/`live` rows show the aggregate `H-A` (sets for volleyball). Unplayed rows show `&nbsp;-&nbsp;`.
- **Empty day:** `<div id="score-data"><div><span>No match found.</span></div>…`. A page without `#score-data`, without that marker and without any rows is a failure, never an empty day.
- **Unknown kickoff:** detail pages are off-limits, so a live row keeps the kickoff already stored for that fixture. A live row never seen before is skipped and counted, never given a made-up time.
