# Broadcast coverage audit — 11 October 2026

Inspected the configured live Dispatcharr Xtream API and M3U, all 16 cached XMLTV sources, the local catalog database, import/matching code, and catalog UI. Findings were presented before fixes began.

## Measured baseline and verification

| Metric                                    | Before | Verified rebuild |
| ----------------------------------------- | -----: | ---------------: |
| Dispatcharr channels imported             |    269 |              269 |
| Channels with usable guide mappings       |    107 |              111 |
| Matched broadcast rows                    |    351 |              381 |
| Distinct events with broadcasts           |    167 |              176 |
| Distinct channels carrying matched events |     42 |               45 |

These are the local snapshot and isolated rebuild totals, including recent past broadcasts. They are not counts of simultaneously live streams. Expiring broadcasts make current/future totals time-dependent.

Dispatcharr returned all 269 channels and 269 M3U TV-guide IDs. No channel import cap caused the missing broadcasts. At baseline, 140 channels had no guide candidate and 22 had candidates without a usable schedule. Before duration filtering, 7,094 programmes yielded 2,520 parsed sports candidates and 348 matches in the diagnostic run. The importer applies additional duration checks; these diagnostic counts are not the same as persisted row totals.

## Confirmed defects fixed

1. **Accented channel names did not match.** Latin diacritics are now folded while country restrictions remain. CT Sport now maps to ČT sport; Spiler 1/2 map to Spíler1/2.
2. **Fox Sports 1 had a usable but differently named guide.** Added an explicit mapping to the inspected US guide ID `FS1.Fox.Sports.1.HD.us2`.
3. **XMLTV traversal skipped adjacent programmes.** Calling `next()` and then `read()` skipped the following programme in compact XML when the preceding channel was unwanted. Removed the double advancement; a compact XML regression reproduces the defect.
4. **[Reverted by the owner's decision, 11 Oct 2026: finished events stay hidden, including their repeats.]** **Repeats disappeared after a match finished.** The TV schedule now includes published finished events with unexpired broadcasts and selects the day using broadcast overlap. Event/watch pages accept these events too. The ordinary sports schedule still excludes finished events. There were 49 unexpired broadcast rows attached to finished events during the initial inspection.
5. **Broadcast timing was confused with match timing.** TV rows now display and sort by the first remaining broadcast in the selected day. “Next day” in TV mode searches broadcast dates. A tomorrow repeat no longer makes an event appear in today's TV schedule after today's airing ends.
6. **Refreshes broke watch links.** Broadcast rows are updated by channel and programme start, preserving IDs. Obsolete rows are deleted only inside the refreshed interval and channel set. Both catalog and legacy storage follow this rule.
7. **Partial guide outages erased valid broadcasts.** Channels belonging to an unavailable configured source retain their mapping and airings while healthy sources refresh. Guide loading now precedes channel mutation, so a total guide failure aborts earlier.
8. **Explicit repeats were discarded or mislabeled live.** Full-match replay titles are accepted, explicit replay flags prevent live classification, and unnamed repeat slots cannot attach to a new kickoff. Ambiguous national-team titles remain unmatched.
9. **Available broadcasts were buried on the homepage.** Added “Сегодня по ТВ” with an event count, a preview, and a link to the complete TV schedule. Finished events with broadcasts have a repeat label.

## Remaining source coverage limits

158 channels still lack a usable guide mapping after the rebuild. This is not equivalent to 158 missing live matches: some are archive, studio, FAST, or niche-sport channels. The inspected sources include empty and placeholder schedules; for example, Teledeporte's candidate contained repeated identical titles and failed the existing real-schedule check.

The standalone catalog uses Flashscore; LiveScore belongs to the legacy fixture pipeline. Futsal, bandy, and water polo are parsed by the older guide layer but are not registered sports in the standalone catalog. Their TV programmes therefore cannot attach to catalog events. These are coverage extensions, not matches that can safely be recovered by weakening similarity thresholds.

Generic tennis tournament slots, ambiguous national-team titles without a sport, old archive matches outside the replay window, and programmes with no corresponding catalog event remain unmatched. Matching every sports channel to an event would create false broadcasts.

## Verification

- 340 PHP tests pass, including new regressions for the defects above.
- PHPStan passes with zero errors; Pint passes.
- TypeScript type checking and changed-frontend formatting/lint checks pass. The repository-wide `npm run check` reports pre-existing formatting issues in 31 unchanged documentation/fixture files; these are outside the broadcast changes.
- Production frontend build succeeds.
- Live Dispatcharr + existing TV-guide rebuild tested first against a separate database copy, then run against the local app. All 351 existing channel/start broadcast rows retained their IDs.
- Browser verification: the homepage shows the TV section and links to the filtered schedule; the finished Arsenal–Leeds event opens and lists its upcoming repeats.
- Original database snapshot retained at `/tmp/sports-broadcast-before.sqlite` for this session.
