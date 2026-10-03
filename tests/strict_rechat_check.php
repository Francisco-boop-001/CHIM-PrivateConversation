<?php
declare(strict_types=1);

// 0.1.14 G3 (found on the clone): CHIM's "Strict Rechat Targeting" (ENFORCE_STRICT_RECHAT_RESPONSE) overwrites every
// rechat reply's listener with the previous speaker, so a third member could never get the floor. PCV lifts it for
// this request only on spread turns of a 3+ member scene; pairs, wrap-up, SHARMAT-pinned and non-rechat turns keep it.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/scope.php';

function srCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $spread = ['listeners' => ['Bruce'], 'spread' => true, 'wrap_up' => false, 'sharmat' => false];
    $GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE'] = true;
    pcvApplyTurnPlanToChim($spread, 'rechat');
    srCheck($GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE'] === false, 'A spread rechat lifts strict targeting for this request.');

    foreach ([
        'pair turn' => [['listeners' => ['Aela'], 'spread' => false, 'wrap_up' => false, 'sharmat' => false], 'rechat'],
        'opening input' => [$spread, 'inputtext'],
        'SHARMAT pin' => [['listeners' => ['Bruce'], 'spread' => false, 'wrap_up' => false, 'sharmat' => true], 'rechat'],
    ] as $label => [$plan, $type]) {
        $GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE'] = true;
        pcvApplyTurnPlanToChim($plan, $type);
        srCheck($GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE'] === true, "Strict targeting is kept for: $label.");
    }
    unset($GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE']);
    pcvApplyTurnPlanToChim($spread, 'rechat');
    srCheck(!array_key_exists('ENFORCE_STRICT_RECHAT_RESPONSE', $GLOBALS) || $GLOBALS['ENFORCE_STRICT_RECHAT_RESPONSE'] === false,
        'With the setting off, nothing is switched on.');
    echo "PASS strict rechat targeting is lifted only for spread rechats in 3+ member scenes\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
