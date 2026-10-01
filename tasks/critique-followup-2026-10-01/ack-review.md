# ACK registration supersession review

## Result

The confirmed stale-slot issue is fixed. A distinct registration can replace an unclaimed `registered` record once its original `created_at` is at least 60 seconds old. The decision and atomic record write remain inside the existing exclusive state lock in `pcv_reflection_register_with_store()` (`server/reflection.php:633-640`). The 600-second registry TTL is unchanged; the shorter lease applies only to an unclaimed registration.

An exact direct ACK remains eligible for the existing registry lifetime until a newer output replaces its record. Same-ID registration remains idempotent and does not refresh `created_at`. A `claimed` record continues to return `claim_taken`, including at age 60; no provider work is automatically retried.

## Regression evidence

The test-first RED was the expected behavior failure, after the prior assertions for the pre-lease busy case and duplicate timestamp had passed:

```text
Command: wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php
Exit: 1
PHP Fatal error: Uncaught RuntimeException: A distinct output may supersede an unclaimed registration at 60 seconds.
expected: 'registered'
actual: 'registration_busy'
```

The updated fixture uses a 50-second registered record for the “still busy” check. That is deliberately separated from the 60-second boundary to avoid a second-rollover flake and is already beyond the distinct 45-second pending-receipt window. It also verifies that a same-ID duplicate preserves the original timestamp, a 60-second unclaimed record is replaced, and a late ACK for the old ID returns `registration_missing` without a model call or changing the replacement (`tests/reflection_registry_check.php:339-392`).

Additional cases verify a valid claimed record at age 60 still rejects a distinct registration (`tests/reflection_registry_check.php:394-420`) and an exact direct ACK against an unreplaced age-60 registration still commits using the fixture model/store (`tests/reflection_registry_check.php:906-921`). These are isolated MemoryStoreDb/model fixtures, not live database or provider evidence.

## Lock and failure-path review

Both registration replacement and ACK claim use the same exclusive state lock. Therefore a claim that wins first changes the record to `claimed`, and the replacement path keeps returning `claim_taken`; if the new registration wins first after the lease, it atomically replaces the old ID and a later old ACK fails the exact utterance-ID check before provider work. The focused test covers replacement followed by the late ACK. No separate multi-process race stress was run; the serialization conclusion follows from the existing shared-lock call paths, which this change preserves.

The change does not alter the receipt ledger's 45-second pending window, the 600-second general record freshness check, fresh-scope or epoch revalidation, one-claim behavior, or claimed-provider completion. A registered slot with no subsequent output may still accept a valid exact direct ACK under the existing 600-second lifetime; after supersession, the old ID cannot claim the new record.

## Verification

```text
Command: wsl.exe -d DwemerAI4Skyrim3 --exec sh -lc 'php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php >/tmp/pcv-r2-registry.log 2>&1; rc=$?; tail -n 1 /tmp/pcv-r2-registry.log; printf "exit=%s\n" "$rc"; exit "$rc"'
PCV reflection registry checks passed.
exit=0
```

```text
Command: wsl.exe -d DwemerAI4Skyrim3 --exec sh -lc 'php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/reflection.php && php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php && git -C /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev diff --check -- server/reflection.php tests/reflection_registry_check.php'
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/reflection.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php
Exit: 0 (the chained diff whitespace check emitted no output)
```

Final SHA-256:

| File | SHA-256 |
| --- | --- |
| `server/reflection.php` | `A2BC64BE75E377BCA078F45C6C3907D1F39EAB42344FA38F3AAE3ECC9FE5FC17` |
| `tests/reflection_registry_check.php` | `CD363311EE835DB53856DA0395AE67FD12DF938068F0081777CB7438973814E4` |

Only those two implementation/test files were changed for this fix. Documentation still describing the 600-second busy behavior should be reconciled by the assigned docs owner. No package, install, live CHIM/database/provider, or gameplay check was run.

## Final state/scope integration rerun

After the shared state resolver and scope changes landed, the registry fixture was run once more against those final modules:

```text
Command: wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php
Exit: 0
Final stdout: PCV reflection registry checks passed.
```

The full captured stdout contained the expected Mind Poisoning observer records (`reflection_model_finished`, `persistence_finished`, and `request_finished`), including committed fixture evaluations and the stale transaction result with `commit_state=not_attempted`; all fixture assertions passed. The fixture uses in-memory stores and stub models, with no live SQL or provider.

Final dependency/source hashes at this rerun:

| File | SHA-256 |
| --- | --- |
| `server/state.php` | `CE6E9E660E7F491AE51AD000CB86AFEB60E132AB894A7D9DC1D02B81E4E11F6B` |
| `server/scope.php` | `F60519E72C7281E2BDAB81D78C5C873EC264EBEA3EFFEAC6D97B791428AED3C0` |
| `server/reflection.php` | `A2BC64BE75E377BCA078F45C6C3907D1F39EAB42344FA38F3AAE3ECC9FE5FC17` |
| `tests/reflection_registry_check.php` | `CD363311EE835DB53856DA0395AE67FD12DF938068F0081777CB7438973814E4` |
