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
