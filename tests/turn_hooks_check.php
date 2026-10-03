<?php
declare(strict_types=1);

// 0.1.14 hooks: the JSON listener hook follows this turn's plan (SHARMAT pin, wrap-up sentinel, spread), and a
// wrap-up turn disables CHIM's relationship queue for that request (it would treat the sentinel as an NPC).

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';
require_once dirname(__DIR__) . '/server/json_response_custom.php';

function thCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function thReset(array $thScope): void
{
    $GLOBALS['PCV_REQUEST_SCOPE'] = $thScope;
    $GLOBALS['structuredOutputTemplate'] = ['json_schema' => ['schema' => ['properties' => ['listener' => ['type' => 'string']]]]];
    $GLOBALS['responseTemplate'] = ['listener' => 'x'];
}

try {
    $GLOBALS['CHIM_EXECUTION_MODE'] = 'STANDARD';
    $GLOBALS['HERIKA_NAME'] = 'Lidia';
    $trio = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => 'Lidia', 'actor_b' => 'Aela',
        'members' => ['Lidia', 'Aela', 'Bruce'], 'opener' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $thScope = ['status' => 'active', 'scope' => $trio, 'origin_mode' => 'STANDARD', 'origin_request_type' => 'inputtext',
        'origin_dialogue' => 'Hawke: talk', 'route' => 'scene_direction'];
    $enum = static fn(): array => $GLOBALS['structuredOutputTemplate']['json_schema']['schema']['properties']['listener']['enum'] ?? [];

    thReset($thScope);
    unset($GLOBALS['PCV_TURN_PLAN']);
    pcvCustomizeJsonResponseTemplate();
    thCheck($enum() === ['Aela', 'Bruce'], 'Without a plan the listeners are all other members.');

    thReset($thScope);
    $GLOBALS['PCV_TURN_PLAN'] = ['listeners' => ['Bruce'], 'spread' => true, 'wrap_up' => false, 'sharmat' => false];
    pcvCustomizeJsonResponseTemplate();
    thCheck($enum() === ['Bruce'], 'The plan narrows the listener choices.');

    thReset($thScope);
    $GLOBALS['PCV_TURN_PLAN'] = ['listeners' => ['explicit_disable_rechat'], 'spread' => false, 'wrap_up' => true, 'sharmat' => false];
    pcvCustomizeJsonResponseTemplate();
    thCheck($enum() === ['explicit_disable_rechat'] && str_contains($GLOBALS['responseTemplate']['listener'], 'explicit_disable_rechat'),
        'A wrap-up turn uses the closing sentinel.');

    thReset($thScope);
    $GLOBALS['PCV_TURN_PLAN'] = ['listeners' => ['Nazeem'], 'spread' => false, 'wrap_up' => false, 'sharmat' => true];
    pcvCustomizeJsonResponseTemplate();
    thCheck($enum() === ['Aela', 'Bruce'], 'A plan naming a non-member is ignored (scene boundary wins).');

    // Wrap-up guards the relationship queue for this request.
    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = true;
    unset($GLOBALS['PCV_SOLO_RELATIONSHIP_GUARD_SET']);
    $GLOBALS['PCV_REQUEST_SCOPE'] = $thScope + ['wrap_up' => true];
    require dirname(__DIR__) . '/server/prepostrequest.php';
    thCheck(($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] ?? null) === false, 'A wrap-up turn disables the relationship queue for this request.');
    $GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] = true;
    unset($GLOBALS['PCV_SOLO_RELATIONSHIP_GUARD_SET']);
    $GLOBALS['PCV_REQUEST_SCOPE'] = $thScope;
    require dirname(__DIR__) . '/server/prepostrequest.php';
    thCheck(($GLOBALS['RELATIONSHIP_SYSTEM_ENABLED'] ?? null) === true, 'Ordinary scene turns leave the relationship queue alone.');

    echo "PASS the listener hook follows the turn plan within the scene; wrap-up guards the relationship queue\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
