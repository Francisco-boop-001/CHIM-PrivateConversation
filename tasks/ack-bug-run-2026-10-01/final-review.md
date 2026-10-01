# Early-ACK bug run — lead judgment, 2026-10-01

## Result

Two functional defects are confirmed in isolated checks of the current unreleased source. They concern receipt admission and preservation, not the already documented finite capacity or legacy compatibility choices. Both should be fixed before treating this candidate as release-ready. This was a bug check: product code, published assets and installed environments remain unchanged.

The lead reviewed product call paths, both reproduction files, actual output, independent findings and counterarguments. Reused gpt-6-luna Max agents used Ponytail FULL with distinct ownership. The lead wrote review/checklist artifacts only. Existing source changes were preserved.

## Confirmed findings

### P2 — an early receipt can expire before it is claimed, yet reach model work

`pcv_reflection_evaluate_with_store()` checks the full receipt's 45-second lifetime at `server/reflection.php:920-929`. It then performs playthrough and fresh-scope reads. When it finally holds the exclusive claim lock, it checks matching stored receipt data at `:981-996`, but does not recheck receipt freshness before setting `claimed` at `:1003-1005`. MP revalidation deliberately does not enforce the pending lifetime after claim, so it cannot distinguish a valid slow provider from this late admission.

The smallest temporal reproduction starts a receipt at age 44 seconds and delays only the first fresh-scope read by 2.1 seconds. The actual MP API-v1 evaluator reaches the stub model callback after the receipt expires. Final shared-fixture output:

```text
ACK_BUG_REPRO start_age=44 end_age=47 ttl=45 model_calls=1 result=failed registry_status=consumed
EXIT=1
```

The retained regression expects zero model calls and an unclaimed registration after expiry; its RED result is a product-boundary failure, not a harness startup failure. Earlier independently isolated runs observed age 46 with the same call/claim result. The stub model deliberately throws at its invocation, so this proves late admission/model invocation, not a real provider response or opinion commit.

Counterargument considered: a provider admitted while fresh may legitimately exceed 45 seconds. This reproducer delays **before** claim; it does not ask to time out an already claimed provider. Minimal fix candidate: under the existing claim lock, check freshness of the full captured and stored pending receipt before claiming. Preserve the ordinary direct transient-metadata path and the existing post-claim lifetime/revalidation contract.

Evidence: [core-review.md](core-review.md), [regression](../../tests/reflection_ack_bug_check.php).

### P2 — a delayed stale ACK can evict newer valid receipt evidence

`pcvReflectionEvaluateAck()` validates generation/scope at `server/reflection.php:47-56`, then later stores a receipt at `:95`. Storage locks the ledger, but filters it using the **arriving snapshot** at `server/reflection_receipt.php:262-269`. A request paused after validation can resume after a new generation's receipt has been stored; its old snapshot silently prunes that fresh entry.

The production storage helper was exercised with current generation held at G5: first store a fresh G5 receipt, then resume the previously captured G4 write. The intended invariant is that the G5 receipt survives, whether the stale write is rejected or otherwise cannot replace current evidence. Observed RED output:

```text
stale_write_kind=ready
current_generation=5
current_G5_receipt_count=0
captured_G4_receipt_count=1
RuntimeException: A delayed stale G4 write must not evict the still-fresh current G5 receipt.
cleanup=PASS
exit=1
```

This directly proves helper behavior for that write ordering. Reachability through delayed concurrent ACK requests follows from the caller gap; a full concurrent installed-CHIM request run was not performed. Same-generation key/config/actor changes have the same source-level risk but were not separately reproduced.

Counterargument considered: later epoch/scope validation rejects the stale ACK. That protects effects, but cannot restore the newer evidence already removed, so the valid ACK can lose recovery. Minimal fix candidate: validate incoming captured context against current interaction/active scene state at admission under the storage lock before pruning, and reject stale writers without changing newer evidence. Merely checking again outside the lock leaves the same ordering gap. Preserve explicit capacity rejection and avoid silently evicting a still-valid candidate.

Evidence: [storage-review.md](storage-review.md), [regression](../../tests/reflection_receipt_bug_check.php).

## Diagnostic limitation, not a third functional finding

An unmatched early ACK intentionally omits its utterance ID from logs. If later source registration recovers it in another PHP request, request IDs differ and there is no guaranteed per-utterance link between the initial skip and the later effect. Same-request shutdown recovery and already registered records remain correlated.

The existing timing fixture explicitly requires unmatched-ID omission. Do not fix traceability by blindly logging arbitrary unmatched IDs, speech, tuple digests or claim tokens. The logging guide's instruction to read correlated early-ACK/result events together needs this cross-request qualification; a richer handoff link would require an explicit privacy/diagnostic design decision. [integration-review.md](integration-review.md) records the source trace and counterevidence.

## Scope, evidence and limits

- Two targeted regression checks remain RED against unchanged product source. No broad suites or unaffected package/SQL gates were rerun; the runtime payload did not change.
- The TTL regression reuses existing MP fixture-only helpers and MemoryStoreDb, with the actual MP evaluator and a stub model. The storage regression uses actual PCV storage/locking helpers in a temporary directory, with a controlled sequential interleaving and no database/provider.
- Source-only review found no demonstrated additional hook/bootstrap/escaping-exception defect and confirmed that inactive/unrelated ACKs return before identity/catalog/native SQL/provider work. This is not proof of absence of all bugs.
- No new installed CHIM, real provider, native client or Skyrim/audio check was performed. The integration reviewer could not run a separate PHP probe in its default shell; its conclusions are source-only. Other owners ran the two isolated PHP reproductions. Temporary fixture state was removed.
- All 50 task-start source/test/script/doc files are byte-identical. All 52 protected files match the prior inventory. HEAD remains `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d`; manifest remains 0.1.6. Mind Poisoning and installed/published sources were not edited.

Reviewed product SHA-256: reflection.php `7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B`; reflection_receipt.php `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5`. Final regression hashes and whitespace/scope checks are recorded in `final-scope-check.json`.

No commit, push, publication, installation or pin advancement. Keep any eventual product fix with the same responsible core owner, then verify these two invariants and the directly affected compatibility paths.
