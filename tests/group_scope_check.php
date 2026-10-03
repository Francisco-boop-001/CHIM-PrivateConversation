<?php
declare(strict_types=1);

// 0.1.11 group mode: resolved scopes carry every member's live name, the opener name (null for auto) and every
// member's profile; legacy pairs resolve to two members with A as opener.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function scCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $known = ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two'];
    $stored = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'actor_ids' => ['404', '505', '606'],
        'opener' => '606', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $scope = pcvResolveScopeNames($stored, $known, 'Hawke');
    echo json_encode($scope) . "\n";
    scCheck(($scope['members'] ?? null) === ['Stalleo', 'Guard One', 'Guard Two'] && ($scope['opener'] ?? 'missing') === 'Guard Two'
        && ($scope['actor_a'] ?? null) === 'Stalleo' && ($scope['actor_b'] ?? null) === 'Guard One',
        'A resolved group lists member names and the picked opener name.');
    $auto = pcvResolveScopeNames(array_replace($stored, ['opener' => 'auto']), $known, 'Hawke');
    scCheck(array_key_exists('opener', $auto ?? []) && $auto['opener'] === null, 'An auto opener resolves to null.');

    $legacy = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '404', 'actor_b' => '505', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $legacyScope = pcvResolveScopeNames($legacy, $known, 'Hawke');
    scCheck(($legacyScope['members'] ?? null) === ['Stalleo', 'Guard One'] && ($legacyScope['opener'] ?? null) === 'Stalleo',
        'A legacy pair resolves to two members with A as opener.');
    $solo = ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '404', 'actor_b' => null, 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $soloScope = pcvResolveScopeNames($solo, $known, 'Hawke');
    scCheck(is_array($soloScope) && array_key_exists('actor_b', $soloScope) && $soloScope['actor_b'] === null
        && !array_key_exists('members', $soloScope) && pcvScopeMembers($soloScope) === ['Stalleo'],
        'Solo keeps its shape and resolves to one member.');

    scCheck(pcvResolveScopeNames($stored, ['404' => 'Stalleo', '505' => 'Guard', '606' => 'guard'], 'Hawke') === null,
        'Members whose names fold to the same key are refused.');
    scCheck(pcvResolveScopeNames($stored, $known, 'Guard Two') === null, 'A member named like the player is refused.');
    scCheck(pcvResolveScopeNames($stored, ['404' => 'Stalleo', '505' => 'Guard One'], 'Hawke') === null, 'A member missing from the catalog is refused.');

    $rows = [
        ['id' => 404, 'npc_name' => 'Stalleo', 'profile_id' => 7],
        ['id' => 505, 'npc_name' => 'Guard One', 'profile_id' => 8],
        ['id' => 606, 'npc_name' => 'Guard Two', 'profile_id' => 9],
    ];
    $live = pcvResolveLiveScopeState(['status' => 'active', 'scope' => $stored, 'config_id' => null], $rows, 'Hawke',
        ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two']);
    echo 'live=' . json_encode(['status' => $live['status'] ?? null, 'profiles' => $live['profiles'] ?? null]) . "\n";
    scCheck(($live['status'] ?? null) === 'active' && ($live['profiles'] ?? null) === ['Stalleo' => 7, 'Guard One' => 8, 'Guard Two' => 9]
        && ($live['profile_id_a'] ?? null) === 7, 'Live scope resolves every member profile.');
    $missing = pcvResolveLiveScopeState(['status' => 'active', 'scope' => $stored, 'config_id' => null], $rows, 'Hawke',
        ['404' => 'Stalleo', '505' => 'Guard One']);
    scCheck(($missing['status'] ?? null) === 'unavailable', 'Every member must be in the map passed to live resolution.');
    $rowsWithoutGuardTwoProfile = $rows;
    $rowsWithoutGuardTwoProfile[2]['profile_id'] = null;
    $noProfile = pcvResolveLiveScopeState(['status' => 'active', 'scope' => $stored, 'config_id' => null], $rowsWithoutGuardTwoProfile, 'Hawke',
        ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two']);
    // pcvScopeKnownNpcs lists only NPCs with a valid profile, so a member without one is not in the catalog at all.
    scCheck(($noProfile['status'] ?? null) === 'unavailable', 'A member without a valid profile is not in the catalog: unavailable.');
    $noProfileA = pcvResolveLiveScopeState(['status' => 'active', 'scope' => $stored, 'config_id' => null], array_slice($rows, 1), 'Hawke',
        ['404' => 'Stalleo', '505' => 'Guard One', '606' => 'Guard Two']);
    scCheck(($noProfileA['status'] ?? null) === 'unavailable', 'A (the first member) still needs a profile.');

    $request = ['status' => 'active', 'scope' => $scope, 'route' => 'scene_direction', 'origin_mode' => 'STANDARD'];
    scCheck(function_exists('pcvPairRoutedRequest'), 'fixture: pcvPairRoutedRequest exists');
    echo "PASS resolved group scopes carry member names, opener and profiles\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
