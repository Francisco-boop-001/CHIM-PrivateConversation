# Maintenance review — 2026-10-01

Scope: delegated source maintenance in the isolated canonical checkout, branch `fix/scene-scope-and-diagnostics`, based on `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d`. Three gpt-6-luna Max owners used Ponytail FULL. The lead reviewed product/test diffs, callers, failure boundaries and recorded verification output; product edits remained with the owners.

## Findings and decisions

| Finding | Resolution and evidence |
| --- | --- |
| PCV-01: stored scenes applied to unrelated generation | Removed `context_pre.php`'s fallback that synthesized `generated_event`; removed the matching dead admission in `pcvPairRoutedRequest()`. Unstamped Standard events retain normal context without stored-scene identity/catalog/presence resolution. Managed ordinary inputs and rechat/continuation guards remain. |
| Stale scene escape | `pcv_stage()` now clears active and pending slots under its existing exclusive lock when disabled. END retains CSRF/identity checks, needs no selected actors or later input, and leaves corrupt state untouched rather than inventing a successful reset. ARM remains staged. |
| PCV-12: expensive unmatched-ACK diagnostics | Existing validated state read provides a negative hint. Missing, unreadable, off, pair, pending-only and expired solo state skip fresh identity/scope work. A positive hint cannot authorize a diagnostic or effect without fresh validation. |
| PCV-06: HTTP-readable state | Extension-root `.htaccess` denies mapped state paths using Apache authorization. It is outside schema-4 mutable `state/` and included by the common package allowlist. Isolated Apache proves denial; package checks prove inclusion in each distribution format. |
| PCV-11: own optional module failure | Both ACK and registration entrypoints catch reflection-module load errors and log bounded metadata. ACK errors do not claim a solo route before validation. No optional reflection load exists in `postrequest.php`. |
| PCV-11: colon in Player name | Excluded-player conversion rejects a colon-containing Player name with `invalid_input_prefix`, preserving the original input. Installed core strips only through the first colon; no invented attribution or core patch was added. |
| Local artifact ambiguity | Rejected unpublished CRLF build renamed and labeled, preserving its original hash. Historical 0.1.4 authoring tree marked superseded; source and critique retained. |

## Verification boundaries

Owner evidence: [routing review](routing-review.md), [ACK review](ack-review.md), [HTTP/package review](state-security-review.md).

Focused regression checks cover unrelated events, retained private safety, immediate END, rejected ambiguous input, cheap negative ACK cases, exact registered ACK after off/END, and optional-module parse errors. The registered-ACK-after-END fixture returns `scope_changed`, makes zero model calls, leaves the opinion unchanged and does not claim the record. Together with the immediate-END state fixture and the inspected fresh-state callback, this verifies the relevant source boundary. It does not promise cancellation of a transaction already committed, a request already generating, or audio already delivered.

The HTTP RED run served synthetic state JSON with 200. GREEN returned 200 for public manifest/CSS/JS, 403 for state directory/files/locks/temp/registry and case/encoded-character variants, and 404 for encoded slash. Apache 2.4.65 ran separately on an ephemeral loopback port with a temporary configuration and no live state. Four package checks passed, including exact denial-file bytes in DWPkg/tar and the byte-identical MO2 wrapper. These are isolated HTTP and packaging results, not an installed PHP UI or Skyrim test.

The scope fixture exits 0 with six PASS lines and a trailing `directory_unavailable` fallback. This was traced rather than ignored: its deliberate final logger-failure case leaves a deferred shutdown summary, and its earlier cleanup callback removes the isolated directory before that summary runs. The restored stderr sink then receives the teardown warning. No product logging error was reproduced by that teardown artifact.

Lead review returned three defects to their owners: a vacuous route assertion used an unresolved scene, an ACK module-load error claimed a solo route before validation, and a late END-only optimization emptied otherwise fresh picker options until polling. All were corrected. END still reads eligibility for rendering, but does not require fresh evidence to clear the state. The implementation reused `pcv_stage()` rather than adding a duplicate public lock/write API. A state-fixture logger failure was traced to its own insecure pre-created lock permissions; only that fixture setup changed, preserving the product logger's security check. Generated runtime `server/state/` is also ignored by Git; that prevents accidental commits and does not replace HTTP denial.

Protected-file comparison found all 52 captured shared Mind Poisoning product/test and pinned PCV fixture hashes unchanged. The shared checkout retained its prior dirty/untracked set. Manifest/version pins remain unchanged. No commit, tag, installation, live database/provider mutation, publication, or CHIM/core change was performed.

## Remaining limits

- **PCV-03 remains unresolved.** Installed CHIM `cf5030f15781637498be86debe26fcf102f5690d` constructs the exact utterance in `lib/chat_helper_functions.php`, flushes it, and only later invokes available output/extension hooks. No inspected extension callback exposes the exact utterance before flush. An early ACK may still be skipped; no recovery queue or false race-closure claim was introduced.
- Managed private requests still stop on stale/unknown eligibility. This preserves nearby AND CHIM-AI-active membership and prevents private direction being replayed as Player speech. END is the immediate escape. Unrelated generation is not globally muted; vanilla dialogue and Director remain outside the private-request guarantees.
- Claimed records can block another solo registration for up to 10 minutes. Shortening this blindly could retry an uncertain opinion write. Wire-separator subtitles remain rejected, and only the final native chunk is registered.
- Presence processing still performs authoritative identity/activity work. No unmeasured speed claim or cache weakening was introduced. Legacy companion commands remain accepted for compatibility; no companion is shipped or required.
- Log writes remain nonblocking and best-effort; contention can lose records. Indefinite blocking in CHIM's synchronous request path was not substituted for a diagnostics limitation.
- HTTP protection depends on Apache 2.4 `mod_authz_core` and effective overrides (`AllowOverride All` was verified). Other servers/configurations need an equivalent rule. This source is unreleased and the existing 0.1.6 pin does not authorize distributing changed bytes as published 0.1.6.

Judgment: accepted for source integration after owner gates and lead review. Remaining limits above are explicit; no runtime certification or release approval is implied. The project checklist records completion, and the final scoped whitespace/documentation check is recorded in the plan.
