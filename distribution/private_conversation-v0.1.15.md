# Private Conversation 0.1.15 — PRE-ALPHA candidate

Fixes from the first in-game session on 0.1.14 (evidence: `tasks/live-issues-2026-10-04.md`).

## Fixes

- **CHIM no longer cancels a scene's own reply (L1); bystanders stay out (L2).**
  - CHIM cancels a generation when it sees a newer `user_input` row. It exempts instruction-type rows only for direct player input, and PCV routes scene directions as instructions. So a background narration that arrived while the scene request waited for CHIM's lock cancelled the scene, and the narration itself pulled bystanders in.
  - Now, while a scene with **excluded** bystanders is active, PCV turns away CHIM `bored` and incoming `instruction` requests before CHIM logs them (`routing.request_skipped background_paused`). Background life resumes when the scene ends.
  - With **silent** bystanders, background narration keeps running, and PCV aligns the scene request's timestamp past background rows written while it waited.
- **Voice-friendly phrases (L4):** "Wrap up, …", "Wrap up." and "Wrap-up:", plus "End-scene.".
- **Solo wrap-up (L7):** "wrap up:" during solo ends the reflection instead of becoming its topic; "wrap up: end scene" is an end.
- **Early ACK classification (L6):** ACKs during a long reflection log as `reflection.ack_pending reply_in_progress`, even when an older registration is still stored.

## Verification

- 54 offline PHP fixtures and 12 browser-refresh cases pass.
- A clone check (about 4 AI calls) confirmed the background pause, an intact scene reply, the voice wrap-up, background resuming after the scene, and solo wrap-up ending the scene.
- The original live race was not reproducible on the clone (its background events finish instantly). The fix removes its trigger.

## Known, not fixed here

- **L5:** one live solo reflection was skipped by Mind Poisoning as having no subjects; this is being investigated.
- **L3:** CHIM lists the `explicit_disable_rechat` marker in a line's witness list; Mind Poisoning should ignore it.

## Installation

Replace the old enabled PCV package; do not stack versions. See the [0.1.15 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.15/README.md).
