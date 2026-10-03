# Roleplay verification: 0.1.14, 2026-10-03

## Offline

- 53 default PHP fixtures pass, including the new ones:
  - `roleplay_settings_check`
  - `roleplay_ui_check`
  - `turn_plan_check`
  - `turn_hooks_check`
  - `in_game_commands_check`
  - `strict_rechat_check`
  - the seven 0.1.13 files
- 12 of 12 browser-refresh cases pass.

## Clone runs

- The disposable clone `DwemerAI4Skyrim3-test` (E:) ran with the gaming distro stopped.
- SHARMAT 3.1.9.3 and Mind Poisoning 0.1.16 were installed.
- The final test package sha256 is `ddfb6a1a828f767b004249a3a5f0d1c9b0e88f8a805f3888df28fbdecbe207e7`.

| Case | Observed |
| --- | --- |
| 5a solo (with SHARMAT) | Lidia still slurs (SHARMAT drunk stage intact under PCV guidance). Reflection registered and evaluated: Lidia on Aela +1. |
| 24 turn spreading, first try | Order was Lidia, Aela, Lidia. The round tracker was correct, but **CHIM's "Strict Rechat Targeting"** (`ENFORCE_STRICT_RECHAT_RESPONSE`, on in this config) overwrote the rechat listener with the previous speaker (chat_helper_functions.php:1756). |
| 24 after the fix | PCV lifts strict targeting for this request on spread rechats only. The order was **Lidia, Aela, Bruce: all three members spoke.** |
| 25 turn length | Short gave 2 lines and Long gave 6 lines (the sentence counts are inflated by the slurred "..."). The direction pointed the right way. |
| 26 wrap-up | One closing reply addressed to `explicit_disable_rechat`, no rechat, then `state.scope_ended wrapped_up`. The page showed Off. |
| 27 "end scene" | Input consumed (`state.scope_ended ended_in_game`, `routing.request_skipped ended_in_game`); no NPC reply; page Off. |
| 28 scene card alone (two samples, the second with stronger wording) | **0 of 5 lines** used the card's setting. CHIM's prompt composition confirms the card is delivered (nearby section about 480 characters against about 261 without a card). The model, with a long character profile and a drunk stage, did not pick it up. This is reported as delivered but weakly followed. |

- **CHIM silences:** one `pre-roll determined 0 rounds` (CHIM's random rechat dice).
- **AI calls:** about 32 across the 0.1.14 runs.

## SHARMAT compatibility

- **Live:** drunk-stage speech persists with PCV guidance present (case 5a; Lidia in every case).
- **Offline only:** the intimate-scene listener pin rules (`turn_plan_check`, `turn_hooks_check`). No OStim scene was run on the clone.

## Not exercised live

- A SHARMAT intimate scene inside a PCV scene.
- Free-scene size below 6 (offline only).
- The 0.1.13 time- and fault-based fixes (offline only).
