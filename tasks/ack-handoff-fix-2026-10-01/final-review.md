# Early-ACK recovery — lead review, 2026-10-01

## Judgment

Accepted for source integration and isolated verification. The extension now preserves a valid early solo `_speech` acknowledgement and reconciles it when the independently registered emitted output and unique native speech row become available. No mandatory CHIM core, Papyrus or Mind Poisoning API change was introduced. Installed CHIM/provider/Skyrim integration is still unverified; this is not a release or deployment certification.

The lead wrote planning/review artifacts only. Product work was owned by the reused gpt-6-luna Max core agent; separate reused agents owned database fixtures and contract/documentation review. Ponytail FULL, distinct ownership and sequential dependent verification were enforced. Existing maintenance edits were preserved.

## Acceptance and evidence

| Requirement | Reviewed evidence | Judgment |
|---|---|---|
| Both arrival orders, at most one effect | Production PCV registration/reconciliation functions with actual MP 0.1.14 API-v1 evaluator, stub model and MemoryStoreDb: early ACK and normal ordering each reach one provider/effect; actor opinion changes 25 to 27. A preceding sentence receipt does not block the final registered ID. | Pass in isolated fixtures; no real-provider or live-opinion-write claim. |
| Exact ACK evidence | Parameterized exact-ID reader, two-row cardinality cap, raw byte bounds before normalization and digest matching. Ten cases against disposable PostgreSQL 15, including uncommitted invisibility/committed visibility, maximum accepted ID, Unicode, duplicate rows, oversized fields and unavailable query/connection. | Pass for actual production reader against isolated native-shaped schema. |
| Real production callback after intake | Copied production prerequest, queue, reconciliation and native reader run against disposable PostgreSQL. Registration initially misses; parameterized native INSERT then PHP die invokes the queued callback. Matching case reaches evaluator stub once and consumes its claim; mismatch, stale epoch and changed scope never invoke it. | Pass in isolated request bootstrap. Environment, identity/scope/store/evaluator/provider are explicit stubs. |
| Preserve eligibility and freshness | Reviewed current nearby AND CHIM-AI-active scope path, identity/config/key/actor matching, request snapshots and MP pre-model/transaction revalidation. G4 ACK with G5 source/current, END, pre-model and transaction changes fail closed. | Source and targeted behavioral evidence; actual client header behavior remains unverified. |
| Bounded, private recovery | Eight entries, 8 KiB, 45-second pending lifetime, existing exclusive state lock and private atomic writes. No raw dialogue, no fresh-entry eviction; corrupt/conflicting/overflow evidence rejected. Matching registered direct ACK works even with all eight pending slots occupied. | Pass. Existing state HTTP protection applies to the new runtime file; no new HTTP rule was needed. |
| Once-only completion | Shared registry claim gates both reconciliation triggers. Provider work is outside the state lock. Consumed same-ID records cannot restart; consumed records allow a distinct new ID. Uncertain completion is not automatically retried. | Pass within existing registry lifetime and MP dedupe semantics; no permanent historical replay guarantee. |
| Diagnostics and packaging | Actual busy/stale-epoch/corrupt-receipt events asserted after shared reason-list correction; log gate passes. Four deterministic package checks verify the new module in DWPkg, tar and MO2 payloads. | Pass. No published asset was replaced. |

Raw commands, results, stub boundaries and hashes are in [core-review.md](core-review.md) and [database-review.md](database-review.md). [contract-review.md](contract-review.md) records independent MP/call-path review. PHP lint is syntax evidence only; the SQL/callback and evaluator fixtures provide the bounded behavioral evidence above.

## Review corrections

Returned defects to their original owner rather than editing product code: maximum ID bound; catch query exceptions; validate byte bounds before trimming; avoid losing the final sentence behind an earlier receipt; keep pending expiry separate from claimed provider duration; allow resolved records to admit a distinct ID; preserve the direct ACK path when pending capacity is full; reuse the normalized tuple parser; correct fixture module/config setup; and fix the shared logger reason allowlist. The first captured GREEN run had an `invalid_event` warning and was not accepted as clean diagnostic evidence. Final focused registry/logger/package output is clean and retained separately from that historical run.

Reviewed every current-task product/test/documentation diff, affected hook/SQL/claim/evaluator/package call paths, optional-module exception containment and failure results. Documentation distinguishes routing completion from a later reflection result and unreleased source from published 0.1.6.

## Deliberate limits

- Legacy version-1 registrations retain their historical direct-ACK compatibility until expiry. They lack a source epoch and cannot use recovery. The new source-plus-ACK epoch guarantee applies to version-2 registrations and recovered receipts, not retroactively to legacy records.
- An identical retained, unexpired receipt does not renew its timestamp. Once expired/pruned, a newly received same-ID ACK can be captured anew. No longer-lived anti-replay tombstone is added; fresh epoch/scope, exact native evidence, claim and MP dedupe remain required.
- Eight pending receipts are a finite bound. Overflow is explicit. One unresolved registered/claimed effect can stay busy for up to 600 seconds, including after END/rearm; fresh checks stop later evaluation but cannot undo committed effects or delivered audio.
- Only the final complete emitted native line is registered; earlier chunks are not combined. An ACK establishes a matching line attempt, not audio playback or hearing.
- Process failure before registration/callback, or a native row still invisible through both probes because of an uncommitted ambient transaction, can still lose recovery. There is no daemon or automatic uncertain-outcome retry.
- No full installed CHIM intake/client/provider/gameplay run occurred. Isolated PostgreSQL resources were stopped and removed. No live database, installed files, service configuration or collaborator code changed.

## Scope and pin check

Task-start comparison: 43 baseline files; only reflection.php, log.php, build-package.py, two existing reflection fixtures, README.md and logging-revision-2.md changed. New receipt module and three focused database/shutdown/direct-capacity fixtures are within ownership. Previously accepted maintenance changes to state/scope/request hooks remain byte-identical to task start. All 52 protected Mind Poisoning/source/snapshot files match the prior protected hash inventory.

Current reviewed product SHA-256:

```text
server/reflection.php         7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B
server/reflection_receipt.php 5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5
server/log.php                E890175B2A44856A22ED7345826410CB09587037271C5296FE2A44A9F9F8EB22
scripts/build-package.py      053860A61E388EC2B4F062C6B98C1E387EABBC3E8DEE73BCAA60F7C710558D0F
```

Whole working-tree tracked diff whitespace check exited 0. HEAD remains `3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d`; manifest remains 0.1.6. No commit, push, publication, installation, mirror synchronization or pin advancement was performed. A future release must establish its own distinct identity and installed-runtime evidence before any release/runtime claim.
