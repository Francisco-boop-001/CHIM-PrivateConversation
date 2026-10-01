# R1 storage review

## Result

Default state now resolves to `pcv_log_default_directory(true)/state`, outside the extension document root. The path stays stable for the same effective PHP user and canonical extension path; it does not follow `PCV_LOG_DIR` or the request-scoped log override. Explicit `stateDirectory` arguments remain a separate fixture/storage seam and are not redirected into the default root.

When the legacy `server/state` directory exists, the default resolver takes its existing `state.lock` exclusively, checks a bounded allowlist, validates `state.json` with the current state loader and reflection files with their existing readers, then renames the entire directory into private storage and sets the moved directory to mode `0700`. Presence files are bounded and must remain JSON objects; their runtime readers continue to perform the full context and freshness checks. Known writer temporary files are retained. Symlinks, unknown entries, oversized files, invalid authoritative state, conflicting source/destination directories, and failed `rename`/permission changes fail closed. No files are merged, copied, or recursively deleted.

Scope handling uses the shared resolver. If resolving or migrating storage fails while the playthrough identity is unknown, `pcvScopeStoredStateExists()` returns true so the request cannot proceed as if no state exists. END now emits `state.scope_ended`; ARM activation continues to use `state.scope_activated`.

## Verification

The focused state test first failed before implementation:

```text
$ wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/state_check.php
FAIL: default state must live below the stable private logger root
exit 1
```

The final focused state check passed. It exercised same-filesystem migration with active and pending configurations, reflection registry, ACK receipts, both presence files, and a writer temporary file; it checked byte preservation, corrupt-state refusal, destination conflict refusal, cross-device refusal with the source bytes preserved, and the END event.

```text
$ wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/state_check.php
PASS: default state path and atomic legacy migration preserved active/pending, registry, receipts, and presence
PASS: cross-device migration refusal preserved source bytes
PASS: identity, config correlation, legacy compatibility, invalidation/expiry logs, corrupt-state preservation and isolated state logs
exit 0
```

The scope fixture passed with its isolated state-directory stub. The logger-unavailable line is an expected negative fixture near the end of this test.

```text
$ wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/scope_check.php
PASS: stable actor identity, ordinary event transform, authoritative audience, and presence exclusion.
PASS: ambiguous speaker prefixes and unsupported modes fail closed; disabled scope is inert.
PASS: rechat speaker set and pre-generation speaker guard stay within the selected pair.
PASS: silent bystanders are described generically without actor membership or names.
PASS: real hooks preserve the eligible-input boundary, responder selection, rechat clamp, audience, and action schema.
PASS: malformed rechat/Close and outsider generation stop; Director and fresh install remain outside scope.
Private Conversation logger: directory_unavailable request_id=1823ab9d-f831-41c2-b54f-0b404ad45f3f config_id=123e4567-e89b-42d3-a456-426614174000
exit 0
```

The HTTP fixture ran a separate Apache process with a temporary config and loopback port, `AllowOverride None`, and a copied extension tree. Because the invoking shell was root, the fixture ran PHP CLI as `www-data`, matching the isolated Apache worker UID. It first confirmed the synthetic legacy state file was readable, then called the actual copied `state.php` resolver. Public static resources stayed available and all state URLs returned 404 after the move.

```text
$ wsl.exe -d DwemerAI4Skyrim3 --exec python3 -u /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/http_state_protection_check.py /usr/sbin/apache2 /usr/lib/apache2/modules --php /usr/bin/php
RED: HTTP 200 /HerikaServer/ext/private_conversation/state/state.json with AllowOverride None
HTTP 200 /HerikaServer/ext/private_conversation/manifest.json
HTTP 200 /HerikaServer/ext/private_conversation/assets/style.css
HTTP 200 /HerikaServer/ext/private_conversation/assets/ui-refresh.js
HTTP 404 /HerikaServer/ext/private_conversation/state/
HTTP 404 /HerikaServer/ext/private_conversation/state/state.json
HTTP 404 /HerikaServer/ext/private_conversation/state/state.lock
HTTP 404 /HerikaServer/ext/private_conversation/state/.state-ABC123
HTTP 404 /HerikaServer/ext/private_conversation/state/reflection.json
HTTP 404 /HerikaServer/ext/private_conversation/state/reflection_receipts.json
HTTP 404 /HerikaServer/ext/private_conversation/state/presence.json
HTTP 404 /HerikaServer/ext/private_conversation/state/background_presence.json
HTTP 404 /HerikaServer/ext/private_conversation/State/case.json
HTTP 404 /HerikaServer/ext/private_conversation/st%61te/state.json
HTTP 404 /HerikaServer/ext/private_conversation/state%2fstate.json
PASS: with AllowOverride None, the pre-migration state was HTTP-readable; PHP moved it outside DocumentRoot, public resources stayed available, and legacy state URLs returned 403/404
exit 0
```

Focused PHP syntax checks:

```text
$ wsl.exe -d DwemerAI4Skyrim3 --exec php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/state.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/state.php
$ wsl.exe -d DwemerAI4Skyrim3 --exec php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/scope.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/scope.php
$ wsl.exe -d DwemerAI4Skyrim3 --exec php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/state_check.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/state_check.php
$ wsl.exe -d DwemerAI4Skyrim3 --exec php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/scope_check.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/scope_check.php
exit 0
```

`git diff --check -- server/state.php server/scope.php tests/state_check.php tests/scope_check.php tests/http_state_protection_check.py` returned no output and exit 0.

SHA-256 of the final checked files:

| File | SHA-256 |
| --- | --- |
| `server/state.php` | `CE6E9E660E7F491AE51AD000CB86AFEB60E132AB894A7D9DC1D02B81E4E11F6B` |
| `server/scope.php` | `F60519E72C7281E2BDAB81D78C5C873EC264EBEA3EFFEAC6D97B791428AED3C0` |
| `tests/state_check.php` | `55399327175037DFC75405AA5462D6619E528724EC737B1CD6466D79AC10084F` |
| `tests/scope_check.php` | `7401E86C2C14959E48650D1A04FC02DFE8BE0353B0CB9C0E4664C1E048822DFD` |
| `tests/http_state_protection_check.py` | `C6A4A632829B9E19086664772161AD996FFC25770CE3FEE2BE117025C90A8007` |

## Limits and operating conditions

- This was an isolated source/HTTP test, not an installed-extension, PHP-FPM, gameplay, provider, or release test. PHP CLI used the same effective UID and extension path as the isolated Apache worker; a deployment must keep the PHP HTTP process on the same UID and canonical extension path so both resolve the same state root.
- The default root is under the system temporary directory. It can be removed by OS cleanup or reboot, so this change does not promise durable state across those events. Changing the PHP UID or extension path also selects a different root and requires an operator-managed offline move if prior state must be retained.
- `rename()` is deliberately the only migration operation. If the legacy directory and private root are on different filesystems, migration refuses and leaves the legacy files intact; there is no copy fallback. Until an operator performs an offline move, the old web-root files remain at their legacy path, so a server with `AllowOverride None` can still serve them. Do not treat this case as protected by the new default resolver.
- Upgrade migration requires old-version writers to be quiesced. An already-running old version that writes directly to `server/state` could recreate that directory after the rename. Existing `mutable_paths: ["state"]` remains extension-relative; no installer behavior was changed or tested.
- Presence migration checks syntax, object shape, and size while preserving exact bytes. Current runtime readers remain responsible for validating their semantic content and freshness before use.
