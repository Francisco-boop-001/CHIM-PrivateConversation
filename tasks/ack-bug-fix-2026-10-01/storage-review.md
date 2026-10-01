# Storage review — ACK fixes

## Independent state and lock contract

The pending ACK receipt file and PCV `state.json` use the same `state.lock`. `pcv_lock_state()` in `server/state.php:221-268` owns the `flock`; `pcv_load_store()` and `pcv_write_store()` explicitly require the caller to hold it (`:340-404`). PCV state transitions acquire the exclusive lock before loading, mutating and atomically writing state. Therefore the receipt admission/pruning decision can be serialized against active key/config changes by acquiring that existing exclusive lock and reading current state directly with `pcv_load_store()`.

Do not call `pcv_reflection_active_solo_precheck()` while holding that lock: it acquires the same lock itself. An admission guard must fail closed before any duplicate reuse, expiry cleanup, pruning or write if the state load is missing, malformed or unavailable, or if there is no current unexpired enabled solo configuration matching the incoming key, config and actor context. The ACK generation also needs a current enabled/equal check inside the receipt lock, after waiting for it; a request-time snapshot validated before that wait is not proof of current state. Rejected stale admission must leave the ledger byte-for-byte unchanged.

One context field cannot be proved from the persisted active state: the file has no stable actor database ID. The resolved `actor_a_id` comes from the current NPC catalog/scope path (`server/scope.php:399-453`), while the state file retains the configured actor identity/name. If a catalog remap changes the resolved ID without changing the key, config UUID, generation or retained name, the new active state alone cannot prove which captured actor ID is current. Do not prune a still-fresh receipt solely because its actor ID differs from an incoming request snapshot. Preserve that uncertain entry to TTL/capacity or obtain an authoritative current-ID proof without adding a database call under the state lock; full capacity should remain an explicit busy result.

## Required counterexamples and invariants

- **Delayed G4 after current G5:** after G5 admission, a stale captured G4 write must be rejected or otherwise leave the fresh G5 receipt intact. Later epoch rejection does not restore pruned evidence.
- **Same generation, new key/config:** config and playthrough changes can occur without a request snapshot becoming current. The under-lock guard must compare the live persisted key/config/actor identity and reject the old snapshot before pruning.
- **Same retained scope, remapped actor ID:** current file state lacks a stable actor ID; preserving fresh receipts with uncertain IDs is safer than pruning them by the incoming ID.
- **Invalid current state:** missing, corrupt, expired, disabled, wrong-mode, or unavailable active state must not be treated as an empty store and must not prune or add a receipt.
- **Duplicates:** an exact fresh duplicate may reuse the original receipt, but must not change `created_at`. A stale captured duplicate must not renew or remove any existing receipt.
- **Capacity:** no stale or context-mismatched writer may silently evict a fresh candidate. Retain existing fresh evidence and return the existing explicit busy path when bounded capacity prevents another admission.
- **Claim freshness:** before atomically marking a pending full receipt claimed, check both the captured receipt's original timestamp and the exact matching stored receipt's timestamp under the claim lock. Do not substitute a later same-ID capture's timestamp. Keep post-claim provider duration uncapped; expiry before claim and expiry after claim are different cases.

## Review status

### Post-edit source review

The current implementation satisfies the storage rulings above:

- `server/reflection.php:144-162` derives the enabled, unexpired solo context from the state already loaded by the caller. The receipt writer uses this helper while holding the shared exclusive state lock, rather than calling the lock-taking precheck recursively.
- `server/reflection_receipt.php:243-263` holds that lock while loading current state and checking its active solo key/config/actor name and the current interaction epoch against the captured ACK epoch. All of these rejection paths return before reading or changing the receipt ledger. The generation guard therefore prevents a delayed G4 request from pruning current G5 evidence; active key/config changes are serialized by the same PCV state lock.
- `server/reflection_receipt.php:269-300` reuses exact fresh duplicates without changing their stored timestamp, prunes only after current state and epoch admission, retains still-fresh receipts with uncertain actor IDs, and returns `busy` rather than evicting evidence at capacity. The actor-ID policy is conservative because persisted active PCV state contains actor name but no stable actor database ID.
- `server/reflection.php:986-1016` checks the claim against the exact captured receipt timestamp and requires the matching stored receipt to remain fresh while holding the claim lock. `server/reflection.php:1159-1166` rechecks both source and ACK generations at the `pre_model` and `transaction` phases. The freshness limit remains a pre-claim rule; a receipt already claimed for provider work is not expired by elapsed provider time.
- `tests/reflection_receipt_bug_check.php:138-179,181-253` covers the delayed G4/current G5 ledger-preservation case, stale key/config/name admission, uncertain actor-ID remapping, and missing/corrupt current state with byte-for-byte ledger preservation. `tests/reflection_ack_bug_check.php:119-190` covers the pre-claim expiry boundary and a same-ID receipt timestamp replacement between initial evaluation and claim. These fixtures exercise local state helpers with controlled state; they are not live CHIM/gameplay proof.

I found no remaining storage defect in the inspected diff. I reviewed the core owner's `core-review.md` outputs below; I did not rerun those checks. No product or fixture files were edited by this reviewer.

Pre-edit source hashes: `reflection.php` `7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B`; `reflection_receipt.php` `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5`; `state.php` `2387D8C39CFD1FC4FB6C5E3A94981C0A75E8C7617712CD310FB76F6F28C939E8`. Current source hashes and the recorded verification results follow below.

## Recorded verification reviewed

The two focused state-fixture transcripts below are copied from `tasks/ack-bug-fix-2026-10-01/core-review.md`; they are the core owner's runs, not independent reruns by this reviewer. That file retains the exact registry/capacity/hook, isolated PostgreSQL shutdown, syntax/package, and diff command transcripts summarized here.

The focused TTL/timestamp command exited 0:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_bug_check.php
ACK_BUG_REPRO start_age=44 end_age=47 ttl=45 model_calls=0 result=registration_stale registry_status=registered
Expired unclaimed receipt was rejected before model work.
ACK_TIMESTAMP_REPRO stored=ready changed=true model_calls=0 result=registration_stale registry_status=registered
Replaced pre-claim receipt timestamp was rejected.
```

The stale-admission command exited 0:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php
stale_write_kind=interaction_stale
current_generation=5
current_G5_receipt_count=1
captured_G4_receipt_count=0
generation_ledger_unchanged=true
config_scope_stale_kind=scope_changed current_receipt_count=1 ledger_unchanged=true
key_scope_stale_kind=scope_changed current_receipt_count=1 ledger_unchanged=true
actor_scope_stale_kind=scope_changed current_receipt_count=1 ledger_unchanged=true
actor_id_remap_new_kind=ready prior_receipt_count=1
state_missing_kind=scope_changed ledger_unchanged=true
state_corrupt_kind=unavailable ledger_unchanged=true
PASS current G5 receipt survives delayed captured G4 write
cleanup=PASS
```

The existing registry/evaluator, receipt-capacity, and hook timing/API compatibility checks each exited 0; the recorded registry run included a claimed receipt committing after the pending receipt TTL and no `invalid_event` warning. The isolated PostgreSQL 15 shutdown callback command exited 0: its success case reached the provider once and consumed the registry; mismatch, stale-epoch, and changed-scope cases reached the provider zero times and left registration unclaimed. The temporary cluster used a Unix socket without TCP and was removed (`cleanup=PASS`).

The recorded syntax checks (`php -l`) passed for both changed server files and the listed reflection fixtures. `tests/package_check.py` exited 0 with `Ran 4 tests ... OK`; `git diff --check` exited 0 with no output. I rely on these recorded outputs without repeating the checks.

Current hashes reported by the core owner and confirmed against the current workspace for this review:

```text
server/reflection.php 7BAC856D18D19E31AACDE1F7318965F84BF3B790A2AD5469474E73C0C907E526
server/reflection_receipt.php A5F4779697FEA7E04D73B51D4A293CBB5B44D90986EA927AB7DADD4B4A960103
server/state.php 2387D8C39CFD1FC4FB6C5E3A94981C0A75E8C7617712CD310FB76F6F28C939E8
tests/reflection_ack_bug_check.php D583ABEC7B638D6E67600394E4B9D9AE382442B6C8D9EAD740452F04951CA893
tests/reflection_receipt_bug_check.php 5B72CD64D48D52E5F8BCE5921195F839BE7433E43CAC0C99E102EC725E7F46DE
tests/reflection_registry_check.php 452264814ED5626387B187D86088C45EE7303EE5009D8A9C047F8C2525CEC383
```

This verifies the focused PHP fixtures, evaluator hook/capacity behavior, package tests, and isolated PostgreSQL callback flow. It does not prove behavior against a live CHIM installation, actual gameplay/audio delivery, or a real model provider. No manifest/version, installed CHIM, or Mind Poisoning files were changed.
