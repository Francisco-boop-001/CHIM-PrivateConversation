# Reliability verification: 0.1.13, 2026-10-03

## Offline

- 47 default PHP fixtures pass, including seven new ones:
  - `active_ttl_renewal_check`
  - `state_recovery_check`
  - `scene_resilience_check`
  - `page_notes_check`
  - `reflection_presence_check`
  - `reflection_scope_error_check`
  - `log_reason_literals_check` (68 literal log calls checked)
- 11 of 11 browser-refresh cases pass.
- `package_check` passes.

## Clone smoke run

- The disposable clone `DwemerAI4Skyrim3-test` (E:) ran with the gaming distro stopped.
- The test package `dist/test-0.1.13/private_conversation-0.1.13.dwpkg` (sha256 `640e8878a6c8d19c3ba5f0b09e84cd647ea17eac64b71f10904c5361e346169c`) was installed through CHIM's package API.

| Case | Observed |
| --- | --- |
| 2 pair: opening and 2 rechats | Opening 4 lines, then rechat 0 lines (CHIM `Rechat: pre-roll determined 0 rounds`), then rechat 4 lines. Only Lidia spoke. Mind Poisoning judged Aela on Bruce (+0). |
| 5a solo positive | 11 lines registered (`reflection.output_registered`), evaluated and committed. Lidia on Aela +2, Lidia on Bruce -1. |
| 17 group rechats | Speakers and listeners were members only (Lidia, Bruce). The second rechat was silent (CHIM `pre-roll budget exhausted (1/1)`). |
| 20 free crowd | Armed and opened, then stopped by the 15-call budget. It was fully verified for 0.1.12 the same day. |
| 23 free too few | `state.scope_skipped scene_not_eligible`, then **`routing.request_blocked` (warning)** and a `routing.request_finished` blocked. This is the new E9a label, seen live. |

- About 13 real AI calls in total.
- Both silent rechats were CHIM's own pre-roll or budget decisions, made before any PCV hook ran.

## Not exercised live (covered offline only)

- The one-hour renewal (E1), which would need an hour of play.
- END recovery of a corrupt state file (E2).
- The identity-hiccup case (E3).
- Evidence errors (E4).
- The five-minute auto-end (E5).
- Reflection drift (E6).
