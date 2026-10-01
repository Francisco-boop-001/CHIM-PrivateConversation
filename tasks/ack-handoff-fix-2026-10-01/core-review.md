# Early-ACK recovery core review

## Result

The core implementation adds a bounded recovery path for a valid solo _speech ACK received before source registration. If source registration arrives first, it checks the receipt ledger and reconciles. If the ACK arrives first, its caught PHP shutdown callback retries reconciliation after the core has processed the request. Both modeled orders converge only when the registered source ID and the actual persisted public.speech row match the captured ACK tuple; no output text or newest-row heuristic creates an ACK. Full CHIM callback/bootstrap execution remains a separate proof gate.

The receipt store has an eight-entry and 8 KiB ceiling and a 45-second pending lifetime. It stores only the utterance ID, PCV key/config/actor, captured ACK interaction generation, timestamp, and tuple digest; it does not store dialogue. Expired or invalidated receipts may be pruned, fresh unmatched candidates are never evicted, and overflow is explicit. A known registered direct ACK uses transient captured tuple metadata and does not depend on free receipt capacity. After claim, the effect registry's 600-second lifetime and fresh scope/epoch revalidation govern completion; the 45-second pending lookup expiry is not a provider-duration limit.

The native reader uses a parameterized ID lookup with LIMIT 2, bounded returned columns, preserved row cardinality, and explicit missing/ambiguous/invalid/unavailable results. Recovery still requires exact emitted registration, speaker, player-transport listener, normalized tuple digest/subtitle, current playthrough/config, and the active scope. New version-2 PCV envelopes retain the existing Mind Poisoning registration tuple unchanged. Version-1 records remain readable and usable through the existing direct ACK gate, but cannot be recovered from a receipt because they lack a source epoch.

The first registry GREEN run below exposed a logger warning on its `registration_busy` fixture. That captured run predates the final logger fix and is retained as diagnostic history; the post-fix registry output and logger/package gates are recorded in the follow-up section at the end. The cause was a reason present in the reflection event-specific rule but missing from the shared `pcv_log_reason_codes()` allowlist.

For new version-2 records and recovered ACKs, source and ACK epochs are checked against fresh enabled interaction state at initial evaluation, MP pre_model, and transaction revalidation. The captured ACK generation remains distinct from source generation; the G4 ACK / G5 source-and-current fixture rejects before provider or opinion persistence. Existing absent-header behavior continues through chimInteractionBegin() and the current interaction snapshot semantics. Version-1 records preserve the old direct-ACK gate semantics; they do not carry a source epoch and are therefore not eligible for recovery.

## RED evidence

The initial smallest behavioral reproduction delivered a fixture ACK before registering the exact source output. It expected one evaluator invocation and observed zero (expected 1, actual 0), reproducing the lost early ACK. The parent reviewed that RED result and confirmed the label is fixture received ACK; it does not prove native SQL row persistence. The original full console text was not retained in this report, so no additional raw lines are reconstructed here.

## GREEN verification

Commands were run in the existing DwemerAI4Skyrim3 WSL PHP runtime. All fixtures use isolated temporary state; no installed CHIM files, live database, provider, or game were changed.

Command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php

Exit status: 0.

Raw stdout:

    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"8601a2e45ef12434bb737402","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"8601a2e45ef12434bb737402","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.4,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"8601a2e45ef12434bb737402","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.4,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":2.5}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"5e1f2a93416fad30c7649748","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"5e1f2a93416fad30c7649748","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"5e1f2a93416fad30c7649748","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":1.9}
    Private Conversation logger: invalid_event request_id=35e57533-7a30-439c-858c-b2cee0592af9 config_id=123e4567-e89b-42d3-a456-426614174000
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"15bac8463607143a4f91d258","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"15bac8463607143a4f91d258","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"15bac8463607143a4f91d258","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":1.8}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"4099175aae68824121cff5be","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"4099175aae68824121cff5be","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"stale","persistence_reason":"reflection-registration-stale","persistence_ms":0.2,"commit_state":"not_attempted","committed":false,"changes":[],"changed_count":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"4099175aae68824121cff5be","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"stale","persistence_reason":"reflection-registration-stale","persistence_ms":0.2,"commit_state":"not_attempted","committed":false,"changes":[],"changed_count":0,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"stale","outcome":"skipped","elapsed_ms":1.7}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"eb523a181d9366a7e6c31f4f","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0.1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"eb523a181d9366a7e6c31f4f","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:06:01Z","request_id":"eb523a181d9366a7e6c31f4f","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.3,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0.1,"reason":"committed","outcome":"committed","elapsed_ms":1.8}
    PCV reflection registry checks passed.

The fixture verifies both ACK-before-registration and registration-before-ACK; it uses the real MP evaluator fixture with a stub provider/store. The multi-chunk case leaves a prior nonfinal sentence receipt present, then reconciles only the final registered ID. It also checks duplicate/conflicting receipts, count/byte/expiry bounds, unresolved registration busy, G4 ACK versus G5 source/current, both revalidation phases, END clearing active state, one claim, consumed duplicate/no retry, a subsequent distinct output, and private logs without raw dialogue/hash/token. The simulated provider seam ages pending lookup metadata during evaluation; it does not measure a 45-second provider call.

Command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_hook_timing_check.php

Exit status: 0. Raw stdout:

    PCV reflection hook timing and API compatibility checks passed.

Command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_direct_ack_capacity_check.php

Exit status: 0. Raw stdout:

    PCV direct ACK receipt-capacity check passed.

This isolated fixture fills all eight pending slots with unrelated IDs, then verifies a known matching version-2 registration still reaches the public direct ACK path, consumes the effect exactly once, and leaves all eight unrelated receipts unchanged.

Command:

    wsl.exe -d DwemerAI4Skyrim3 --exec python3 /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/package_check.py

Exit status: 0. Raw stdout:

    ....
    ----------------------------------------------------------------------
    Ran 4 tests in 12.784s

    OK

The package fixture verifies the receipt helper is included in deterministic DWPkg, repository archive, and MO2 wrapper payloads. A separate owned isolated PostgreSQL report verifies the actual helper against an ephemeral database; consult database-review.md for that proof. This core report does not treat fixture SQL as installed schema/runtime proof.

## Self-review and limits

- ACK capture rejects off, pair, expired, unrelated-speaker, malformed, and missing-state requests before identity/catalog/presence resolution; a possible active solo match uses fresh identity/scope validation before receipt or evaluation.
- Recovery checks exact ID, one-row cardinality, byte-bounded native fields, speaker/listener/speech tuple, PCV key/config/actor, source/ACK epoch, player/playthrough, and fresh scope before the shared claim/effect gate. Query/storage/MP load and shutdown failures return bounded fixed reasons and fail closed.
- Fresh unrelated chunk receipts can fill the explicit eight-entry ceiling for up to 45 seconds; further unmatched early receipts are logged busy and not stored. This is a deliberate finite bound with no eviction. Direct matching registered ACKs remain independent of that capacity.
- An identical ID/tuple received while its receipt is still stored is a no-op and keeps the original timestamp, including near the 45-second boundary. Once that entry expires and is pruned, a later actual _speech request with the same ID is treated as a new request-time receipt with its own captured epoch. Preventing that later request from becoming a candidate for the full 600-second source lifetime would require a separate longer-lived tombstone structure and its own bounded-capacity policy; this implementation does not add one.
- The END fixture proves an ACK evaluated after active-state clearing cannot start a model/store opinion write. It does not claim END can cancel an effect already committed or an output already delivered/in flight.
- The package and PHP fixtures are isolated evidence, not a live CHIM, installed schema, gameplay, or provider test.
- git diff --check reported no whitespace errors in the owned diff.

Final owned source hashes after the readability-only indentation correction:

    server/reflection.php 7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B
    server/reflection_receipt.php 5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5
    server/log.php 73B78574377395926AF6044863C8A8F43916F91CAAA16EB64C3C03C9DF3F9C07
    scripts/build-package.py 053860A61E388EC2B4F062C6B98C1E387EABBC3E8DEE73BCAA60F7C710558D0F

## Logger allowlist and receipt-expiry follow-up

The warning in the earlier captured registry output was caused by `registration_busy`: `pcv_reflection_log()` emitted `reflection.registration_skipped` with that reason, the event-specific rule accepted it, but the global reason-code precheck rejected it and emitted `invalid_event`. `server/log.php` now includes all new ACK reasons in the shared allowlist. The registry fixture verifies that busy, interaction-stale, and corrupt-receipt events are actually emitted, checks the logger failure list for absence of `invalid_event`, and checks post-expiry same-ID receipt handling. An unexpired identical receipt remains a no-op without refreshing age; after expiry/pruning, a newly received request creates a new request-time candidate. This does not claim permanent historical replay protection.

Focused registry command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php

Exit status: 0. Raw stdout after the logger fix:

    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"7e8afc6568d764d12744690e","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"7e8afc6568d764d12744690e","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.5,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"7e8afc6568d764d12744690e","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.5,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":2.5}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"9aa73f45c7a11e86aaa406e1","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"9aa73f45c7a11e86aaa406e1","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.5,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"9aa73f45c7a11e86aaa406e1","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":144,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_bbbbbbbbbbbbbbbb","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.5,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":2.8}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"6e0239ed529545f10694c1ee","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"6e0239ed529545f10694c1ee","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.6,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"6e0239ed529545f10694c1ee","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":131,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.6,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"committed","outcome":"committed","elapsed_ms":3.1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"0928508759aa1ad1a4939b9f","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"0928508759aa1ad1a4939b9f","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"stale","persistence_reason":"reflection-registration-stale","persistence_ms":0.2,"commit_state":"not_attempted","committed":false,"changes":[],"changed_count":0}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"0928508759aa1ad1a4939b9f","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":143,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_8888888888888888","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"stale","persistence_reason":"reflection-registration-stale","persistence_ms":0.2,"commit_state":"not_attempted","committed":false,"changes":[],"changed_count":0,"stage":"persistence","model_outcome":"valid","model_ms":0,"reason":"stale","outcome":"skipped","elapsed_ms":2}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"ede19974d201300e64a8c464","level":"info","event":"reflection_model_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"stage":"model","model_outcome":"valid","model_ms":0.1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"ede19974d201300e64a8c464","level":"info","event":"persistence_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.4,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1}
    {"schema_version":1,"plugin":"mind_poisoning","version":"0.1.14","timestamp":"2026-10-01T13:31:00Z","request_id":"ede19974d201300e64a8c464","level":"info","event":"request_finished","source_kind":"reflection","payload_bytes":129,"event_id":"200","config_id":"123e4567-e89b-42d3-a456-426614174000","utterance_id":"utt_1234567890abcdef","playthrough_id":"1","speaker_id":"11","speaker_kind":"npc","opinion_owner_id":"11","connector_id":"7","subject_count":1,"cleanup_failed":false,"persistence_outcome":"committed","persistence_reason":"committed","persistence_ms":0.4,"commit_state":"confirmed","committed":true,"changes":[{"subject":"npc:33","delta":2,"before":25,"after":27}],"changed_count":1,"stage":"persistence","model_outcome":"valid","model_ms":0.1,"reason":"committed","outcome":"committed","elapsed_ms":3.3}
    PCV reflection registry checks passed.

Logger gate command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/log_check.php

Exit status: 0. Raw stdout:

    PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, lock fallback

Package gate command:

    wsl.exe -d DwemerAI4Skyrim3 --exec python3 /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/package_check.py

Exit status: 0. Raw stdout:

    ....
    ----------------------------------------------------------------------
    Ran 4 tests in 15.389s

    OK

Focused PHP syntax command:

    wsl.exe -d DwemerAI4Skyrim3 --exec php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php

Exit status: 0. Raw stdout:

    No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php

`git diff --check -- server/log.php tests/reflection_registry_check.php` exited 0 with no output.

Final source hashes after the logger allowlist fix:

    server/reflection.php 7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B
    server/reflection_receipt.php 5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5
    server/log.php E890175B2A44856A22ED7345826410CB09587037271C5296FE2A44A9F9F8EB22
    scripts/build-package.py 053860A61E388EC2B4F062C6B98C1E387EABBC3E8DEE73BCAA60F7C710558D0F
