<?php
declare(strict_types=1);

// Full-reply registration for Mind Poisoning's reply API (v2). CHIM splits a reflection into several
// ScriptQueue lines; PCV must register every line of *this* request, proven from its own eventlog anchor,
// and fall back to the final line whenever grouping cannot be proven.

define('PCV_LOG_TESTING', true);
define('CHIM_MIND_POISONING_TEST_FIXTURES_ONLY', true);
$mindPoisoningRoot = null;
foreach ([dirname(__DIR__, 2) . '/CHIM-MindPoisoning', dirname(__DIR__, 3)] as $candidate) {
    if (is_file($candidate . '/tests/runtime_test.php') && is_file($candidate . '/server/reflection.php')) {
        $mindPoisoningRoot = $candidate;
        break;
    }
}
if ($mindPoisoningRoot === null) {
    throw new RuntimeException('Mind Poisoning reflection test fixtures were not found.');
}
require_once $mindPoisoningRoot . '/tests/runtime_test.php';
require_once $mindPoisoningRoot . '/server/reflection.php';
require_once __DIR__ . '/../server/reflection.php';

function fullReplyCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function fullReplyStore(array $lines): \MemoryStoreDb
{
    $store = new \MemoryStoreDb();
    foreach ($lines as [$eventId, $utteranceId, $speaker, $text, $target, $state]) {
        $store->events[] = [
            'event_id' => $eventId, 'utterance_id' => $utteranceId, 'delivery_state' => $state,
            'source_data' => "$speaker: $text (talking to $target)", 'gamets' => 292500000, 'type' => 'chat',
        ];
    }
    return $store;
}

function fullReplyReader(int $anchors, array $rows): callable
{
    return static fn(string $requestTs, int $finalEventId): array => ['anchor_count' => $anchors, 'rows' => $rows];
}

$sentinel = 'explicit_disable_rechat';
$lines = [
    [101, 'utt_line_one_000001', 'Aela', 'Aela the Huntress, can I trust her?', $sentinel, 'spoken'],
    [103, 'utt_other_npc_00001', 'Lydia', 'I am sworn to carry your burdens.', 'Hawke', 'spoken'],
    [104, 'utt_line_two_000002', 'Aela', 'She calls him alpha and I do not like it.', $sentinel, 'emitted'],
];
$rows = [
    ['rowid' => 100, 'type' => 'prechat', 'utterance_id' => null],
    ['rowid' => 101, 'type' => 'chat', 'utterance_id' => 'utt_line_one_000001'],
    ['rowid' => 102, 'type' => 'prechat', 'utterance_id' => null],
    ['rowid' => 103, 'type' => 'chat', 'utterance_id' => 'utt_other_npc_00001'],
    ['rowid' => 104, 'type' => 'chat', 'utterance_id' => 'utt_line_two_000002'],
];

fullReplyCheck(pcv_reflection_mind_poisoning_reply_api_compatible(), 'Mind Poisoning reply API v2 fixtures must be detected.');

// 1. All of the actor's sentinel lines after this request's anchor, in order; other NPCs are skipped.
$store = fullReplyStore($lines);
$got = pcv_reflection_reply_lines($store, 'Aela', 104, 'utt_line_two_000002', '1900000000000001', fullReplyReader(1, $rows));
$expected = [
    ['event_id' => 101, 'utterance_id' => 'utt_line_one_000001', 'speech_hash' => hash('sha256', 'Aela the Huntress, can I trust her?')],
    ['event_id' => 104, 'utterance_id' => 'utt_line_two_000002', 'speech_hash' => hash('sha256', 'She calls him alpha and I do not like it.')],
];
echo 'lines=' . json_encode(array_column($got ?? [], 'event_id')) . "\n";
fullReplyCheck($got === $expected, 'The reply must contain both actor lines, ordered, with parsed-text digests.');

// 2. Grouping that cannot be proven falls back (null): no unique anchor, another request inside the window,
//    the actor speaking to someone else inside the window, an aborted line, or more than eight lines.
$cases = [
    'no_anchor' => [fullReplyStore($lines), fullReplyReader(0, $rows)],
    'two_anchors' => [fullReplyStore($lines), fullReplyReader(2, $rows)],
    'other_request' => [fullReplyStore($lines), fullReplyReader(1, array_merge(
        array_slice($rows, 0, 3), [['rowid' => 103, 'type' => 'inputtext', 'utterance_id' => null]], array_slice($rows, 4)))],
    'actor_ordinary_line' => [fullReplyStore([$lines[0], [103, 'utt_other_npc_00001', 'Aela', 'Hello, Hawke.', 'Hawke', 'spoken'], $lines[2]]),
        fullReplyReader(1, $rows)],
    'aborted_line' => [fullReplyStore([[101, 'utt_line_one_000001', 'Aela', 'First.', $sentinel, 'aborted'], $lines[1], $lines[2]]),
        fullReplyReader(1, $rows)],
    'final_not_last' => [fullReplyStore($lines), fullReplyReader(1, array_slice($rows, 0, 4))],
];
$replyOf = static function (int $count): array {
    $lines = [];
    $rows = [];
    for ($i = 1; $i <= $count; $i++) {
        $lines[] = [200 + $i, sprintf('utt_many_line_%05d', $i), 'Aela', "Line $i.", 'explicit_disable_rechat', 'spoken'];
        $rows[] = ['rowid' => 200 + $i, 'type' => 'chat', 'utterance_id' => sprintf('utt_many_line_%05d', $i)];
    }
    return [$lines, $rows];
};
// Live replies ran to 13 lines; Mind Poisoning 0.1.16 accepts up to 24 (its text cap is the real bound).
[$thirteenLines, $thirteenRows] = $replyOf(13);
$thirteen = pcv_reflection_reply_lines(fullReplyStore($thirteenLines), 'Aela', 213, 'utt_many_line_00013',
    '1900000000000001', fullReplyReader(1, $thirteenRows));
echo 'thirteen_lines=' . ($thirteen === null ? 'fallback' : count($thirteen)) . "\n";
fullReplyCheck(is_array($thirteen) && count($thirteen) === 13, 'A 13-line reply must be registered in full.');
[$manyLines, $manyRows] = $replyOf(25);
$cases['over_cap_lines'] = [fullReplyStore($manyLines), fullReplyReader(1, $manyRows), 225, 'utt_many_line_00025'];
foreach ($cases as $label => $case) {
    $result = pcv_reflection_reply_lines($case[0], 'Aela', $case[2] ?? 104, $case[3] ?? 'utt_line_two_000002',
        '1900000000000001', $case[1]);
    echo "$label=" . ($result === null ? 'fallback' : 'grouped') . "\n";
    fullReplyCheck($result === null, "Unprovable grouping ($label) must fall back to the final line.");
}

// 3. Registrations with lines validate and compare strictly; the top level must equal the final tuple.
$registration = [
    'event_id' => 104, 'utterance_id' => 'utt_line_two_000002', 'actor_id' => 11, 'actor_name' => 'Aela',
    'playthrough_id' => '1', 'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'rechat_target_hint' => $sentinel, 'speech_hash' => $expected[1]['speech_hash'],
];
$withLines = $registration + ['lines' => $expected];
fullReplyCheck(pcv_reflection_valid_registration($registration), 'A v1 registration must stay valid.');
fullReplyCheck(pcv_reflection_valid_registration($withLines), 'A registration with valid lines must validate.');
$mismatchedFinal = $withLines;
$mismatchedFinal['lines'][1]['speech_hash'] = str_repeat('a', 64);
fullReplyCheck(!pcv_reflection_valid_registration($mismatchedFinal), 'The final tuple must equal the top-level registration.');
fullReplyCheck(!pcv_reflection_registration_matches($registration, $withLines), 'Adding lines must change the registration identity.');
fullReplyCheck(pcv_reflection_registration_matches($withLines, $withLines), 'Identical registrations with lines must match.');

// 4. The evaluator follows the registration shape.
fullReplyCheck(pcv_reflection_mp_evaluator($withLines) === 'ChimMindPoisoning\\mindPoisoningEvaluateReflectionReply',
    'A registration with lines must use the reply evaluator.');
fullReplyCheck(pcv_reflection_mp_evaluator($registration) === 'ChimMindPoisoning\\mindPoisoningEvaluateReflection',
    'A single-line registration must keep the v1 evaluator.');

// 5. An ACK for an earlier registered line is a quiet non-final ACK, not an unmatched one.
$record = ['registration' => $withLines];
fullReplyCheck(pcv_reflection_is_non_final_line($record, 'utt_line_one_000001'), 'An earlier line must be recognised as non-final.');
fullReplyCheck(!pcv_reflection_is_non_final_line($record, 'utt_line_two_000002'), 'The final line is not non-final.');
fullReplyCheck(!pcv_reflection_is_non_final_line(['registration' => $registration], 'utt_line_one_000001'),
    'Without lines nothing is a known non-final line.');

echo "PASS full-reply lines are grouped from the request anchor, validated, and routed to the reply evaluator\n";
