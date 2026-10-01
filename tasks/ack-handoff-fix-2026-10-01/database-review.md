# Isolated database and native ACK feasibility

## Status

Environment/source review, the focused SQL-reader check, and the isolated production-hook callback check are complete. The actual reader and copied production prerequest/queue/shutdown path passed against fresh disposable PostgreSQL clusters; the Mind Poisoning evaluator/provider, core/store environment, and `ptp_connect()` configuration boundary were stubbed. No live CHIM database was contacted. The isolated transaction test shows a row remains invisible to a second connection until commit; this report does not promise recovery if another plugin leaves an ambient transaction uncommitted through both probes.

## Available isolated runtime

The installed read-only target is `/var/www/html/HerikaServer` in WSL distro `DwemerAI4Skyrim3`, source revision `cf5030f15781637498be86debe26fcf102f5690d`. The Windows host has no `php` or `psql` command. WSL has PHP 8.2.29 with `pgsql`, `pdo_pgsql`, `PDO`, `pcntl`, and `posix`, plus PostgreSQL 15.14 `psql`, `initdb`, `pg_ctl`, and `postgres`. `runuser` is installed; the unprivileged `postgres` OS user can run `initdb --version` and write to `/tmp`. The current WSL execution identity is root, so any cluster must be initialized, run, and stopped as `postgres`, inside a unique verified directory under `/tmp`.

Inventory commands and observed output:

```text
$php = Get-Command php -ErrorAction SilentlyContinue; $psql = Get-Command psql -ErrorAction SilentlyContinue; [pscustomobject]@{ WindowsPHP = if ($php) { $php.Source } else { 'missing' }; WindowsPSQL = if ($psql) { $psql.Source } else { 'missing' } } | Format-List
WindowsPHP : missing
WindowsPSQL : missing

wsl.exe -d DwemerAI4Skyrim3 --exec sh -c 'for x in php psql initdb pg_ctl postgres apache2; do printf "%s=" "$x"; command -v "$x" || true; done; printf "PG_BINS="; ls -1d /usr/lib/postgresql/*/bin 2>/dev/null || true; printf "PHP_MODULES\n"; php -m 2>/dev/null | grep -E "^(PDO|pdo_pgsql|pgsql|pcntl|posix)$" || true; php -r "echo PHP_VERSION, PHP_EOL;" 2>/dev/null || true'
php=/usr/bin/php
psql=/usr/bin/psql
initdb=pg_ctl=postgres=apache2=/usr/sbin/apache2
PG_BINS=/usr/lib/postgresql/15/bin
PHP extensions: pcntl, PDO, pdo_pgsql, pgsql, posix
PHP_VERSION=8.2.29

wsl.exe -d DwemerAI4Skyrim3 --exec sh -c 'id; for x in initdb pg_ctl postgres; do p="/usr/lib/postgresql/15/bin/$x"; if test -x "$p"; then "$p" --version; else echo "$p missing"; fi; done; test -w /tmp && echo TMP_WRITABLE=yes || echo TMP_WRITABLE=no; getent passwd postgres | cut -d: -f1,3,6 || true'
uid=0(root) gid=0(root) groups=0(root)
initdb (PostgreSQL) 15.14 (Debian 15.14-0+deb12u1)
pg_ctl (PostgreSQL) 15.14 (Debian 15.14-0+deb12u1)
postgres (PostgreSQL) 15.14 (Debian 15.14-0+deb12u1)
TMP_WRITABLE=yes
postgres:107:/var/lib/postgresql

wsl.exe -d DwemerAI4Skyrim3 --exec sh -c 'runuser -u postgres -- /usr/lib/postgresql/15/bin/initdb --version && runuser -u postgres -- sh -c "test -w /tmp && echo POSTGRES_CAN_WRITE_TMP=yes || echo POSTGRES_CAN_WRITE_TMP=no"'
initdb (PostgreSQL) 15.14 (Debian 15.14-0+deb12u1)
POSTGRES_CAN_WRITE_TMP=yes
```

No installed service status, live database connection, credential/config file, or production data was inspected. The later isolated run bound no TCP listener and used a unique Unix-socket directory and nondefault port inside its own `/tmp` tree. It created only a disposable `public.speech` fixture schema, connected two PHP/PG sessions to that cluster, then stopped the cluster and removed only the verified task-owned temp path. No package installation was needed.

## Installed `_speech` SQL and termination path

At the pinned installed revision, `processor/comm.php:632-697` parses `_speech`, extracts/normalizes the utterance ID, then calls `$db->insert('speech', ...)` with listener, speaker, raw speech, and utterance ID. The ACK row insertion precedes the later eventlog matching/update work (`:699-716`, `:943-962`). The branch sets `$MUST_END` and reaches its normal end (`:630`, `:968`); `main.php:1527-1539` flushes the short response and calls `terminate()`. `lib/auditing.php:26-48` flushes, releases acquired semaphores, and calls `die()`; `terminate()` itself does not close the SQL connection. The SQL object destructor closes its connection in `lib/postgresql.class.php:73-88`.

The SQL wrapper's exact behavior matters for recovery:

- `sql::insert()` reconnects if needed, builds a single `INSERT`, binds row values through `pg_query_params`, and logs a false query result. It has no success/failure return (`lib/postgresql.class.php:136-171`), so the `_speech` caller cannot observe insertion success from this method.
- No explicit `BEGIN`/`COMMIT` surrounds the `_speech` branch. The `BEGIN` found at `processor/comm.php:309` is in a different earlier request branch. Under ordinary PostgreSQL autocommit, a successful standalone insert should be visible after the statement completes; ambient transaction state/visibility is still an isolated-runtime question, not proven by the wrapper source alone.
- `sql::fetchOne($query, $params)` reconnects and uses `pg_query_params` when parameters are supplied, but it returns only the first row. On query failure it logs and returns `[]`, which is indistinguishable from no result (`lib/postgresql.class.php:418-450`). It cannot establish exactly-one-row cardinality or distinguish missing data from query failure for this gate. The new reader must report query status and cardinality separately, using parameterized SQL, and reject zero, multiple, or failed results.

The generic PHP CLI probe was:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec php -r 'class S { public function __destruct() { echo "destructor\n"; } } $s = new S(); register_shutdown_function(static function () { echo "callback\n"; }); echo "body\n"; die();'
body
callback
destructor
```

It shows ordinary PHP shutdown callbacks run after `die()` and before an object destructor in this CLI probe. It does not prove that the extension callback has the right include scope, that CHIM's `$db` is available/usable at callback time, that a PostgreSQL insert is committed/visible, or that an actual provider/evaluator path is safe. The earlier PHP 8.2.29 probe in `tasks/ack-handoff-investigation-2026-10-01/delivery-evidence.md` also tested callback-after-`die()` without a database.

## Actual isolated reader check (SQL stage only)

The focused test at `tests/reflection_ack_database_check.php` passed the actual `pcv_reflection_read_native_ack()` helper with a native `\PgSql\Connection` object. It guarded its connection target to the exact `/tmp/pcv-ack-db-<16 hex>/socket` shape, database `pcv_ack_fixture`, and user `postgres`; opened independent writer/reader connections with `PGSQL_CONNECT_FORCE_NEW`; and created only `public.speech (rowid bigserial primary key, utterance_id text, speaker text, listener text, speech text)`. The helper source SHA-256 at the run was `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5`; the test file SHA-256 was `B306C870536138754595C8A7F9E3CB0485DCD20FC0115F0119CF808C81140E7F`.

The disposable directory was `/tmp/pcv-ack-db-56502906b0084ad5`. Setup/start/configure/create commands and observed results:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec mkdir -m 700 -- /tmp/pcv-ack-db-56502906b0084ad5
wsl.exe -d DwemerAI4Skyrim3 --exec chown postgres:postgres -- /tmp/pcv-ack-db-56502906b0084ad5
wsl.exe -d DwemerAI4Skyrim3 --exec runuser -u postgres -- mkdir -m 700 -- /tmp/pcv-ack-db-56502906b0084ad5/data /tmp/pcv-ack-db-56502906b0084ad5/socket
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/lib/postgresql/15/bin/initdb -D /tmp/pcv-ack-db-56502906b0084ad5/data --username=postgres --auth-local=trust --auth-host=reject
Success. You can now start the database server using:
    /usr/lib/postgresql/15/bin/pg_ctl -D /tmp/pcv-ack-db-56502906b0084ad5/data -l logfile start

wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/lib/postgresql/15/bin/pg_ctl -D /tmp/pcv-ack-db-56502906b0084ad5/data -l /tmp/pcv-ack-db-56502906b0084ad5/postgres.log -o "-c listen_addresses= -c unix_socket_directories=/tmp/pcv-ack-db-56502906b0084ad5/socket -c port=55441" -w start
waiting for server to start.... done
server started

wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/psql -X -h /tmp/pcv-ack-db-56502906b0084ad5/socket -p 55441 -U postgres -d postgres -A -t -F '|' -c "SELECT 'listen_addresses=' || current_setting('listen_addresses'), 'socket=' || current_setting('unix_socket_directories'), 'port=' || current_setting('port');"
listen_addresses=|socket=/tmp/pcv-ack-db-56502906b0084ad5/socket|port=55441

wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/psql -X -h /tmp/pcv-ack-db-56502906b0084ad5/socket -p 55441 -U postgres -d postgres -v ON_ERROR_STOP=1 -c 'CREATE DATABASE pcv_ack_fixture'
CREATE DATABASE
```

The actual helper test command and full stdout were:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/env -i PATH=/usr/bin:/bin PCV_ACK_TEST_HOST=/tmp/pcv-ack-db-56502906b0084ad5/socket PCV_ACK_TEST_PORT=55441 PCV_ACK_TEST_DB=pcv_ack_fixture PCV_ACK_TEST_USER=postgres /usr/bin/php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_database_check.php
PASS missing row
PASS uncommitted invisible, then visible after commit
PASS ordinary autocommit visible
PASS 132-byte ID and exact Unicode/punctuation fields
PASS oversize text rejected
PASS identical duplicate IDs ambiguous
PASS conflicting duplicate IDs ambiguous
PASS duplicate ID ambiguous with oversized row
PASS closed connection unavailable
PASS missing table unavailable
PASS isolated native ACK reader (10 cases)
exit code: 0
```

This exercised absent-row versus uncommitted-row invisibility, visibility after commit, normal standalone autocommit, max 132-byte ID, exact Unicode/punctuation, a 12,001-byte field, identical/conflicting duplicates, a duplicate set containing an oversized row, closed connection, and missing table/query failure. The reader's SQL uses `CASE WHEN octet_length(speech) <= 12000 THEN speech ELSE NULL END`, so the oversized body is not selected into PHP; the actual result was `invalid`. Any row still uncommitted through both reconciliation probes remains `missing` to the independent connection and must fail closed; the reader does not commit another writer's transaction.

Source review confirms the include chain `lib/playthrough_home.php:2-3` -> `lib/playthrough_retention.php:4` -> `lib/playthrough_preferences.php`, where `ptp_connect()` is defined at `:20-33` and passes `PGSQL_CONNECT_FORCE_NEW` to `pg_connect()`. Thus the production wrapper obtains a fresh connection rather than a reusable shared core handle before its `pg_close()`. This is source-only connection-ownership evidence; no config resolution or live endpoint was exercised.

Cleanup command/result:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/lib/postgresql/15/bin/pg_ctl -D /tmp/pcv-ack-db-56502906b0084ad5/data -m fast -w stop
waiting for server to shut down.... done
server stopped

verified=/tmp/pcv-ack-db-56502906b0084ad5 owner=postgres state=stopped
cleanup=PASS target=/tmp/pcv-ack-db-56502906b0084ad5
```

The final cleanup verified exact `realpath`, non-symlink status, `postgres` ownership and absence of `postmaster.pid` before removing only that directory. No other `/tmp` path was touched.

## Isolated production-hook callback check

The focused fixture `tests/reflection_ack_shutdown_check.php` copies current production `prerequest.php`, `reflection.php`, and `reflection_receipt.php` into a temporary extension-shaped tree. It executes the actual `_speech` branch in `prerequest.php`, which queues the actual shutdown callback. It then calls production `pcv_reflection_register_with_store()` with a stub `StoreDb` before any native row exists; the registration-side production lookup returns missing and leaves the record registered. The runner inserts one native-shaped row with parameterized SQL, closes that writer connection, then calls PHP `die()`. The queued callback opens a fresh connection through the production `pcv_reflection_lookup_native_ack()` wrapper, whose `ptp_connect()` implementation is a test stub guarded to this disposable socket, and uses the actual production SQL reader. No generic-only shutdown probe substitutes for this path.

The fixture creates only `public.speech (rowid bigserial primary key, utterance_id text, speaker text, listener text, speech text)` in database `pcv_ack_fixture`. Its other explicit stubs are `state.php`, `scope.php`, `PostgresStoreDb`, the `StoreDb` used by late registration, Mind Poisoning evaluator/provider, and runtime identity/playthrough functions. The evaluator stub accepts only if the production callback supplies both `pre_model` and `transaction` revalidation. The fixture also verifies the private receipt file contains no raw speech. It makes no installed config, database, provider, or service calls.

The isolated PostgreSQL 15.14 cluster used `/tmp/pcv-ack-db-c8d1f042a708e65b`, ran as unprivileged OS user `postgres`, and bound no TCP listener. Init/start/database commands:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec mkdir -m 700 -- /tmp/pcv-ack-db-c8d1f042a708e65b
wsl.exe -d DwemerAI4Skyrim3 --exec chown postgres:postgres -- /tmp/pcv-ack-db-c8d1f042a708e65b
wsl.exe -d DwemerAI4Skyrim3 --exec runuser -u postgres -- mkdir -m 700 -- /tmp/pcv-ack-db-c8d1f042a708e65b/data /tmp/pcv-ack-db-c8d1f042a708e65b/socket
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/lib/postgresql/15/bin/initdb -D /tmp/pcv-ack-db-c8d1f042a708e65b/data --username=postgres --auth-local=trust --auth-host=reject
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/lib/postgresql/15/bin/pg_ctl -D /tmp/pcv-ack-db-c8d1f042a708e65b/data -l /tmp/pcv-ack-db-c8d1f042a708e65b/postgres.log -o "-c listen_addresses= -c unix_socket_directories=/tmp/pcv-ack-db-c8d1f042a708e65b/socket -c port=55632" -w start
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/psql -X -h /tmp/pcv-ack-db-c8d1f042a708e65b/socket -p 55632 -U postgres -d postgres -v ON_ERROR_STOP=1 -c 'CREATE DATABASE pcv_ack_fixture'
CREATE DATABASE
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/psql -X -h /tmp/pcv-ack-db-c8d1f042a708e65b/socket -p 55632 -U postgres -d postgres -A -t -c 'SHOW listen_addresses' -c 'SHOW unix_socket_directories' -c 'SHOW port'

/tmp/pcv-ack-db-c8d1f042a708e65b/socket
55632
```

The `listen_addresses` result is the blank first line, confirming TCP is disabled. The first combined setup invocation had a shell-quoting error in its trailing settings-display subcommand after cluster start and database creation; the separate successful `SHOW` command above verified the actual settings.

Lint and callback fixture command/output:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec /usr/bin/php -l /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_shutdown_check.php
No syntax errors detected in /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_shutdown_check.php

wsl.exe -d DwemerAI4Skyrim3 --exec /usr/sbin/runuser -u postgres -- /usr/bin/env -i PATH=/usr/bin:/bin PCV_ACK_TEST_HOST=/tmp/pcv-ack-db-c8d1f042a708e65b/socket PCV_ACK_TEST_PORT=55632 PCV_ACK_TEST_DB=pcv_ack_fixture PCV_ACK_TEST_USER=postgres /usr/bin/php /mnt/k/ActorwrightExchange/projects/CHIM-PrivateConversation-dev/tests/reflection_ack_shutdown_check.php
PASS success native INSERT complete; die follows
CASE success provider=1 callback_sql=1 registry=consumed
PASS mismatch native INSERT complete; die follows
CASE mismatch provider=0 callback_sql=1 registry=registered
PASS stale native INSERT complete; die follows
CASE stale provider=0 callback_sql=0 registry=registered
PASS scope native INSERT complete; die follows
CASE scope provider=0 callback_sql=1 registry=registered
cleanup=PASS
exit code: 0
```

The test assertions additionally verify the success evaluator phase list is exactly `['pre_model', 'transaction']`; every scenario leaves exactly one inserted row and one bounded receipt. A same-ID mismatched tuple reaches SQL but never invokes the evaluator. A changed interaction generation fails before callback SQL lookup. A changed active config reaches SQL, then fails scope validation before evaluator invocation. `callback_sql` is the number of fresh wrapper connections opened after the registration-side pre-insert miss.

Production source SHA-256 values used by the test: `server/prerequest.php` `4653DCFEEA5B64CB17534DC3424D7D10DF1EF2D204816CE83C02FB8B87CF036A`; `server/reflection.php` `7ADF7BFFABFA1DC85BB3134105233C828271A0A8DD8F5C40662503C9A8F9809B`; `server/reflection_receipt.php` `5E651289635D013BFC83267D6E64E185859B01A0CAD2D1587433C7A53E4523B5`; new fixture `tests/reflection_ack_shutdown_check.php` `B0AC173E12275FC9A2B04A9F085390746FAA7CCF82F27F235428FD12C5459C5A`.

Cleanup stopped the cluster, checked the canonical path, non-symlink status, owner, and absence of `postmaster.pid`, then removed only the exact task-created directory:

```text
wsl.exe -d DwemerAI4Skyrim3 --exec sh -c 'set -eu
base=/tmp/pcv-ack-db-c8d1f042a708e65b
test "$base" = /tmp/pcv-ack-db-c8d1f042a708e65b
runuser -u postgres -- /usr/lib/postgresql/15/bin/pg_ctl -D "$base/data" -m fast -w stop
resolved=$(realpath -e -- "$base")
test "$resolved" = "$base"
test ! -L "$base"
test "$(stat -c %U -- "$base")" = postgres
test ! -e "$base/data/postmaster.pid"
printf "verified=%s owner=%s state=stopped\n" "$resolved" "$(stat -c %U -- "$base")"
rm -rf -- "$base"
test ! -e "$base" && test ! -L "$base"
printf "cleanup=PASS target=%s\n" "$base"'
waiting for server to shut down.... done
server stopped
verified=/tmp/pcv-ack-db-c8d1f042a708e65b owner=postgres state=stopped
cleanup=PASS target=/tmp/pcv-ack-db-c8d1f042a708e65b
```

## Callback verification and remaining boundaries

The SQL reader and isolated callback checks are complete; they do not establish installed database migration state, real client authenticity, actual provider behavior, or gameplay/audio delivery. If another plugin keeps an ambient transaction uncommitted through both probes, the independent reader correctly sees no row; no recovery should be promised for that case.

No live/installed database, credentials, production service, or provider was used. No installed or product source code was modified by this database review. The two product-adjacent files authored here are focused database and callback fixtures; both remain for lead review.
