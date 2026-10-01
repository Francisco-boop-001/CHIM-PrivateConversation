<?php
declare(strict_types=1);

$stateModule = dirname(__DIR__) . '/server/state.php';
if (!is_file($stateModule)) {
    fwrite(STDERR, "FAIL: expected state API module at server/state.php\n");
    exit(1);
}
define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
$logFixture = sys_get_temp_dir() . '/chim-private-conversation-log-' . bin2hex(random_bytes(8));
if (!mkdir($logFixture, 0700) || !pcv_log_set_test_directory($logFixture)) {
    fwrite(STDERR, "FAIL: could not prepare isolated logging fixture\n");
    exit(1);
}
require_once $stateModule;

function pcvCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function pcvCheckState(
    array $actual,
    string $status,
    ?array $scope,
    bool $pending,
    string $label,
    ?array $pendingScope = null
): void
{
    pcvCheck(($actual['status'] ?? null) === $status, "$label status mismatch");
    pcvCheck(($actual['scope'] ?? null) === $scope, "$label scope mismatch");
    pcvCheck(($actual['pending'] ?? null) === $pending, "$label pending mismatch");
    pcvCheck(array_key_exists('pending_scope', $actual) && $actual['pending_scope'] === $pendingScope,
        "$label pending scope mismatch");
}

function pcvCleanFixture(string $directory): void
{
    @unlink($directory . '/state.json');
    @unlink($directory . '/state.lock');
    @rmdir($directory);
}

function pcvCleanLogFixture(string $directory): void
{
    foreach (['events.jsonl', 'events.1.jsonl', 'events.2.jsonl', 'events.3.jsonl', 'events.4.jsonl', 'events.lock'] as $name) {
        @unlink($directory . '/' . $name);
    }
    @rmdir($directory);
}

function pcvCleanMigrationFixture(string $directory): void
{
    $temporaryRoot = realpath(sys_get_temp_dir());
    $resolved = realpath($directory);
    if ($temporaryRoot === false || $resolved === false
        || !str_starts_with($resolved, $temporaryRoot . DIRECTORY_SEPARATOR . 'pcv-state-migrate-')) {
        return;
    }
    $items = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($resolved, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::CHILD_FIRST
    );
    foreach ($items as $item) {
        if ($item->isLink() || !$item->isDir()) {
            @unlink($item->getPathname());
        } else {
            @rmdir($item->getPathname());
        }
    }
    @rmdir($resolved);
}

$fixture = sys_get_temp_dir() . '/chim-private-conversation-' . bin2hex(random_bytes(8));
$isolatedLockState = $fixture . '-logger-lock';
$oldErrorLog = ini_get('error_log');
$validCharacterId = str_repeat('a', 32);
$invalidCharacterId = 'not-a-character-id';
$homeState = [
    'available' => true,
    'active_id' => 17,
    'playthroughs' => [[
        'id' => 17,
        'active' => true,
        'character_id' => $validCharacterId,
        'player_name' => 'Runa',
    ]],
];
$privateRootBefore = pcv_log_default_directory(false);
$privateRootExistedBefore = is_string($privateRootBefore) && is_dir($privateRootBefore);

try {
    $defaultStateDirectory = pcv_state_directory(null);
    $privateLogRoot = pcv_log_default_directory(false);
    pcvCheck(is_string($privateLogRoot)
        && $defaultStateDirectory === $privateLogRoot . DIRECTORY_SEPARATOR . 'state',
        'default state must live below the stable private logger root');
    pcvCheck($defaultStateDirectory !== dirname(__DIR__) . '/server/state'
        && !str_starts_with($defaultStateDirectory . DIRECTORY_SEPARATOR, $logFixture . DIRECTORY_SEPARATOR),
        'default state must not live in the webroot or follow the logger test override');
    $explicitFixture = sys_get_temp_dir() . '/pcv-state-migrate-explicit-' . bin2hex(random_bytes(8));
    pcvCheck(pcv_state_directory($explicitFixture) === $explicitFixture,
        'explicit state directory fixtures must retain their supplied path');

    require_once dirname(__DIR__) . '/server/reflection.php';
    require_once dirname(__DIR__) . '/server/scope.php';
    $migrationRoot = sys_get_temp_dir() . '/pcv-state-migrate-' . bin2hex(random_bytes(8));
    $legacyDirectory = $migrationRoot . '/legacy';
    $privateDirectory = $migrationRoot . '/private/state';
    mkdir($legacyDirectory, 0700, true);
    mkdir(dirname($privateDirectory), 0700, true);
    $migrationLock = pcv_lock_state($legacyDirectory, true, LOCK_EX);
    pcvCheck(is_resource($migrationLock), 'could not lock the legacy migration fixture');
    $migrationKey = str_repeat('c', 64);
    $migrationConfigId = '123e4567-e89b-42d3-a456-426614174000';
    $migrationStore = pcv_empty_store($migrationKey);
    $migrationNow = time();
    $migrationStore['active'] = [
        'config' => ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '101', 'actor_b' => null,
            'exclude_player' => true, 'bystander_mode' => 'silent'],
        'config_id' => $migrationConfigId,
        'activated_at' => $migrationNow,
        'expires_at' => $migrationNow + PCV_ACTIVE_TTL,
    ];
    $migrationStore['pending'] = [
        'config' => ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202',
            'exclude_player' => true, 'bystander_mode' => 'exclude'],
        'config_id' => '123e4567-e89b-42d3-a456-426614174001',
        'staged_at' => $migrationNow,
        'expires_at' => $migrationNow + PCV_PENDING_TTL,
    ];
    pcv_write_store($legacyDirectory, $migrationStore);
    $migrationRegistration = [
        'event_id' => 17,
        'utterance_id' => 'utt_migrate12345678',
        'actor_id' => 11,
        'actor_name' => 'Aela',
        'playthrough_id' => 'fixture-profile',
        'config_id' => $migrationConfigId,
        'rechat_target_hint' => 'explicit_disable_rechat',
        'speech_hash' => hash('sha256', 'private fixture output'),
    ];
    pcv_reflection_write_locked($legacyDirectory, [
        'version' => 2,
        'pcv_key' => $migrationKey,
        'config_id' => $migrationConfigId,
        'actor_id' => 11,
        'actor_name' => 'Aela',
        'origin_request_type' => 'inputtext',
        'origin_mode' => 'STANDARD',
        'route' => 'solo_reflection',
        'created_at' => time(),
        'status' => 'registered',
        'claim_token' => null,
        'registration' => $migrationRegistration,
        'source_generation' => 1,
        'ack_receipt' => null,
    ]);
    pcv_reflection_write_receipts_locked($legacyDirectory, [[
        'created_at' => time(),
        'utterance_id' => 'utt_migrate12345678',
        'pcv_key' => $migrationKey,
        'config_id' => $migrationConfigId,
        'actor_id' => 11,
        'actor_name' => 'Aela',
        'ack_generation' => 1,
        'tuple_digest' => hash('sha256', 'native ACK fixture'),
    ]]);
    $migrationPresence = [
        'version' => 1,
        'key' => $migrationKey,
        'observed_at' => time(),
        'radius' => 3000,
        'actors' => [],
        'autonomous_order' => ['request_timestamp' => time(), 'observed_at' => time()],
    ];
    $migrationBackground = [
        'version' => PCV_BACKGROUND_PRESENCE_VERSION,
        'source' => 'infonpc_close_v1',
        'key' => null,
        'player_name' => null,
        'state' => 'unavailable',
        'reason' => 'identity_unavailable',
        'observed_at' => time(),
        'heartbeat_timestamp' => null,
        'baseline_timestamp' => null,
        'actors' => [],
    ];
    file_put_contents($legacyDirectory . '/presence.json', json_encode($migrationPresence, JSON_THROW_ON_ERROR) . "\n");
    file_put_contents($legacyDirectory . '/background_presence.json', json_encode($migrationBackground, JSON_THROW_ON_ERROR) . "\n");
    $migrationFiles = ['state.json', 'presence.json', 'background_presence.json', 'reflection.json', 'reflection_receipts.json'];
    $migrationBytes = [];
    foreach ($migrationFiles as $name) {
        $migrationBytes[$name] = (string)file_get_contents($legacyDirectory . '/' . $name);
    }
    pcv_unlock_state($migrationLock);
    pcv_state_migrate_legacy_directory($legacyDirectory, $privateDirectory);
    pcvCheck(!file_exists($legacyDirectory) && is_dir($privateDirectory),
        'legacy state should move as one directory out of its webroot');
    foreach ($migrationFiles as $name) {
        pcvCheck((string)file_get_contents($privateDirectory . '/' . $name) === $migrationBytes[$name],
            "migration must preserve $name byte-for-byte");
    }
    $migratedStore = pcv_load_store($privateDirectory);
    pcvCheck(($migratedStore['kind'] ?? null) === 'ready'
        && ($migratedStore['state']['active']['config_id'] ?? null) === $migrationConfigId
        && ($migratedStore['state']['pending']['config_id'] ?? null) === '123e4567-e89b-42d3-a456-426614174001',
        'migration must preserve active and pending configuration');
    pcvCheck(pcv_reflection_read_locked($privateDirectory)['kind'] === 'ready'
        && pcv_reflection_read_receipts_locked($privateDirectory)['kind'] === 'ready',
        'migration must preserve readable registry and receipt metadata');
    pcvCheck(pcvScopeStoredStateExists($privateDirectory),
        'scope state detection must follow the resolved private directory');
    echo "PASS: default state path and atomic legacy migration preserved active/pending, registry, receipts, and presence\n";

    $corruptRoot = sys_get_temp_dir() . '/pcv-state-migrate-' . bin2hex(random_bytes(8));
    $corruptLegacy = $corruptRoot . '/legacy';
    $corruptPrivate = $corruptRoot . '/private/state';
    mkdir($corruptLegacy, 0700, true);
    mkdir(dirname($corruptPrivate), 0700, true);
    file_put_contents($corruptLegacy . '/state.json', '{broken state');
    $corruptBytes = (string)file_get_contents($corruptLegacy . '/state.json');
    $corruptLock = pcv_lock_state($corruptLegacy, false, LOCK_EX);
    pcvCheck(is_resource($corruptLock), 'could not lock the corrupt migration fixture');
    pcv_unlock_state($corruptLock);
    $corruptRefused = false;
    try {
        pcv_state_migrate_legacy_directory($corruptLegacy, $corruptPrivate);
    } catch (RuntimeException) {
        $corruptRefused = true;
    }
    pcvCheck($corruptRefused && !file_exists($corruptPrivate)
        && (string)file_get_contents($corruptLegacy . '/state.json') === $corruptBytes
        && pcvScopeStoredStateExists($corruptLegacy),
        'corrupt legacy state must remain intact and be treated as present, never as empty');

    $conflictLegacy = $migrationRoot . '/conflict-legacy';
    $conflictPrivate = $migrationRoot . '/conflict/state';
    mkdir($conflictLegacy, 0700, true);
    mkdir($conflictPrivate, 0700, true);
    file_put_contents($conflictLegacy . '/state.json', json_encode(pcv_empty_store($migrationKey), JSON_THROW_ON_ERROR) . "\n");
    $conflictLock = pcv_lock_state($conflictLegacy, false, LOCK_EX);
    pcvCheck(is_resource($conflictLock), 'could not lock the conflict migration fixture');
    pcv_unlock_state($conflictLock);
    file_put_contents($conflictPrivate . '/keep.txt', 'existing destination');
    $conflictRefused = false;
    try {
        pcv_state_migrate_legacy_directory($conflictLegacy, $conflictPrivate);
    } catch (RuntimeException) {
        $conflictRefused = true;
    }
    pcvCheck($conflictRefused && is_file($conflictLegacy . '/state.json')
        && is_dir($privateDirectory) && is_file($conflictPrivate . '/keep.txt'),
        'an existing destination must not be merged with or replace another store');

    $crossDeviceRoot = '/dev/shm/pcv-state-migrate-' . bin2hex(random_bytes(8));
    $crossDeviceLegacy = $crossDeviceRoot . '/legacy';
    $crossDeviceParent = sys_get_temp_dir() . '/pcv-state-migrate-' . bin2hex(random_bytes(8));
    $crossDevicePrivate = $crossDeviceParent . '/state';
    $tmpStat = @stat(sys_get_temp_dir());
    $shmStat = @stat('/dev/shm');
    if (is_array($tmpStat) && is_array($shmStat) && $tmpStat['dev'] !== $shmStat['dev']) {
        mkdir($crossDeviceLegacy, 0700, true);
        mkdir($crossDeviceParent, 0700, true);
        $crossDeviceStore = json_encode(pcv_empty_store($migrationKey), JSON_THROW_ON_ERROR) . "\n";
        file_put_contents($crossDeviceLegacy . '/state.json', $crossDeviceStore);
        $crossDeviceLock = pcv_lock_state($crossDeviceLegacy, false, LOCK_EX);
        pcvCheck(is_resource($crossDeviceLock), 'could not lock the cross-device fixture');
        pcv_unlock_state($crossDeviceLock);
        $crossDeviceRefused = false;
        try {
            pcv_state_migrate_legacy_directory($crossDeviceLegacy, $crossDevicePrivate);
        } catch (RuntimeException) {
            $crossDeviceRefused = true;
        }
        pcvCheck($crossDeviceRefused && !file_exists($crossDevicePrivate)
            && (string)file_get_contents($crossDeviceLegacy . '/state.json') === $crossDeviceStore,
            'cross-device migration must fail closed and preserve source bytes');
        echo "PASS: cross-device migration refusal preserved source bytes\n";
        @unlink($crossDeviceLegacy . '/state.json');
        @unlink($crossDeviceLegacy . '/state.lock');
        @rmdir($crossDeviceLegacy);
        @rmdir($crossDeviceRoot);
        pcvCleanMigrationFixture($crossDeviceParent);
    } else {
        echo "SKIP: /dev/shm is not a separate filesystem; EXDEV migration refusal not exercised\n";
    }

    $identity = pcv_identity_from_home_state($homeState);
    pcvCheck(is_string($identity['key'] ?? null) && preg_match('/^[a-f0-9]{64}$/D', $identity['key']) === 1,
        'valid active profile and character should produce a SHA-256 key');
    pcvCheck(($identity['player_name'] ?? null) === 'Runa', 'identity adapter should expose the active player name for UI filtering');

    $renamed = $homeState;
    $renamed['playthroughs'][0]['player_name'] = 'Different Display Name';
    pcvCheck(pcv_identity_from_home_state($renamed)['key'] === $identity['key'], 'player name must not affect the key');

    $noActive = $homeState;
    $noActive['active_id'] = 0;
    pcvCheck(pcv_identity_from_home_state($noActive)['key'] === null, 'missing active profile must be unavailable');

    $badCharacter = $homeState;
    $badCharacter['playthroughs'][0]['character_id'] = $invalidCharacterId;
    pcvCheck(pcv_identity_from_home_state($badCharacter)['key'] === null, 'invalid live character ID must be unavailable');

    $ambiguous = $homeState;
    $ambiguous['playthroughs'][] = ['id' => 18, 'active' => true, 'character_id' => str_repeat('b', 32), 'player_name' => 'Runa'];
    pcvCheck(pcv_identity_from_home_state($ambiguous)['key'] === null, 'multiple active profiles must be unavailable');

    $unprofiled = pcv_identity_from_home_state([], false, ' Runa ');
    pcvCheck(($unprofiled['key'] ?? null) === hash('sha256', 'unprofiled:runa')
        && ($unprofiled['player_name'] ?? null) === 'Runa',
        'an absent optional profile table should use a normalized shared-server identity and core player name');
    $zeroProfiles = pcv_identity_from_home_state([
        'available' => true,
        'active_id' => 0,
        'playthroughs' => [],
    ], true, 'Runa');
    pcvCheck(($zeroProfiles['key'] ?? null) === hash('sha256', 'unprofiled:runa')
        && ($zeroProfiles['player_name'] ?? null) === 'Runa',
        'a verified zero-active-profile result should use shared-server identity');
    pcvCheck(pcv_identity_from_home_state([], false, 'Other Player')['key'] !== $unprofiled['key'],
        'shared-server settings must not cross validated core player identities');
    pcvCheck(pcv_identity_from_home_state([], false, null)['key'] === null,
        'shared-server identity must fail closed without a current core player name');
    pcvCheck(pcv_identity_from_home_state([], false, 'Run' . "\n" . 'a')['key'] === null,
        'shared-server identity must reject control characters in the core player name');
    pcvCheck(pcv_identity_from_home_state(['available' => false], true, 'Runa')['key'] === null,
        'an unavailable profile lookup must not fall back to shared-server identity');
    pcvCheck(pcv_identity_from_home_state([
        'available' => true,
        'active_id' => 0,
        'playthroughs' => [['id' => 17, 'active' => true, 'character_id' => $validCharacterId, 'player_name' => 'Runa']],
    ], true, 'Runa')['key'] === null,
        'an inconsistent active-profile result must not be relabelled shared-server');

    $key = $identity['key'];
    pcv_log_set_playthrough_ref($key);
    $otherKey = hash('sha256', 'another active playthrough');
    $knownNpcs = ['101' => 'Aela the Huntress', '202' => 'Faendal'];
    $a = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'exclude'];
    $b = ['enabled' => true, 'scene_mode' => 'pair', 'actor_a' => '202', 'actor_b' => '101', 'exclude_player' => false, 'bystander_mode' => 'silent'];

    ini_set('error_log', $logFixture . '/fallback.log');
    pcvCheck(@mkdir($isolatedLockState, 0700), 'could not create isolated lock-contention state directory');
    $logLock = fopen($logFixture . '/events.lock', 'c');
    pcvCheck(is_resource($logLock) && flock($logLock, LOCK_EX | LOCK_NB), 'could not hold logger lock for state-transition check');
    chmod($logFixture . '/events.lock', 0600);
    $transitionDuringLogFailure = pcv_stage($otherKey, $a, $knownNpcs, $isolatedLockState);
    flock($logLock, LOCK_UN);
    fclose($logLock);
    pcvCheckState($transitionDuringLogFailure, 'pending', null, true,
        'scope staging must succeed while logger lock is held', $a);
    pcvCleanFixture($isolatedLockState);

    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'missing state');
    $stagedA = pcv_stage($key, $a, $knownNpcs, $fixture);
    pcvCheckState($stagedA, 'pending', null, true, 'new config stages without activating', $a);
    $configA = $stagedA['pending_config_id'] ?? null;
    pcvCheck(is_string($configA) && pcv_log_valid_uuid($configA), 'staged config should persist a UUID correlation ID');
    pcvCheck(($stagedA['config_id'] ?? null) === null, 'pending config is not reported as active');
    pcvCheckState(pcv_begin_request($key, false, $fixture), 'pending', null, true, 'ineligible request', $a);

    $activeA = $a;
    $activatedA = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($activatedA, 'active', $activeA, false, 'eligible request applies pending config');
    pcvCheck(($activatedA['config_id'] ?? null) === $configA, 'activation should preserve the staged config ID');
    pcvCheck(($activatedA['pending_config_id'] ?? null) === null, 'activated config is no longer pending');
    pcvCheck((pcv_read($key, $fixture)['config_id'] ?? null) === $configA, 'read should preserve the active config ID');
    pcvCheckState(pcv_read($otherKey, $fixture), 'off', null, false, 'different playthrough must not read active scope');

    $stagedB = pcv_stage($key, $b, $knownNpcs, $fixture);
    pcvCheckState($stagedB, 'active', $activeA, true, 'pending replacement leaves active scope unchanged', $b);
    $configB = $stagedB['pending_config_id'] ?? null;
    pcvCheck(is_string($configB) && pcv_log_valid_uuid($configB) && $configB !== $configA, 'replacement staging should receive a distinct config ID');
    pcvCheck(($stagedB['config_id'] ?? null) === $configA, 'staging must preserve current active config ID');
    pcvCheckState(pcv_begin_request($key, false, $fixture, $knownNpcs), 'active', $activeA, true, 'rechat leaves pending config untouched', $b);
    $activatedB = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($activatedB, 'active', $b, false, 'next eligible request applies replacement');
    pcvCheck(($activatedB['config_id'] ?? null) === $configB, 'replacement activation should preserve its staged ID');

    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    unset($stored['active']['config_id']);
    unset($stored['active']['config']['scene_mode']);
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    $legacyActive = pcv_read($key, $fixture);
    pcvCheckState($legacyActive, 'active', $b, false, 'legacy v1 pair state without config ID or scene mode remains active');
    pcvCheck(($legacyActive['config_id'] ?? null) === null, 'legacy active state is surfaced as uncorrelated');
    $stored['active']['config_id'] = 'malformed-id';
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    $malformedOptionalId = pcv_read($key, $fixture);
    pcvCheckState($malformedOptionalId, 'active', $b, false, 'malformed optional ID does not invalidate legacy scope');
    pcvCheck(($malformedOptionalId['config_id'] ?? null) === null, 'malformed optional ID should normalize to null');

    $pcvStageDisabled = ['enabled' => false];
    pcv_stage($key, $a, $knownNpcs, $fixture);
    pcvCheckState(pcv_read($key, $fixture), 'active', $b, true,
        'END fixture has both an active scene and a staged replacement', $a);
    $stateBeforeInvalidEnd = (string)file_get_contents($fixture . '/state.json');
    pcvCheckState(pcv_stage('invalid', $pcvStageDisabled, [], $fixture), 'unavailable', null, false,
        'invalid-key END must fail closed');
    pcvCheck((string)file_get_contents($fixture . '/state.json') === $stateBeforeInvalidEnd,
        'invalid-key END must leave active and pending state untouched');
    $ended = pcv_stage($key, $pcvStageDisabled, [], $fixture);
    pcvCheckState($ended, 'off', null, false, 'END clears active and pending state without another input');
    pcvCheck(($ended['config_id'] ?? null) === null && ($ended['pending_config_id'] ?? null) === null,
        'immediate END should return no active or pending config ID');
    $endedStore = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    pcvCheck(($endedStore['key'] ?? null) === $key
        && array_key_exists('active', $endedStore) && $endedStore['active'] === null
        && array_key_exists('pending', $endedStore) && $endedStore['pending'] === null,
        'immediate END must atomically preserve identity while clearing both stored slots');
    pcvCheckState(pcv_begin_request($key, true, $fixture), 'off', null, false,
        'a later eligible input cannot revive an immediately ended scene');
    pcvCheckState(pcv_read($otherKey, $fixture), 'off', null, false,
        'immediate END state must remain isolated from a different identity');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    $beforeSwitch = pcv_begin_request($key, true, $fixture, $knownNpcs);
    pcvCheckState($beforeSwitch, 'active', $a, false, 'reactivate before playthrough switch');
    $beforeSwitchId = $beforeSwitch['config_id'] ?? null;
    pcvCheckState(pcv_begin_request($otherKey, false, $fixture), 'off', null, false, 'request for another playthrough invalidates prior state');
    pcvCheckState(pcv_begin_request($key, true, $fixture), 'off', null, false, 'switching back cannot revive stale active state');

    $entriesBeforeCorruption = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $invalidationLogged = false;
    foreach ($entriesBeforeCorruption as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_invalidated'
            && ($entry['reason'] ?? null) === 'playthrough_changed'
            && ($entry['context']['active_config_id'] ?? null) === $beforeSwitchId) {
            $invalidationLogged = true;
            break;
        }
    }
    pcvCheck($invalidationLogged, 'playthrough reset should log the prior active config ID');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    pcv_begin_request($key, true, $fixture, $knownNpcs);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    $expiredAt = time() - 1;
    $stored['active']['activated_at'] = $expiredAt - 3600;
    $stored['active']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_stage($key, $b, $knownNpcs, $fixture), 'pending', null, true,
        'staging clears expired active scope', $b);
    $entriesAfterExpiry = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $stageExpiryLogged = false;
    foreach ($entriesAfterExpiry as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_expired'
            && ($entry['reason'] ?? null) === 'active_ttl'
            && ($entry['context']['target'] ?? null) === 'active') {
            $stageExpiryLogged = true;
            break;
        }
    }
    pcvCheck($stageExpiryLogged, 'staging should record expired active scope after successful write');

    pcv_stage($key, $a, $knownNpcs, $fixture);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    $expiredAt = time() - 1;
    $stored['pending']['staged_at'] = $expiredAt - 900;
    $stored['pending']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'expired pending state is not reported as armed');
    pcvCheckState(pcv_begin_request($key, false, $fixture), 'off', null, false, 'ineligible request clears but never applies expired pending state');

    $invalidPairs = [
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => 'missing', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => '101', 'exclude_player' => true, 'bystander_mode' => 'exclude'],
        ['enabled' => true, 'actor_a' => '101', 'actor_b' => '202', 'exclude_player' => true, 'bystander_mode' => 'include'],
    ];
    foreach ($invalidPairs as $bad) {
        try {
            pcv_stage($key, $bad, $knownNpcs, $fixture);
            throw new RuntimeException('invalid desired config was accepted');
        } catch (InvalidArgumentException) {
        }
    }
    try {
        pcv_stage($key, array_replace($a, ['scene_mode' => 'unknown']), $knownNpcs, $fixture);
        throw new RuntimeException('unknown scene mode was accepted');
    } catch (InvalidArgumentException) {
    }

    pcv_stage($key, $a, $knownNpcs, $fixture);
    pcv_begin_request($key, true, $fixture, $knownNpcs);
    $stored = json_decode((string)file_get_contents($fixture . '/state.json'), true, 16, JSON_THROW_ON_ERROR);
    pcvCheck(!str_contains(json_encode($stored, JSON_THROW_ON_ERROR), 'Aela the Huntress'), 'display names must not be persisted');
    pcvCheck(is_file($fixture . '/state.lock'), 'all state operations must use the stable lock file');
    $expiredAt = time() - 1;
    $stored['active']['activated_at'] = $expiredAt - 3600;
    $stored['active']['expires_at'] = $expiredAt;
    file_put_contents($fixture . '/state.json', json_encode($stored, JSON_THROW_ON_ERROR));
    pcvCheckState(pcv_read($key, $fixture), 'off', null, false, 'expired active state');

    file_put_contents($fixture . '/state.json', '{broken json');
    pcvCheckState(pcv_read($key, $fixture), 'unavailable', null, false, 'corrupt state');
    pcvCheckState(pcv_begin_request($key, true, $fixture), 'unavailable', null, false, 'request boundary fails closed on corrupt state');
    $corruptBytes = (string)file_get_contents($fixture . '/state.json');
    pcvCheckState(pcv_stage($key, $a, $knownNpcs, $fixture), 'unavailable', null, false, 'staging fails closed on corrupt state');
    pcvCheck((string)file_get_contents($fixture . '/state.json') === $corruptBytes, 'staging must not overwrite corrupt state');

    $sceneFixture = $fixture . '-scene-mode';
    $soloInput = [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => '101',
        'actor_b' => ['ignored in solo mode'],
        'exclude_player' => false,
        'bystander_mode' => 'silent',
    ];
    $solo = [
        'enabled' => true,
        'scene_mode' => 'solo',
        'actor_a' => '101',
        'actor_b' => null,
        'exclude_player' => true,
        'bystander_mode' => 'silent',
    ];
    $legacyPair = [
        'enabled' => true,
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ];
    $legacyPairState = pcv_stage($key, $legacyPair, $knownNpcs, $sceneFixture);
    $canonicalPair = [
        'enabled' => true,
        'scene_mode' => 'pair',
        'actor_a' => '101',
        'actor_b' => '202',
        'exclude_player' => true,
        'bystander_mode' => 'exclude',
    ];
    pcvCheck(($legacyPairState['pending_scope'] ?? null) === $canonicalPair,
        'new configurations without a mode should be stored and exposed as pair');
    $soloStaged = pcv_stage($key, $soloInput, $knownNpcs, $sceneFixture);
    pcvCheck(($soloStaged['pending_scope'] ?? null) === $solo,
        'solo staging should discard actor B and force player exclusion');
    $soloActivated = pcv_begin_request($key, true, $sceneFixture, ['101' => 'Aela']);
    pcvCheck(($soloActivated['status'] ?? null) === 'active'
        && ($soloActivated['scope'] ?? null) === $solo,
        'solo activation should require only actor A in the current eligible map');
    $soloConfigId = $soloActivated['config_id'] ?? null;
    $sameKeyStale = pcv_begin_request($key, false, $sceneFixture, []);
    pcvCheck(($sameKeyStale['status'] ?? null) === 'unavailable'
        && ($sameKeyStale['config_id'] ?? null) === $soloConfigId,
        'same-key state should remain configured while stale or empty eligibility prevents use');
    $sameKeyFresh = pcv_begin_request($key, false, $sceneFixture, ['101' => 'Aela']);
    pcvCheck(($sameKeyFresh['status'] ?? null) === 'active'
        && ($sameKeyFresh['scope'] ?? null) === $solo
        && ($sameKeyFresh['config_id'] ?? null) === $soloConfigId,
        'same-key state should be usable again when the current actor evidence is fresh');
    $sceneEvents = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file((string)pcv_log_path(), FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $ineligibleSkips = array_values(array_filter($sceneEvents, static fn(array $entry): bool =>
        ($entry['event'] ?? null) === 'state.scope_skipped'
        && ($entry['reason'] ?? null) === 'scene_not_eligible'));
    pcvCheck(count($ineligibleSkips) === 1 && ($ineligibleSkips[0]['severity'] ?? null) === 'info',
        'expected ineligibility should emit one informational skip');
    pcvCleanFixture($sceneFixture);

    $logPath = pcv_log_path();
    pcvCheck(is_string($logPath) && is_file($logPath), 'state lifecycle events should use the isolated log fixture');
    $entries = array_map(
        static fn(string $line) => json_decode($line, true, 16, JSON_THROW_ON_ERROR),
        file($logPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES)
    );
    $endEventLogged = false;
    foreach ($entries as $entry) {
        if (($entry['event'] ?? null) === 'state.scope_ended'
            && ($entry['context']['action'] ?? null) === 'end') {
            $endEventLogged = true;
            break;
        }
    }
    pcvCheck($endEventLogged, 'immediate END should use the dedicated scope-ended event with action=end');
    pcvCheck(!str_contains((string)file_get_contents($logPath), 'Aela the Huntress'), 'operational logs must not contain NPC names');
    pcvCheck(!str_contains((string)file_get_contents($logPath), $key), 'operational logs must not contain the state key');

    echo "PASS: identity, config correlation, legacy compatibility, invalidation/expiry logs, corrupt-state preservation and isolated state logs\n";
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    $exitCode = 1;
} finally {
    pcvCleanFixture($fixture);
    pcvCleanFixture($isolatedLockState);
    if (isset($migrationRoot)) {
        pcvCleanMigrationFixture($migrationRoot);
    }
    if (isset($corruptRoot)) {
        pcvCleanMigrationFixture($corruptRoot);
    }
    if (!$privateRootExistedBefore && is_string($privateRootBefore)) {
        @rmdir($privateRootBefore);
    }
    pcvCleanLogFixture($logFixture);
    if (is_string($oldErrorLog)) {
        ini_set('error_log', $oldErrorLog);
    }
}

exit($exitCode ?? 0);
