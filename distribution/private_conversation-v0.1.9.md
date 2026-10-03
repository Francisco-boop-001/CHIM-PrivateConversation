# Private Conversation 0.1.9 — PRE-ALPHA candidate

This candidate follows 0.1.8. It is the first PCV release tested against a live CHIM server: a disposable clone of a real CHIM server (`cf5030f`) and player database, driven by a simulated game client. Scene direction does not require Mind Poisoning. Optional solo opinion effects need Mind Poisoning 0.1.14 or later; whole-reply evaluation needs Mind Poisoning 0.1.16 (reply API v2). The candidate remains PRE-ALPHA.

## Fixes

- **Solo opinion effects never fired in 0.1.7–0.1.8.** The ACK precheck compared the ACK speaker's name with the stored catalog ID, so every solo ACK was dropped. ACKs now bind the scene by ID and the speaker by the live-resolved name; rejected reflection lines log their reason.
- **Eligibility flickered.** A read in the same second as a heartbeat dropped every NPC (about one check in ten). Same-second reads are now treated as fresh; the TTL is still enforced.
- **Busy places had no eligible NPCs.** 22.9% of 13,224 real heartbeats listed more than 33 names and were discarded. Up to 128 actors are accepted; duplicate names stay ambiguous; larger reports fail closed.
- **END could swallow the next reflection.** An unfinished registration from an ended scene held the slot for 60 seconds, silently losing the next solo scene's reflection. A registration from another scope now yields immediately; claimed effects stay protected.

## Additions

- **Whole-reply reflections.** With Mind Poisoning 0.1.16, PCV registers every line of a solo reply (up to 24), proven from the request's own `eventlog` rows. Unprovable grouping, aborted lines or over-cap replies fall back to the final line. ACKs of earlier lines are recognised and ignored quietly.
- **Shorter solo replies.** The solo direction asks for at most five sentences naming the subject. Models may ignore it.

## Removals and logging

- The legacy `ext_pcv_presence` companion command is no longer accepted.
- Routine presence successes are logged only when the roster changes; stale, baseline and failed observations are always logged.

## Live verification

Standard run (60-call AI budget, 38 used) with Mind Poisoning 0.1.16: baseline chat, pair scenes with rechat, pair with player, silent bystanders, solo positive/negative/neutral/short-name/first-line-only, player gossip, and four failure cases (abort mid-reply, duplicate final ACK, stale presence before ACK, END during reply). Effects landed on the correct owner and subject; every failure case produced no effect. Offline fixtures: 23 of 23 pass; the two disposable-PostgreSQL checks were not run for this candidate.

## Limits

The simulated client does not prove the native DLL, audio playback or gameplay feel. Models may ignore direction. Mind Poisoning skips a reflection with no new evidence since the last one (`reflection-basis-duplicate`) by design. Earlier 0.1.8 limits and upgrade conditions (private-state migration, access boundary) still apply.

## Installation

Replace the old enabled PCV package; do not stack versions. This is CHIM-only server data and needs no ESP/ESL or Papyrus companion. See the [0.1.9 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.9/README.md) for asset formats and update steps.
