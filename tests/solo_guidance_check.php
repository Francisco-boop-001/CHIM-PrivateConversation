<?php
declare(strict_types=1);

// Live 0.1.9 finding: solo lines were spoken *to* the subject standing nearby ("Bruce. You. You're
// s'till here."). Solo is thinking aloud, addressed to no one; the subject is referred to in the third person.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

$text = pcvBuildScopeContext(
    ['scene_mode' => 'solo', 'actor_a' => 'Lidia', 'actor_b' => null, 'bystander_mode' => 'exclude'],
    'Lidia',
    'explicit_disable_rechat'
);
foreach ([
    'addressed to no one',
    'Do not address anyone present, including the person being thought about',
    'third person',
    'at most five sentences',
    'Do not address, include, quote, or narrate the player',
] as $needle) {
    if (!str_contains($text, $needle)) {
        fwrite(STDERR, "FAIL solo guidance is missing: $needle\n");
        exit(1);
    }
}
$pair = pcvBuildScopeContext(
    ['scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Bruce', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
    'Lidia',
    'Bruce'
);
if (str_contains($pair, 'addressed to no one')) {
    fwrite(STDERR, "FAIL pair guidance must keep addressing the counterpart\n");
    exit(1);
}
echo "PASS solo guidance is self-addressed; pair guidance unchanged\n";
