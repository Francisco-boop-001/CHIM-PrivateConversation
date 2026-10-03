# Private Conversation 0.1.12 — PRE-ALPHA candidate

This candidate follows 0.1.11. It adds free scenes, requested on the CHIM Discord as a mode that "will just exclude
player presence". Scene direction does not require Mind Poisoning; optional opinion effects need Mind Poisoning 0.1.14
or later (whole-reply solo evaluation: 0.1.16). The candidate remains PRE-ALPHA.

## Free scenes

- **Free scene checkbox.**
  - No pickers: NPC A–D and the opener picker are disabled while it is ticked.
  - The player is always excluded.
  - ARM needs at least two eligible NPCs in the current roster.
- **Members.**
  - On the next eligible ordinary input, the nearest six NPCs that are strictly eligible (close report and fresh AI activity, as for any activation) and present in the input's routing snapshot become the members, nearest first.
  - Ties are broken by catalog ID.
  - With fewer than two, the scene stays pending (`scene_not_eligible`).
- **Stored shape.**
  - A pending free scene stores `free: true` without members.
  - Activation turns it into an ordinary group (`actor_ids` of up to six, `opener: auto`, `free: true`).
  - Hand-picked groups stay capped at four.
- **Opener.**
  - The member named earliest in the direction opens.
  - Otherwise the NPC the player faces opens, if a member. That NPC is the routing snapshot's `listener` with `target_mode: direct`, the field CHIM itself decodes.
  - Otherwise the nearest member opens.
  - `opener_source` gains `target` and `nearest`.
- **During the scene.** The 0.1.11 group rules apply unchanged:
  - the addressed member answers through CHIM tight rechat;
  - only members speak;
  - members who leave are dropped while two remain;
  - each line's witness list is exactly the members.
- **Logs.** `free_scene: true` on `state.scope_staged`, `state.scope_activated` and `routing.request_prepared`.
- **Page.** "Current free scene: …" when active; "Next: free scene (nearest six)." while pending.

## Verification

- **Offline fixtures:** all 40 pass, including four new free mode test files, plus 11 of 11 browser-refresh cases.
- **Clone run:** a crowd of nine on a disposable clone, 11 AI calls.
  - The nearest six became the members.
  - Target, named and nearest openers each opened as expected.
  - The addressed member answered the rechat.
  - Witness lists held exactly the six members.
  - With too few nearby, the scene stayed pending.
  - Details: `tasks/free-mode-verification-2026-10-03.md`.
- **Not yet covered:** an in-game check with the real client.

## Limits

- At most six members, fixed at activation; latecomers do not join.
- "Nearest" uses the distances in the input that starts the scene, not a live measurement.
- The opener rule reads names, not intent. A direction that mentions a member in passing may make them open.
- CHIM's rechat budget limits how many members speak per prompt.

## Installation

Replace the old enabled PCV package; do not stack versions. CHIM-only server data; no ESP/ESL or Papyrus companion. See the [0.1.12 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.12/README.md).
