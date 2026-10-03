# Private Conversation 0.1.13 — PRE-ALPHA candidate

This is a reliability release. It has no new features. Each fix comes from a code audit after 0.1.12 (`tasks/audit-2026-10-03.md`) and was rechecked against the code before it was changed. Scene direction does not require Mind Poisoning; optional opinion effects need Mind Poisoning 0.1.14 or later (whole-reply solo evaluation: 0.1.16).

## Fixes

- **No switch-off at the one-hour mark.**
  - Before: an active scene expired one hour after activation, however much it was used, and the next direction then went out as plain player speech, despite the README promise.
  - Now: each turn that keeps the scene active renews the hour (at most once a minute), so a scene ends after 60 minutes without use.
- **END recovers an unreadable state file.**
  - Before: a corrupt, schema-invalid or oversized `state.json` blocked every input, and END could not clear it.
  - Now: END renames the file to `state.json.corrupt-<time>-<id>` and starts clean (`state.store_recovered`, warning). ARM still refuses to overwrite a bad file. Symlinks and non-regular files are left alone.
- **Identity hiccups no longer block when no scene exists.** Input is blocked during an identity failure only when the stored file is unreadable or holds an active or pending scene.
- **Stuck scenes end themselves.** An active scene whose every request is refused for five minutes ends (`state.scope_ended`, reason `members_gone`). The page shows "The scene ended: its NPCs were gone for five minutes." An accepted turn resets the clock.
- **Read errors never drop members.**
  - Unreadable presence evidence is logged (`state.unavailable`, operation `presence_read`).
  - The turn is refused (`presence_check: presence_error`) instead of recording a permanent `left_scene` drop.
- **Reflections use routing's presence rule while reports are fresh.** A solo NPC who drifts slightly during a long reply keeps the reflection. During a heartbeat gap the check stays strict.
- **Logs.**
  - Refused scene requests are `routing.request_blocked` (warning) instead of info-level `routing.request_skipped`.
  - Reflection-scope read failures are `scope_unavailable`, not `identity_changed`.
  - A new test checks every literal log call against the event schema.

## Verification

- **Offline fixtures:** all 47 pass, including seven new test files, plus 11 of 11 browser-refresh cases.
- **Clone smoke run** (about 13 AI calls): pair, solo with Mind Poisoning evaluation, group and free scenes still behave as before. A refused direction is now logged as `routing.request_blocked` (warning).
- **Offline only:** the time- and fault-based fixes (one-hour renewal, corrupt-file recovery, identity hiccup, read errors, five-minute auto-end, reflection drift).
- **Not yet covered:** an in-game check with the real client.
- Details: `tasks/reliability-verification-2026-10-03.md`.

## Not changed

- The blocking state lock with no timeout: low risk, deferred.
- The cached `presence.json` write: deferred; its tests encode privacy checks.
- Behaviour while a queued scene's NPCs are not nearby: input is still refused, which is intended.

## Installation

Replace the old enabled PCV package; do not stack versions. CHIM-only server data; no ESP/ESL or Papyrus companion. See the [0.1.13 installation guide](https://github.com/Francisco-boop-001/CHIM-PrivateConversation/blob/private_conversation-v0.1.13/README.md).
