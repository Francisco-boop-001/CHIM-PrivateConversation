# Private Conversation 0.1.14 — PRE-ALPHA candidate

This release adds roleplay tools and includes the 0.1.13 reliability fixes, which were never published on their own (see `distribution/private_conversation-v0.1.13.md`). Scene direction does not require Mind Poisoning; optional opinion effects need Mind Poisoning 0.1.14 or later (whole-reply solo evaluation: 0.1.16).

## Roleplay tools

- **"end scene" in game.**
  - The whole input "end scene" or "end the scene" (any case, trailing punctuation allowed) ends an active, queued or stuck scene, like END. It is checked before activation.
  - The input reaches no NPC (`routing.request_skipped`, `ended_in_game`; `state.scope_ended`, `ended_in_game`).
  - Without a scene it is ordinary speech.
- **"wrap up: <how>".** In a pair, group or free scene, the rest of the line is the closing direction.
  - The opener rules pick the speaker, who is told to give parting words. The listener choice is the native no-rechat sentinel.
  - The scene ends (`state.scope_ended`, `wrapped_up`), and CHIM's relationship queue is skipped for that request, as for solo.
- **Turn spreading.** In scenes of three or more, the listener choices are narrowed to members who have not spoken yet this round (tracked in `scene_turns.json`). A new round starts once everyone has spoken.
- **Scene card.** An optional page field of up to 300 single-line characters, stored with the scene. It is added to every turn's context as "Scene: …", including rechats and solo.
- **Turn length.** Short or Long guidance for pair, group and free scenes; Normal adds nothing; solo keeps its own limit.
- **Free-scene size.** 2–6, default 6.
- **Logs.** `routing.request_prepared` gains `scene_card`, `pace`, `wrap_up` and `sharmat_listener`.

## SHARMAT compatibility

Checked look-only against SHARMAT (`aiagent_nsfw`) 3.1.9.3 on the test clone.

- **Hook order.** CHIM runs extension hooks in alphabetical order, so SHARMAT's hooks run before PCV's.
- **Conditions are untouched.** SHARMAT adds each NPC's drunk stage (and other conditions) to that NPC's personality text in its context_pre. That runs after PCV picks the speaker, so the condition follows whoever actually speaks. PCV never writes that text, and all PCV guidance ends with "This adds to, and never replaces, your own condition and way of speaking."
- **Intimate scenes.** During SHARMAT NPC-to-NPC intimate scenes, SHARMAT pins who the speaker addresses.
  - PCV honours that pin when the partner is a scene member and the pin was computed for the NPC who speaks.
  - A pin computed before PCV switched the speaker is not honoured, and neither is a partner outside the scene.
  - When honoured, turn spreading and the wrap-up sentinel step aside for that turn.
- **Phrases.** SHARMAT does not handle "end scene" or "wrap up".
- **Drinking during scenes.** PCV scenes disable actions, so no new drinks are taken mid-scene. Existing drunk stages persist.

## Verification

See `tasks/roleplay-verification-2026-10-03.md`.

## Limits

- Turn length and turn spreading are guidance and listener constraints; models follow length only partly.
- CHIM's rechat pre-roll and budget still decide how many follow-up turns happen.
- Wrap-up is one parting reply, not a two-sided goodbye.
- SHARMAT intimate-scene interplay is verified offline only: no OStim scene was run on the clone.

## Installation

Replace the old enabled PCV package; do not stack versions. CHIM-only server data; no ESP/ESL or Papyrus companion. See the [0.1.14 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.14/README.md).
