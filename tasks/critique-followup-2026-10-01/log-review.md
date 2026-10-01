# Logger contention review

The writer now retries native `flock(..., LOCK_EX | LOCK_NB)` until a cumulative 100 ms request lock-acquisition budget is spent, measured with `hrtime(true)`. Brief contention can still append the event once. On exhaustion, the existing `lock_unavailable` health path marks the request degraded and emits its generic warning once; each already-sanitized, bounded JSONL event line is also sent to PHP's configured `error_log` sink. Failure of that sink is contained. The lock budget limits lock acquisition waiting only; it does not bound total filesystem or error-log I/O.

PHP `error_log` fallback is best effort, is not part of the Private Conversation Logs reader, and does not guarantee durable or lossless logging. Unsafe plugin storage paths are not used for fallback writes. The event catalog now accepts `state.scope_ended` as info/ok with the same allowlisted context as staged/activated scope events. The state emitter remains unchanged for its owner to rename after both changes stabilize.

## Verification

- RED before the writer change: `wsl -d DwemerAI4Skyrim3 -- php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/log_check.php` — exit 1, `FAIL: short lock contention should wait for the lock and finish within the bounded window:`.
- GREEN after the final source/test changes: the same command — exit 0, `PASS: schema/redaction, IDs, debug window, five-segment rotation, private permissions, concurrent JSONL, unsafe-path rejection, bounded lock retry/fallback; eight-event contention elapsed 104.72 ms`. The fixture holds the lock across eight events, checks one JSONL append after brief contention, eight sanitized fallback records after persistent contention, degraded health, privacy sentinels, failed-sink containment, and the END event. The reported 104.72 ms measures the full eight-event logger calls, including fallback I/O, not lock waiting alone.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/server/log.php` — exit 0, `No syntax errors detected`.
- `wsl -d DwemerAI4Skyrim3 -- php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/log_check.php` — exit 0, `No syntax errors detected`.
- `git diff --check -- server/log.php tests/log_check.php` — exit 0.

SHA-256 at verification:

- `server/log.php`: `76D7BE47ED7104AF391012B5C5DC491C700A95F7626E51A62DC0E29867BB9F7F`
- `tests/log_check.php`: `DAD6D2D36D8B114200DF68F8CCDB66C441F84F6F6B77A1A76F3F54A82D203D13`

Only the logger fixture ran; this is not proof of live CHIM/gameplay or PHP error-log durability.
