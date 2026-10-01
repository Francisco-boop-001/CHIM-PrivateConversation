# ACK maintenance review - 2026-10-01

## Result

Unmatched `_speech` ACK diagnostics now return before fresh identity and resolved-scope work unless the validated stored state contains an unexpired active solo scene. This state check is a negative optimization hint only. A positive hint still goes through fresh identity/scope resolution and the existing exact utterance, speaker/listener, expiry, correlation, replay, and adapter revalidation checks before any opinion effect.

The optional `reflection.php` loads in `prerequest.php` and `prepostrequest.php` now catch `Throwable`, log bounded exception metadata through `pcv_log_exception`, and return without claiming ACK success or registering output. `postrequest.php` does not load `reflection.php`, so it has no optional reflection load boundary to contain.

## ACK-before-registration source finding

Read-only inspection of installed `/var/www/html/HerikaServer` found no existing extension hook between exact utterance construction and delivery flush:

- `lib/chat_helper_functions.php:2014-2015` creates and stores `SCRIPTLINE_UTTERANCE_ID`; lines 2043-2046 emit and retain the exact serialized `ScriptQueue` utterance; lines 2072-2073 flush it.
- `lib/data_functions.php:6214` calls `returnLines()` before action postfilters at lines 6222-6232, so those filters run after utterance delivery.
- `main.php:2919-2923` flushes the response, drains the remaining buffer at 2940, then invokes extension `prepostrequest.php` and `postrequest.php` at 2941-2943.
- The installed extension inventory contains the corresponding prerequest/context/prepostrequest/postrequest stages, but none is an exact-utterance pre-flush callback. The optional custom debug include in `chat_helper_functions.php:2066-2067` is commented out.

Therefore PCV-03's ACK-before-registration race remains unresolved in the inspected installation. Registration still occurs in the existing post-flush `prepostrequest` stage. No queue, worker, core patch, or claim of race closure was added. The inspected installation is pinned to `cf5030f15781637498be86debe26fcf102f5690d`, read with a command-local `safe.directory` override; no global Git setting or installed file was changed.

## Verification evidence

All commands below used PHP 8.2.29 in the `DwemerAI4Skyrim3` WSL environment. Fixture evidence is isolated PHP behavior, not live database, game, or output-delivery proof.

| Check | RED before implementation | GREEN after implementation | Evidence |
| --- | --- | --- | --- |
| `tests/reflection_hook_timing_check.php` | Exit 1: negative ACK-state cases incorrectly reached fresh identity/scope resolution. | Exit 0: missing, unavailable, off, pair, pending-only, and expired states make zero fresh identity/scope calls; a stale positive hint still forces fresh authoritative resolution and produces no unmatched diagnostic/effect. | Isolated fixture |
| `tests/postrequest_terminal_check.php` | Exit 1 before containment: malformed optional `reflection.php` escaped the prerequest include boundary as `ParseError`. A follow-up RED also caught the prerequest load error being mislabeled `solo_reflection` before scope validation. | Exit 0: prerequest ACK and prepostrequest registration contain the parse failure; prerequest logs phase `ack` without claiming a route, while prepostrequest retains its validated solo route. Both log only bounded exception class/code/file/line metadata. | Isolated child-process fixtures |
| `tests/reflection_registry_check.php` | Not applicable; END behavior was already fail-closed and this added a focused regression. | Exit 0: exact registered ACK after END returns `scope_changed`, makes zero provider calls, leaves opinion at 25, and leaves the record registered/unclaimed. Existing positive exact-ACK guard remains covered. | Isolated MemoryStoreDb fixture |

RED command (timing):

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_hook_timing_check.php
```

RED command (load containment):

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/postrequest_terminal_check.php
```

The same commands and this registry command passed after the changes:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_registry_check.php
```

The timing and registry checks both exited 0 in the final three-test run before the final ACK error-context-only edit. After that edit, `postrequest_terminal_check.php` exited 0 and `php -l` exited 0 for `server/prerequest.php` and `tests/postrequest_terminal_check.php`; the change does not affect reflection state/evaluation paths. Earlier `php -l` checks exited 0 for `server/reflection.php`, `server/prepostrequest.php`, and the other changed tests. `git diff --check` reported no whitespace errors in owned changes.

## Limits

The installed-source search is static evidence from the available installation, not runtime proof across other CHIM versions. The PHP checks use isolated fixtures and do not establish live PostgreSQL, game playback, delivered-audio cancellation, or closure of the ACK-before-registration race.
