# Early-ACK recovery core audit — 2026-10-01

## Confirmed: pending receipt can expire before claim and still reach model work

In `server/reflection.php`, `pcv_reflection_evaluate_with_store()` validates a recovered receipt's 45-second freshness at lines 920–929. It then performs the playthrough and fresh-scope reads at lines 936–956. Those reads may take time. Under the exclusive state lock, lines 981–995 locate the stored receipt and compare its tuple through `pcv_reflection_receipts_match()`. That comparison in `server/reflection_receipt.php:194–200` deliberately omits `created_at`; the claim path does not call `pcv_reflection_receipt_fresh()` again before setting status to `claimed` at `reflection.php:1002–1005`. The evaluator can then reach Mind Poisoning at line 1034.

The focused fixture `tests/reflection_ack_bug_check.php` ages a real stored receipt to 44 seconds, delays the first fresh-scope read by 2.1 seconds, then invokes the production evaluator/claim path. It uses the existing Mind Poisoning fixture-only `MemoryStoreDb`, request helpers, and evaluator; no provider or database is contacted. The test's model callback throws deliberately at the model boundary. Its RED assertion expects zero model calls and a still-registered record, so exit 1 is the reproduced failure, not a test-runner failure.

Final post-refactor command and raw result:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_bug_check.php
ACK_BUG_REPRO start_age=44 end_age=47 ttl=45 model_calls=1 result=failed registry_status=consumed
EXIT=1
```

Before refactoring the fixture to reuse shared helpers, two valid runs printed the same failure at age 46. Earlier setup attempts were invalid harness runs (missing interaction setup/parser helpers); they are not evidence for or against product behavior. The final run above includes the explicit Mind Poisoning `server/reflection.php` include needed because `tests/runtime_test.php` fixture-only mode does not load that module.

Minimal fix candidate, not applied: under the existing claim lock, require the exact stored full receipt still to be within pending TTL and bind its `created_at` to the captured receipt before setting `claimed`. Keep transient direct-ACK metadata outside the 45-second ledger check. Do not enforce the pending TTL after the atomic claim: a claimed provider/evaluator remains governed by the registry's 600-second lifetime and the existing fresh epoch/scope revalidation at both MP `pre_model` and transaction callbacks (`reflection.php:1100–1166`).

## Other core-path review

No second core-path defect was reproduced in this bounded audit. Direct ACKs still validate the current request's exact speaker, subtitle hash, listener/player transport, registration and fresh scope. Recovered ACKs additionally carry a captured tuple digest/generation and are matched against the exact native utterance ID before evaluation. The exclusive claim lock rejects a replaced, consumed, or already claimed record; claim token plus immutable registration/receipt snapshot is checked at MP revalidation and again when consuming (`reflection.php:972–1005`, `1100–1185`). These checks limit the TTL issue to the interval before claim; they do not repair it.

A separate source-level concern was sent to the storage owner and root, not reproduced by this core fixture: `pcv_reflection_store_ack_receipt()` prunes entries against the incoming ACK generation at `server/reflection_receipt.php:262–269`. A delayed stale-generation ACK may therefore filter a newer-generation receipt if other scope keys match. I have not claimed this as a confirmed finding; the storage owner owns its independent reproduction.

## Snapshot and scope

This was an audit, not a fix. No product source was changed. The only owned test is the new focused RED fixture above. No broad suite, live DB/provider/core/MP access, install, or deployment was used.

```text
HEAD: 3f7c3681cd5f3a36493eaad5c54b126c58f2ab0d
Branch: fix/scene-scope-and-diagnostics
Manifest: 0.1.6
server/reflection.php         SHA256 7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B
server/reflection_receipt.php SHA256 5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5
tests/reflection_ack_bug_check.php
                              SHA256 DE46535FFB210A10931AE445E78CBFC63C1B5518F9CFC4B9E30CFFE2620BC9C1
```
