# Native ACK receipt storage review

## Finding: confirmed stale-snapshot eviction race

`pcvReflectionEvaluateAck()` captures the ACK request generation and checks it against current interaction state at `server/reflection.php:47-56`. If the registry does not already match, it later calls `pcv_reflection_store_ack_receipt()` at line 95. It does not revalidate the current epoch or scope after the snapshot check and before receipt persistence.

The storage helper serializes each update under the state lock (`server/reflection_receipt.php:243-286`), but its fresh-entry filter at lines 262-269 retains only receipts matching the arriving snapshot's key, config, actor, and generation. It then writes the pruned map and adds that snapshot at lines 275-283. A delayed request carrying generation 4 can therefore run after a generation 5 receipt is stored, remove the fresh generation 5 entry, and replace it with its stale generation 4 receipt. The lock prevents torn or overlapping file writes; it does not make the caller's earlier snapshot current.

The focused regression fixture stores a generation 5 receipt, keeps current state at generation 5, then submits a previously captured generation 4 receipt through the production storage helper. It asserts the intended invariant that the still-fresh generation 5 receipt survives, whether the stale write is rejected or retained without eviction. Against the current source, the helper returns `ready` and the ledger contains only generation 4. The actual interaction check rejects generation 4 against current generation 5, but that happens too late to preserve generation 5's evidence. The consequence is lost recovery/liveness for a still-valid ACK, not authorization of the stale ACK.

The same source-level risk applies within one generation if the active PCV key, config, or actor changes after the ACK scope snapshot is read: the same filter drops fresh entries for the newer scope. I did not build a second fixture for that variant.

Candidate fixes belong with the core owner. They must preserve a newer still-valid receipt when a stale captured request resumes; rejecting the stale effect after it has pruned the newer entry is insufficient. Keep capacity rejection explicit rather than silently evicting another fresh candidate.

## Storage and trust-boundary review

The native reader validates the utterance ID before querying, binds it as `$1`, caps returned field sizes in SQL, and uses `LIMIT 2` so duplicate rows are rejected before any row is accepted (`server/reflection_receipt.php:10-61`). It also rejects malformed UTF-8, empty fields, and a returned ID that differs from the requested ID. The lookup wrapper closes its returned connection in `finally` (`:63-88`); live database behavior was not exercised here.

Receipt state contains a bounded tuple digest and identity/scope metadata rather than raw speech. The reader enforces exact schema, at most eight entries, an 8 KiB file ceiling, unique IDs, valid fields, and no future timestamps (`:292-337`). Reads and writes use the shared state lock; writes serialize to a temporary file and rename atomically (`:339-384`). State file failures, malformed data, and capacity exhaustion fail closed. `state.php:221-268` rejects a symlinked state directory/lock and uses `flock` for cooperating readers and writers.

Expired entries are ineligible: registration lookup checks freshness (`reflection.php:690-716`), and reconciliation checks it before proceeding (`:719-730`). The receipt reader itself does not delete expired entries, so bounded metadata can remain on disk until a later distinct receipt prunes it. This is lazy cleanup, not evidence that an expired receipt is accepted; the stored digest contains no raw dialogue. The deliberate 8-entry/8 KiB bounds and 45-second eligibility window are not treated as defects here.

## Reproduction

Command, run from Windows PowerShell at `K:\ActorwrightExchange`:

```text
wsl.exe -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php
```

Raw output:

```text
stale_write_kind=ready
current_generation=5
current_G5_receipt_count=0
captured_G4_receipt_count=1
PHP Fatal error:  Uncaught RuntimeException: A delayed stale G4 write must not evict the still-fresh current G5 receipt. in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php:13
Stack trace:
#0 /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php(103): receiptBugCheck()
#1 {main}
  thrown in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php on line 13
cleanup=PASS
```

This is the expected RED result against the current source (exit code 1), not a harness startup failure: the stale write succeeded, current G5 remained active, and its receipt count was zero. The fixture uses the actual local receipt/state helpers and a unique temporary directory. It uses no database, provider, installed configuration, or live service. The fixture forces the write ordering sequentially; the real multi-request pause is inferred from the production caller gap at `reflection.php:47-56,95`, not simulated by a multiprocess request harness. `cleanup=PASS` confirms the temporary directory was removed. No broad suite was run.

## Source snapshot

- `server/reflection_receipt.php`: SHA-256 `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5`
- `server/reflection.php`: SHA-256 `7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B`
- `server/state.php`: SHA-256 `2387D8C39CFD1FC4FB6C5E3A94981C0A75E8C7617712CD310FB76F6F28C939E8`
- `tests/reflection_receipt_bug_check.php`: SHA-256 `6C77651938006E3C9B56CC7E765ECB0634E5EC469AFCEE0D82FDA87A471BAFEE`

No product files were changed by this review.
