<?php
declare(strict_types=1);

if (!extension_loaded('pgsql')) {
    fwrite(STDERR, "pgsql extension is required.\n");
    exit(2);
}

$host = getenv('PCV_ACK_TEST_HOST') ?: '';
$port = getenv('PCV_ACK_TEST_PORT') ?: '';
$database = getenv('PCV_ACK_TEST_DB') ?: '';
$user = getenv('PCV_ACK_TEST_USER') ?: '';
if (preg_match('~\A/tmp/pcv-ack-db-[a-f0-9]{16}/socket\z~D', $host) !== 1
    || realpath($host) !== $host || is_link($host)
    || preg_match('/\A[1-9][0-9]{4}\z/D', $port) !== 1
    || (int) $port < 50000 || (int) $port > 59999
    || $database !== 'pcv_ack_fixture' || $user !== 'postgres') {
    fwrite(STDERR, "Requires a fresh local PostgreSQL fixture under /tmp/pcv-ack-db-<16 hex>/socket.\n");
    exit(2);
}

$source = __DIR__ . '/../server/reflection_receipt.php';
if (!is_file($source)) {
    fwrite(STDERR, "Native ACK reader source is missing.\n");
    exit(2);
}
require_once $source;

function ackDbAssert(bool $condition, string $case): void
{
    if (!$condition) {
        throw new RuntimeException("failed: {$case}");
    }
}

function ackDbStatement(mixed $connection, string $query, array $params = []): void
{
    $result = $params === []
        ? @pg_query($connection, $query)
        : @pg_query_params($connection, $query, $params);
    if ($result === false) {
        throw new RuntimeException('fixture SQL statement failed');
    }
    pg_free_result($result);
}

function ackDbInsert(mixed $connection, string $id, string $speaker, string $listener, string $speech): void
{
    ackDbStatement(
        $connection,
        'INSERT INTO public.speech (utterance_id, speaker, listener, speech) VALUES ($1, $2, $3, $4)',
        [$id, $speaker, $listener, $speech]
    );
}

function ackDbKind(array $result, string $expected, string $case): void
{
    ackDbAssert(($result['kind'] ?? null) === $expected, $case);
}

$connectionString = sprintf(
    'host=%s port=%d dbname=%s user=%s sslmode=disable connect_timeout=3',
    $host,
    (int) $port,
    $database,
    $user
);
$writer = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
$reader = @pg_connect($connectionString, PGSQL_CONNECT_FORCE_NEW);
if ($writer === false || $reader === false || $writer === $reader) {
    if ($writer !== false) {
        pg_close($writer);
    }
    if ($reader !== false) {
        pg_close($reader);
    }
    fwrite(STDERR, "Could not open two independent isolated PostgreSQL connections.\n");
    exit(1);
}

$closedReader = null;
$passed = [];
$failure = null;
try {
    ackDbStatement($writer, 'DROP TABLE IF EXISTS public.speech');
    ackDbStatement($writer, 'CREATE TABLE public.speech (
        rowid bigserial PRIMARY KEY,
        utterance_id text NOT NULL,
        speaker text,
        listener text,
        speech text
    )');

    ackDbKind(pcv_reflection_read_native_ack($reader, 'utt_missing_12345678'), 'missing', 'missing row');
    $passed[] = 'missing row';

    $id = 'utt_uncommitted_12345678';
    ackDbStatement($writer, 'BEGIN');
    try {
        ackDbInsert($writer, $id, 'NPC', 'Player', 'uncommitted fixture');
        ackDbKind(pcv_reflection_read_native_ack($reader, $id), 'missing', 'uncommitted row invisible to independent connection');
        ackDbStatement($writer, 'COMMIT');
    } catch (Throwable $error) {
        @pg_query($writer, 'ROLLBACK');
        throw $error;
    }
    $read = pcv_reflection_read_native_ack($reader, $id);
    ackDbAssert(($read['kind'] ?? null) === 'row'
        && ($read['row'] ?? null) === [
            'utterance_id' => $id,
            'speaker' => 'NPC',
            'listener' => 'Player',
            'speech' => 'uncommitted fixture',
        ], 'row visible to independent connection after commit');
    $passed[] = 'uncommitted invisible, then visible after commit';

    $autocommitId = 'utt_autocommit_12345678';
    ackDbInsert($writer, $autocommitId, 'NPC', 'Player', 'autocommit fixture');
    ackDbKind(pcv_reflection_read_native_ack($reader, $autocommitId), 'row', 'ordinary autocommit row visible');
    $passed[] = 'ordinary autocommit visible';

    $maxId = 'utt_' . str_repeat('a', 128);
    ackDbAssert(strlen($maxId) === 132, '132-byte fixture ID');
    $unicodeSpeech = "¿Héctor’s “yes”? % _ \\ — 東京 🙂";
    ackDbInsert($writer, $maxId, 'NPC — Héc', 'Player', $unicodeSpeech);
    $read = pcv_reflection_read_native_ack($reader, $maxId);
    ackDbAssert(($read['kind'] ?? null) === 'row'
        && ($read['row'] ?? null) === [
            'utterance_id' => $maxId,
            'speaker' => 'NPC — Héc',
            'listener' => 'Player',
            'speech' => $unicodeSpeech,
        ], '132-byte ID and exact Unicode/punctuation fields');
    $passed[] = '132-byte ID and exact Unicode/punctuation fields';

    $oversizeId = 'utt_oversize_12345678';
    ackDbInsert($writer, $oversizeId, 'NPC', 'Player', str_repeat('x', 12001));
    ackDbKind(pcv_reflection_read_native_ack($reader, $oversizeId), 'invalid', 'oversize text rejected');
    $passed[] = 'oversize text rejected';

    $identicalId = 'utt_duplicate_same';
    ackDbInsert($writer, $identicalId, 'NPC', 'Player', 'same duplicate');
    ackDbInsert($writer, $identicalId, 'NPC', 'Player', 'same duplicate');
    ackDbKind(pcv_reflection_read_native_ack($reader, $identicalId), 'ambiguous', 'identical duplicate IDs ambiguous');
    $passed[] = 'identical duplicate IDs ambiguous';

    $conflictId = 'utt_duplicate_conflict';
    ackDbInsert($writer, $conflictId, 'NPC A', 'Player', 'first duplicate');
    ackDbInsert($writer, $conflictId, 'NPC B', 'Player', 'conflicting duplicate');
    ackDbKind(pcv_reflection_read_native_ack($reader, $conflictId), 'ambiguous', 'conflicting duplicate IDs ambiguous');
    $passed[] = 'conflicting duplicate IDs ambiguous';

    $malformedDuplicateId = 'utt_duplicate_malformed';
    ackDbInsert($writer, $malformedDuplicateId, 'NPC', 'Player', 'valid row');
    ackDbInsert($writer, $malformedDuplicateId, 'NPC', 'Player', str_repeat('y', 12001));
    ackDbKind(pcv_reflection_read_native_ack($reader, $malformedDuplicateId), 'ambiguous', 'duplicate ID remains ambiguous with an oversized row');
    $passed[] = 'duplicate ID ambiguous with oversized row';

    $closedReader = $reader;
    pg_close($reader);
    $reader = null;
    ackDbKind(pcv_reflection_read_native_ack($closedReader, $autocommitId), 'unavailable', 'closed connection unavailable');
    $passed[] = 'closed connection unavailable';

    ackDbStatement($writer, 'DROP TABLE public.speech');
    ackDbKind(pcv_reflection_read_native_ack($writer, $autocommitId), 'unavailable', 'missing table unavailable');
    $passed[] = 'missing table unavailable';
} catch (Throwable $error) {
    $failure = $error->getMessage();
} finally {
    if ($reader !== null) {
        pg_close($reader);
    }
    pg_close($writer);
}

if ($failure !== null) {
    foreach ($passed as $case) {
        fwrite(STDOUT, "PASS {$case}\n");
    }
    fwrite(STDERR, "FAIL {$failure}\n");
    exit(1);
}

foreach ($passed as $case) {
    fwrite(STDOUT, "PASS {$case}\n");
}
fwrite(STDOUT, 'PASS isolated native ACK reader (' . count($passed) . " cases)\n");
