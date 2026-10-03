# Private Conversation 0.1.10 — PRE-ALPHA candidate

This candidate follows 0.1.9 and fixes what the first in-game session found that the simulated client could not. Scene direction does not require Mind Poisoning. Optional solo opinion effects need Mind Poisoning 0.1.14 or later, and whole-reply evaluation needs Mind Poisoning 0.1.16. The candidate remains PRE-ALPHA.

## Fixes

- **Partners may wander mid-scene.** NPCs walk off between turns, and CHIM's close-range `infonpc_close` report then drops them, so 0.1.9 refused the partner's rechat (`scene_not_eligible`) and the scene went silent. Inside an **already active** scene a participant now still counts when any of these holds:
  - named in the current close report, including the first report after a long gap, which is stored as a baseline;
  - seen in the close report within the last 60 seconds;
  - named exactly once in CHIM's wider `infonpc` "beings in range" report.

  Activation is unchanged: every participant must be close and AI-active. Refusals log `presence_check` (`close`, `grace_expired`, `wide_absent`, `wide_unavailable`) and `missing_count`.
- **Solo reflection is addressed to no one.** The direction now says to think aloud, not to address anyone present (including the person being thought about), and to use the third person.
- **Logs page from the host browser.** Requests from the WSL host (the single private IPv4 default gateway in `/proc/net/route`) are admitted; forwarding headers and any other address are still refused. The locked page links to the `127.0.0.1` address.

## Diagnostics

- An unobserved finish records the request type. CHIM's rechat-budget stop (`main.php`, "pre-roll budget exhausted") is invisible to plugins; a silent rechat after it is expected.
- The plugin page shows the active scene's **last scene turn** (spoke / ended without speech), read from a small `last_turn.json` beside the log. It never contains dialogue.
- ACKs for early lines that arrive while a solo reply is still generating log `reflection.ack_pending` / `reply_in_progress` (debug) instead of `registration_missing`.

## Verification

- **Offline fixtures:** all 29 pass, including six new ones for the fixes above. The package and page-refresh checks also pass.
- **Clone run:** a disposable clone of a real CHIM server with a simulated game client, cases 12–15, 15 AI calls.
  - A partner absent from close reports was kept by grace, then by the wide report, and refused once absent from both (logged with `presence_check` and `missing_count`).
  - The first report after a 70 s heartbeat gap kept the scene.
  - An early-line ACK during generation no longer logged `registration_missing`, and the reply still registered and was evaluated.
  - A solo reflection with its subject present did not address him (one sample).
  - The Logs page opened through the WSL address that 0.1.9 refused.
- **Not yet covered:** an in-game check with the real client. That follows this release.

## Limits

- The wide report is CHIM's broader scan, not earshot.
- A participant can stay in a scene while walking away, for up to 60 seconds or while still listed in range.
- Model compliance with the self-addressed solo direction is not guaranteed.
- Group scenes and a player-only exclusion mode are planned, not included.

## Installation

Replace the old enabled PCV package; do not stack versions. This is CHIM-only server data and needs no ESP/ESL or Papyrus companion. See the [0.1.10 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.10/README.md) for asset formats and update steps.
