# Live issues, 2026-10-04 (first in-game session on 0.1.14)

Gaming distro `DwemerAI4Skyrim3` (D:), PCV 0.1.14, Mind Poisoning 0.1.17, SHARMAT 3.1.9.3. All findings were read look-only.

## Worked

- "end scene" on an armed, not yet active pair at 10:53:32Z: `state.scope_ended ended_in_game` and `routing.request_skipped ended_in_game`; no NPC reply.

- An active pair (Lidia and Bruce) ran several rechat turns at 11:13–11:27Z; every line's witnesses were exactly `|Lidia Sobieska|Bruce Wayne|`.
- "wrap up: they part ways for the night" at 11:30:08Z gave `state.scope_ended wrapped_up` and `routing.request_prepared wrap_up: true` (after a 27 s wait behind a rechat, with **no** interrupt). Then one parting line by Lidia "(talking to explicit_disable_rechat)", and no reply.
- A first attempt typed without the prefix ("they say goodnight") was correctly treated as an ordinary direction. The instructions given to the user were unclear: put literal in-game text in code blocks.

## L3 (small fix): the sentinel appears in the witness list

- Rowid 1010776 (the wrap-up line) has people `|Lidia Sobieska|Bruce Wayne|explicit_disable_rechat|`: CHIM adds the listener to the witness list.
- Probably the same for solo lines.
- Harmless for routing, but Mind Poisoning's overheard-gossip work (judging every witness) could treat it as a person. Either strip it in PCV (if a hook can still touch people or CACHE_PEOPLE at write time), or tell the MP agent to ignore it.

## L4 (small fix): in-game phrases are type-only

- Speech-to-text will not produce "wrap up:" with a colon.
- Accept "wrap up," and "wrap up." (and possibly "wrap up -") as well, keeping whole-input matching for "end scene".

## L5 (investigate): a solo reflection found no subjects

**Evidence:**
- Solo Lidia, armed 11:42:21Z; direction "Lidia thinks aloud about telling Bruce she was still Hawke's, \"mostly\", and what she meant by it." (rowid 1011044).
- About 9+ lines to `explicit_disable_rechat`; `reflection.output_registered` at 11:45:36Z.
- Then `reflection.evaluation_result evaluation_skipped` (model `not_called`, source_reason `reflection-no-subjects`) and `reflection.ack_skipped evaluation_rejected`.
- The reply names "Bruce" once (rowid 1011102, "I told Bruce…"); Hawke only as "his" / "him". Live Mind Poisoning is **0.1.17** (PCV was tested against 0.1.16).
- Lidia → Bruce stayed at 17; she has no relationship entry for Hawke.

**Check:**
- Which lines PCV registered: the whole reply under reply API v2, or a fallback to the final line.
- Whether MP 0.1.17 resolves a first name "Bruce" to Bruce Wayne.
- Whether "his"/"him" can ever resolve to the player.
- If it is MP-side, write a message for the MP agent.

## L6 (minor): early ACKs during a long solo reply logged as registration_missing

- 11 `reflection.ack_skipped registration_missing` (info) between 11:43:13 and 11:45:33Z while Lidia was still talking; registration landed at 11:45:36Z.
- The 0.1.10 in-flight marker should classify these as `reflection.ack_pending reply_in_progress` (debug).
- Check `PCV_SOLO_INFLIGHT_TTL` (180 s; this reply took about 3 min) and whether the marker was written for this request.

## L7 (small fix): "wrap up:" during solo became a reflection direction

- At 11:51:14Z, with solo Lidia active, the user typed "wrap up: end scene".
- G6 only applies to pair, group and free scenes, so the solo route took the whole line as its direction: stored "The Narrator: Wrap up: end scene" (rowid 1011201), and Lidia reflected on it.
- Fix: in solo, a "wrap up:" line should end the solo scene, either quietly like "end scene" or with one closing reflection line. It must never become a direction.
- Also accept the combined form "wrap up: end scene" as an end.

## L1 (FIX NEXT): CHIM cancels PCV's own scene reply when the request waited for the lock

**Evidence (10:55Z):**
- Pair Lidia (5124) and Bruce (2905); player and bystanders excluded. The direction "Lidia is drunk and flirty" was stored as an `instruction` (rowid 1009980, people `|Lidia Sobieska|Bruce Wayne|`), with a `user_input` row 1009981 whose data is `instruction`.
- A CHIM "bored" (idle banter) generation held the MAIN lock for 39 s. The PCV request waited about 25 s (`state.scope_activated` 10:55:25, `routing.request_prepared` 10:55:50).
- chim.log 12:55:50 (+02:00) shows `[USER_INPUT_INTERRUPT] Closing active instruction generation (phase=before_llm, request_ts=74928810609800, user_input_rowid=1009981, user_input_ts=74953692404700)`. The PCV request finished as `request_unobserved`; no scene reply.

**Cause:**
- CHIM `chimFindSupersedingUserInput` (lib/data_functions.php, about line 5850) cancels a generation when an `eventlog` `user_input` row has `ts > gameRequest[1]`.
- For direct player inputs (`inputtext`, `inputtext_s`, `ginputtext`, `ginputtext_s`, `narrator_inputtext`) it excludes `user_input` rows whose data is `'instruction'`.
- PCV rewrites `gameRequest[0]` to `instruction` (scope.php `pcvPrepareScopedInput`), so that exemption is lost.
- When the request waited for the lock, the input's own `user_input` row was logged later than the request timestamp, so CHIM took the scene's own input as a newer one and closed the generation before the LLM call (`call_llm_internal`, `$abortForSupersedingUserInput`, which captures `$gameRequest` at call time).
- The clone never hit this: no background events, so no lock wait.

**Fix direction (to design test-first):** keep PCV's own input from counting as superseding while still letting a genuinely newer player input interrupt. Candidates:
- (a) restore an input-type marker that CHIM's filter recognises, at a point after prompt composition and before `call_llm`;
- (b) align the request timestamp with this input's own `user_input` row.

Read CHIM's main.php order (when the `user_input` row is logged; what else reads `gameRequest[0]` and `[1]` after context) before choosing. Reproduce on the clone by holding the lock with a background event and then sending a scene input.

## L2 (USER DECISION): CHIM background narration pulls bystanders into an active scene

**Evidence:**
- Right after the direction, CHIM generated its own `instruction` events (rowids 1009984 and 1009986, "The Narrator: Solitude ,Hold: Haafingar)Bruce Wayne… turns his attention to Aelva…" and "…Lidia… turns to Aelva…"), with audience `|Bruce Wayne|Aelva|Lidia Sobieska|Hawke|` ("Scoped CACHE_PEOPLE for instruction from close range").
- Lidia then spoke to Aelva (rowids 1009994–1010016).
- CHIM's follow-up rechat skipped Aelva as inactive (`[RECHAT_SELECT] Skipping Aelva: inactive`), and its relationship worker failed to find "Aelva" (not in the catalog).

**Current PCV behaviour (by design since 0.1.x):** generated events that are not ordinary inputs or rechats keep their normal CHIM context; PCV does not veto them.

**Decision needed:** should PCV scope or suppress CHIM-generated instruction/background events while a scene with excluded bystanders is active (at least when the generated speaker is a member)? This is a behaviour change; ask the user before designing it.

**Workaround meanwhile:** give directions when no background banter is running, or turn CHIM's idle/"bored" banter off during scene tests.
