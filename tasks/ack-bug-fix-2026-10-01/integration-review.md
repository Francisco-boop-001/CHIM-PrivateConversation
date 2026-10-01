# Early-ACK fix — integration review

## Result

I found no integration defect in the stable implementation. The two fixes remain confined to receipt admission and the claim boundary. The existing direct registered-ACK path, MP evaluator callbacks, shutdown reconciliation, and fixed-reason logging remain connected.

## Call-path review

- `server/prerequest.php` still dispatches `_speech` to `pcvReflectionEvaluateAck()` and returns before ordinary PCV request routing. `pcvReflectionEvaluateAck()` first uses the cheap active-solo precheck, then checks the current interaction generation and fresh scope. Inactive, pending-only, pair, expired, and unavailable state therefore exits before MP/provider work; `tests/reflection_hook_timing_check.php` exercises these no-scope-resolution cases.
- A registered matching utterance follows the direct branch in `pcvReflectionEvaluateAck()` and calls the evaluator with only transient ACK metadata. It does not insert the direct ACK into `reflection_receipts.json`, so it does not depend on the eight-entry pending-receipt capacity. `tests/reflection_direct_ack_capacity_check.php` verifies this with eight unrelated receipts already pending. That fixture uses a v2 registration. The v1 direct compatibility path is unchanged in the inspected diff, but was not separately exercised by this focused run; v1 records retain their prior live-request ACK checks and do not gain the v2 source-generation receipt contract.
- If registration is missing, belongs to a different utterance, or cannot yet be read, the hook records bounded receipt metadata and queues reconciliation for shutdown. Reconciliation rechecks registry and receipt identity, compares source and ACK interaction generations, reads the exact native `public.speech` row, and verifies its normalized tuple digest before evaluation. A stale, mismatched, ambiguous, unavailable, or out-of-scope receipt cannot reach the provider or commit. The isolated shutdown fixture confirms the expected success, mismatch, stale-generation, and changed-scope outcomes.
- For a pending receipt, the claim lock now requires the stored receipt to match and retain its captured `created_at`, and rejects it if its 45-second pending lifetime elapsed before claim. This check is before provider work. Once claimed, the 45-second pending lookup limit no longer applies; the existing 600-second registry freshness and fresh scope/epoch checks at `pre_model` and `transaction` govern completion. The registry fixture covers a claimed provider crossing the receipt TTL and committing, plus stale epoch rejection at both phases.
- Receipt admission and pruning share the exclusive state lock with UI state changes. Under that lock, the writer loads current active solo state and enabled interaction generation, and validates the current key/config/actor name before touching the ledger. The current generation/config/key/actor mismatch and missing/corrupt-state cases leave ledger bytes unchanged in the regression fixture. Actor database ID is intentionally not used to prune because the persisted active state has no authoritative stable actor ID; the actor-ID remap case retains prior evidence while accepting the new receipt.
- New rejection outcomes use fixed allow-listed logger reasons in `server/log.php`. The registry fixture asserts the registration/receipt busy, stale interaction, and receipt error events and reports no `invalid_event` fallback.

## Verification evidence

The core owner's exact commands, raw outputs, exit statuses, hashes, and fixture boundaries are in [`core-review.md`](core-review.md). I inspected that report and the stable source diff; I did not rerun the checks.

Focused results recorded there:

- The age-44 receipt crossed the 45-second limit during scope work and returned `registration_stale`, with zero model calls and the registry still `registered`. Replacing the stored receipt timestamp before claim was also rejected with zero model calls.
- The delayed G4 write returned `interaction_stale`; the valid G5 receipt remained. Config, key, actor, missing-state, and corrupt-state rejection cases preserved the ledger as asserted.
- The registry, direct-capacity, hook-timing, shutdown-callback, package, PHP syntax, and whitespace checks all exited successfully. The isolated PostgreSQL shutdown fixture used a temporary socket-only cluster and cleaned it up.

## Limits

The evaluator and core service are not a live CHIM installation. The shutdown check exercises the production PCV prerequest/receipt/shutdown path and a real native-row insert in disposable PostgreSQL, but substitutes the MP store/evaluator and does not run the full CHIM bootstrap or `prepostrequest.php` generation lifecycle. No real provider, installed database, gameplay, or audio-delivery proof was performed. The direct-capacity fixture is v2; the unchanged v1 compatibility statement above is based on source-diff review.
