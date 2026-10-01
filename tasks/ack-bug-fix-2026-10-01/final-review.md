# Two ACK bug fixes — lead review

Verdict: PASS for the two confirmed defects in the canonical development workspace. The lead reviewed every current-task product/test diff against the saved before files, traced admission, direct and recovered ACKs, shared locking, claim, MP phase checks, and error logging, and examined the core owner's verification output and both independent reviews. This is not installed CHIM or Skyrim certification.

## Accepted changes

- `server/reflection.php`: recovered full receipts must still be fresh and retain their original captured timestamp at the exclusive claim boundary. Direct transient ACK metadata stays outside the pending ledger/TTL guard. An already claimed provider keeps the existing registry lifetime and fresh MP phase checks. Fixed admission-rejection reasons use existing logger allowlists.
- `server/reflection_receipt.php`: current active solo context and enabled interaction epoch are checked while holding the shared state/ledger lock, before duplicate reuse or ledger reads/pruning/writes. The cheap precheck and locked admission reuse the same pure state parser without nested locking, catalog queries or provider work under that lock. Pruning uses current locked scope. Fresh uncertain actor IDs are retained because active state stores names rather than authoritative database IDs.
- Three existing targeted fixtures were updated: the expiry reproduction, stale-writer reproduction, and registry fixture setup. Existing tests now create realistic active state; production behavior was not weakened to accommodate incomplete fixtures. Lead returned a fixture indentation defect and requested the original-timestamp replacement check; the same core owner completed both.

## Verification reviewed

Exact commands, captured outputs and exits are in [core-review.md](core-review.md). [storage-review.md](storage-review.md) and [integration-review.md](integration-review.md) independently accept the final call paths and examine those results.

- Receipt age 44 crossed the 45-second limit before claim: zero model calls, `registration_stale`, registry still `registered`. A changed stored timestamp for the same receipt also produced zero model calls and remained unclaimed.
- Delayed G4 admission was rejected while the fresh G5 receipt survived. Changed key/config/name and missing/corrupt active state left receipt bytes unchanged. A same-name actor-ID remap retained the prior receipt.
- Registry/evaluator, direct ACK at full pending capacity, and public hook timing/API compatibility checks passed. The existing registry fixture verifies that a claimed result can commit after the pending receipt TTL and that stale phase checks still reject it.
- The actual PCV shutdown path with disposable socket-only PostgreSQL passed success, tuple mismatch, stale epoch and changed-scope cases. Success reached the stub evaluator once; rejected cases reached it zero times. The cluster and temporary directory were removed.
- Five changed PHP files passed syntax checks; four package checks and `git diff --check` passed. Syntax and package checks are structural evidence, not gameplay proof.

## Scope and limits

The final source comparison in [final-scope-check.json](final-scope-check.json) records exactly two changed runtime files and three changed tests among 52 baseline files, with no unexpected new source files. Their hashes match the tested/reviewed hashes. The 52 protected files are unchanged. PCV remains version 0.1.6 at HEAD `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d`; Mind Poisoning remains at `74ca8c97d30045822170477aad87824fb39b8222`. The lead updated task/review artifacts only.

No installation, collaborator/core/Papyrus edits, commit, push, publication, mirror synchronization or pin advancement occurred. No live database, real model provider, full installed-core generation lifecycle, gameplay or audio-delivery proof is claimed. Direct full-capacity runtime proof uses v2; unchanged legacy v1 compatibility is source-review evidence. The pending eight-entry/45-second bounds, registry lifetime, crash/ambient-transaction recovery limits and unmatched-ACK logging privacy policy remain as previously accepted. No functional defect remains known within this fix scope; optional testing stops here.
