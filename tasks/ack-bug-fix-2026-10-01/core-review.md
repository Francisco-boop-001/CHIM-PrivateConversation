# Early-ACK bug fixes — core review — 2026-10-01

## Result

Both confirmed receipt defects are fixed in the current workspace. A pending receipt is checked again at the atomic claim, where the stored full receipt must still be fresh and retain the exact captured `created_at`. Receipt admission now checks the live active solo state and interaction generation while holding the existing exclusive state lock, before it reads, prunes, or writes the receipt ledger. Stale scope/epoch and missing/corrupt state rejection leave ledger bytes unchanged.

The active-state check reuses the existing validated state reader and a pure active-solo parser shared with the cheap precheck. The receipt filter now uses the authoritative locked key/config/name/current generation; it deliberately does not prune on actor database ID alone because active state does not carry that stable ID. Provider and catalog work remain outside the lock. Existing claimed-provider behavior remains governed by the 600-second registry and fresh phase revalidation, not the 45-second pending receipt lifetime.

## RED evidence

The pre-fix temporal reproduction and source snapshot are recorded in [`ack-bug-run-2026-10-01/core-review.md`](../ack-bug-run-2026-10-01/core-review.md): a receipt captured at age 44 crossed the 45-second boundary during fresh-scope work, then reached the model (`model_calls=1`) and consumed the registry. The stale-writer reproduction in [`ack-bug-run-2026-10-01/final-review.md`](../ack-bug-run-2026-10-01/final-review.md) showed delayed G4 returning `ready` and removing the current G5 receipt (`current_G5_receipt_count=0`). Those reports retain the original RED outputs and baseline hashes.

## GREEN focused verification

All commands ran in WSL distro `DwemerAI4Skyrim3`. The two new audit fixtures use disposable temporary state; they do not contact the real provider or installed CHIM.

### Receipt expires before claim and captured timestamp replacement

Command:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_bug_check.php
```

Exit status: `0`. Raw stdout:

```text
ACK_BUG_REPRO start_age=44 end_age=47 ttl=45 model_calls=0 result=registration_stale registry_status=registered
Expired unclaimed receipt was rejected before model work.
ACK_TIMESTAMP_REPRO stored=ready changed=true model_calls=0 result=registration_stale registry_status=registered
Replaced pre-claim receipt timestamp was rejected.
```

The second case starts with a fresh exact receipt, changes only the stored timestamp in the existing scope-read seam before claim, and verifies no model call and a still-registered record.

### Stale admission and ledger preservation

Command:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_receipt_bug_check.php
```

Exit status: `0`. Raw stdout:

```text
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

### Existing evaluator, direct capacity, and hook paths

Commands and exits:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php
exit=0
PCV reflection registry checks passed.

wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_direct_ack_capacity_check.php
exit=0
PCV direct ACK receipt-capacity check passed.

wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_hook_timing_check.php
exit=0
PCV reflection hook timing and API compatibility checks passed.
```

The registry fixture exercised the actual Mind Poisoning evaluator with its stub provider; its `claimed_receipt_after_ttl` case committed the expected opinion after the pending lookup TTL, and stale/ended cases retained their no-write assertions. The registry output contained no `invalid_event` warning.

### Actual shutdown callback with isolated PostgreSQL

The shutdown fixture ran against a new temporary PostgreSQL 15 cluster owned by `postgres`, bound only to a Unix socket under `/tmp`, with no TCP listener. The database and the verified temporary cluster directory were removed after the run. Command:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/env -i PATH=/usr/bin:/bin PCV_ACK_TEST_HOST=/tmp/pcv-ack-db-9d2a61a7b4e85c03/socket PCV_ACK_TEST_PORT=55719 PCV_ACK_TEST_DB=pcv_ack_fixture PCV_ACK_TEST_USER=postgres /usr/bin/php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_shutdown_check.php
```

Exit status: `0`. Raw stdout:

```text
PASS success native INSERT complete; die follows
CASE success provider=1 callback_sql=1 registry=consumed
PASS mismatch native INSERT complete; die follows
CASE mismatch provider=0 callback_sql=1 registry=registered
PASS stale native INSERT complete; die follows
CASE stale provider=0 callback_sql=0 registry=registered
PASS scope native INSERT complete; die follows
CASE scope provider=0 callback_sql=1 registry=registered
cleanup=PASS
```

### Syntax, package, and diff checks

`php -l` exited `0` with `No syntax errors detected` for each of:

```text
server/reflection.php
server/reflection_receipt.php
tests/reflection_ack_bug_check.php
tests/reflection_receipt_bug_check.php
tests/reflection_registry_check.php
```

Package command and raw stdout:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec python3 /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/package_check.py
....
----------------------------------------------------------------------
Ran 4 tests in 22.261s

OK
```

`git diff --check` exited `0` with no output.

## Scope and remaining proof limits

The change does not test a live CHIM installation, gameplay, audio delivery, or a real model provider. The shutdown callback was verified using the isolated disposable PostgreSQL fixture; evaluator tests use the existing Mind Poisoning fixture-only harness. Existing deliberate bounds remain: eight receipt candidates, 45-second pending lifetime, and no permanent tombstone after expiry. No manifest/version, installed or Mind Poisoning files were changed.

Current SHA-256:

```text
server/reflection.php 7BAC856D18D19E31AACDE1F7318965F84BF3B790A2AD5469474E73C0C907E526
server/reflection_receipt.php A5F4779697FEA7E04D73B51D4A293CBB5B44D90986EA927AB7DADD4B4A960103
tests/reflection_ack_bug_check.php D583ABEC7B638D6E67600394E4B9D9AE382442B6C8D9EAD740452F04951CA893
tests/reflection_receipt_bug_check.php 5B72CD64D48D52E5F8BCE5921195F839BE7433E43CAC0C99E102EC725E7F46DE
tests/reflection_registry_check.php 452264814ED5626387B187D86088C45EE7303EE5009D8A9C047F8C2525CEC383
```
