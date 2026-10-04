<?php
declare(strict_types=1);

// 0.1.15 fixes from the first in-game session on 0.1.14 (tasks/live-issues-2026-10-04.md):
// L1/L2  CHIM background narration ('bored' and incoming 'instruction' requests) is turned away while a scene with
//        excluded bystanders is active, so it can neither cancel the scene's reply nor pull bystanders in.
// L1     For silent-bystander scenes, the scene request's timestamp is aligned past background instruction rows that
//        were written while it waited for CHIM's lock.
// L4     Voice-friendly phrases: "wrap up," / "wrap up." / "wrap-up:" and "end-scene".
// L7     "wrap up:" during solo ends the scene; "wrap up: end scene" is an end.
// L6     An early ACK during a reply is "reply in progress" even when an older registration is still stored.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';
require_once dirname(__DIR__) . '/server/reflection.php';

function lfCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

final class LfFakeDb
{
    public array $rows;
    public array $queries = [];
    public function __construct(array $rows) { $this->rows = $rows; }
    public function fetchAll(string $sql): array
    {
        $this->queries[] = $sql;
        return $this->rows;
    }
}

try {
    // L1/L2: background pause decision.
    $exclude = ['status' => 'active', 'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'bystander_mode' => 'exclude']];
    $silent = ['status' => 'active', 'scope' => ['enabled' => true, 'scene_mode' => 'pair', 'bystander_mode' => 'silent']];
    $soloExclude = ['status' => 'active', 'scope' => ['enabled' => true, 'scene_mode' => 'solo', 'bystander_mode' => 'exclude']];
    lfCheck(pcvBackgroundPauseApplies('bored', $exclude) && pcvBackgroundPauseApplies('instruction', $exclude), 'Background narration pauses during an excluded-bystander scene.');
    lfCheck(pcvBackgroundPauseApplies('instruction', $soloExclude), 'Solo scenes pause background narration too.');
    lfCheck(!pcvBackgroundPauseApplies('instruction', $silent), 'Silent-bystander scenes keep background narration.');
    lfCheck(!pcvBackgroundPauseApplies('instruction', ['status' => 'pending']) && !pcvBackgroundPauseApplies('instruction', ['status' => 'off'])
        && !pcvBackgroundPauseApplies('instruction', null), 'Only an active scene pauses background narration.');
    lfCheck(!pcvBackgroundPauseApplies('inputtext', $exclude) && !pcvBackgroundPauseApplies('rechat', $exclude), 'Player input and rechats are never paused.');
    lfCheck(pcv_log_rule_matches('routing.request_skipped', 'info', 'skipped', 'background_paused'), 'The pause is an informational skip.');

    // L1 safety net for silent scenes: align past background instruction rows written during the wait.
    $db = new LfFakeDb([['ts' => '74953692404700']]);
    lfCheck(pcvLatestInstructionInputTs($db, '74928810609800') === '74953692404700', 'The newest background instruction ts is found.');
    lfCheck(str_contains($db->queries[0], "type='user_input'") && str_contains($db->queries[0], "data='instruction'")
        && str_contains($db->queries[0], 'ts>74928810609800'), 'The query looks only at newer instruction user_input rows.');
    lfCheck(pcvLatestInstructionInputTs(new LfFakeDb([]), '74928810609800') === null, 'No newer rows: no change.');
    lfCheck(pcvLatestInstructionInputTs(new LfFakeDb([['ts' => 'x']]), '1') === null && pcvLatestInstructionInputTs($db, 'abc') === null,
        'Malformed timestamps are ignored.');

    // L4: voice-friendly phrases.
    foreach (['Hawke: wrap up, they part ways', 'Hawke: Wrap up. They part ways', 'Hawke: wrap-up: they part ways', 'Hawke: Wrap up - they part ways'] as $text) {
        $wrap = pcvInGameCommand($text, 'Hawke');
        lfCheck(($wrap['command'] ?? null) === 'wrap' && strcasecmp($wrap['direction'] ?? '', 'they part ways') === 0, "'$text' is a wrap-up.");
    }
    lfCheck((pcvInGameCommand('Hawke: Wrap up.', 'Hawke')['direction'] ?? null) === 'They part ways.', 'A bare spoken "Wrap up." gets the default parting.');
    lfCheck((pcvInGameCommand('Hawke: End-scene.', 'Hawke')['command'] ?? null) === 'end', '"End-scene." ends the scene.');
    lfCheck(pcvInGameCommand('Hawke: wrap up the deal, Bruce', 'Hawke') === null, '"wrap up the deal" without a separator stays speech.');
    lfCheck(pcvInGameCommand('Hawke: we should wrap up soon', 'Hawke') === null, 'Ordinary speech mentioning wrap up stays speech.');

    // L7: "wrap up: end scene" is an end; wrap-up during solo ends the solo scene.
    lfCheck((pcvInGameCommand('Hawke: wrap up: end scene', 'Hawke')['command'] ?? null) === 'end', '"wrap up: end scene" ends the scene.');
    lfCheck(pcvWrapEndsScene(['status' => 'active', 'scope' => ['scene_mode' => 'solo']]), 'Wrap-up during an active solo scene ends it.');
    lfCheck(pcvWrapEndsScene(['status' => 'pending', 'pending_scope' => ['enabled' => true, 'scene_mode' => 'solo']]), 'Wrap-up with a queued solo ends it.');
    lfCheck(!pcvWrapEndsScene(['status' => 'active', 'scope' => ['scene_mode' => 'pair']]), 'Pairs keep the closing turn.');

    // L6: early ACK classification.
    $marked = true;
    lfCheck(pcvReflectionEarlyAckPending(['kind' => 'missing'], 'utt-new', $marked), 'Missing registry plus marker: reply in progress.');
    lfCheck(pcvReflectionEarlyAckPending(['kind' => 'ready', 'record' => ['registration' => ['utterance_id' => 'utt-old']]], 'utt-new', $marked),
        'An older stored registration plus marker: still reply in progress.');
    lfCheck(!pcvReflectionEarlyAckPending(['kind' => 'missing'], 'utt-new', false), 'No marker: genuinely missing.');

    echo "PASS live fixes: background paused in excluded scenes, silent-scene ts alignment, voice phrases, solo wrap-up ends, early ACKs classified\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
