# Private Conversation 0.1.11 — PRE-ALPHA candidate

This candidate follows 0.1.10 and adds group scenes, requested on the CHIM Discord. Scene direction does not require Mind Poisoning; optional opinion effects need Mind Poisoning 0.1.14 or later (whole-reply solo evaluation: 0.1.16). The candidate remains PRE-ALPHA.

## Group scenes

- **Two to four NPCs.** NPC A and B are required; NPC C and D are optional. A pair is a group of two. Stored and logged scenes keep the `pair` mode name, with `actor_a`/`actor_b` mirroring the first two members plus an ordered `actor_ids` list and an `opener`.
- **Auto opener.**
  - The member named earliest in the direction opens (full name, or a first name unique among the members; whole words, case-insensitive).
  - Otherwise the **Who speaks first** picker decides, otherwise NPC A.
  - `routing.request_prepared` logs `opener_source` (`named`, `picker`, `first`).
- **Turns stay inside the members.**
  - Audience, witness list and rechat `active_agents` are exactly the members (plus the player when included).
  - The listener enum is every other member. PCV runs CHIM's tight rechat mode, so the addressed member answers next.
  - Outsiders are blocked as `speaker_outside_scene` / `rechat_speaker_outside_scene`; the old `*_outside_pair` reasons stay valid in existing logs.
- **Partial start.**
  - Checked NPCs that are not eligible at activation are left out (`not_eligible_at_start`), as long as at least two remain; otherwise the scene stays pending.
  - `state.scope_activated` logs `member_count` and `dropped_count`.
- **Mid-scene drop.** A member who fails the 0.1.10 in-scene presence checks is dropped (`state.scope_members_dropped`, `left_scene`) while two remain; otherwise the request is refused as before.
- **Page.**
  - Optional NPC C and D, and the opener picker.
  - Status: "Current group: …", "Started without X (not nearby)", "X left the scene".
- **Mind Poisoning.** No API change. The addressed listener is judged as before. Overheard gossip (judging every witness) is a Mind Poisoning feature request, and PCV needs no change to benefit.

## Other

- Two-member scenes armed from the new page also use the auto opener ("What does Bruce reply?" opens with Bruce).
- Solo reflection is unchanged.

## Verification

Offline fixtures: all pass, including six new group test files and a new browser-refresh case. A clone run of the group simulator cases 16–19 is recorded below before publishing.

## Limits

- At most four members; latecomers do not join a running scene.
- The opener rule reads names, not intent: a direction that mentions a member in passing may make them open.
- CHIM's rechat budget limits how many members speak per prompt.

## Installation

Replace the old enabled PCV package; do not stack versions. CHIM-only server data; no ESP/ESL or Papyrus companion. See the [0.1.11 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.11/README.md).
