<?php
declare(strict_types=1);

function directCapacityCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function directCapacityRemove(string $path): void
{
    if (!str_starts_with($path, sys_get_temp_dir() . DIRECTORY_SEPARATOR) || !is_dir($path) || is_link($path)) {
        return;
    }
    $items = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($path, FilesystemIterator::SKIP_DOTS), RecursiveIteratorIterator::CHILD_FIRST);
    foreach ($items as $item) {
        $item->isDir() && !$item->isLink() ? @rmdir($item->getPathname()) : @unlink($item->getPathname());
    }
    @rmdir($path);
}

$testRoot = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'pcv_direct_ack_capacity_' . bin2hex(random_bytes(8));
$server = $testRoot . '/webroot/HerikaServer/ext/private_conversation';
$mindPoisoning = $testRoot . '/webroot/HerikaServer/ext/mind_poisoning';
$logs = $testRoot . '/logs';
directCapacityCheck(mkdir($server, 0700, true) && mkdir($mindPoisoning, 0700, true) && mkdir($logs, 0700),
    'Create isolated direct-ACK capacity fixture directories.');
$source = dirname(__DIR__) . '/server';
foreach (['reflection.php', 'reflection_receipt.php', 'log.php'] as $file) {
    directCapacityCheck(copy($source . '/' . $file, $server . '/' . $file), 'Copy isolated ' . $file . '.');
}

$stateSource = <<<'PHP'
<?php
function pcv_valid_key($key): bool { return is_string($key) && preg_match('/\A[a-f0-9]{64}\z/D', $key) === 1; }
function pcv_state_directory(?string $directory): string
{
    $path = $directory ?? (__DIR__ . '/state');
    if (!is_dir($path) && !mkdir($path, 0700, true)) { throw new RuntimeException('Could not create fake state directory.'); }
    return $path;
}
function pcv_lock_state(string $directory, bool $create, int $mode) { return (object)[]; }
function pcv_unlock_state($handle): void {}
function pcv_load_store(string $directory): array
{
    return ['kind' => 'ready', 'state' => [
        'key' => str_repeat('a', 64),
        'active' => ['config' => ['enabled' => true, 'scene_mode' => 'solo', 'actor_a' => '11', 'actor_b' => null, 'exclude_player' => true], 'config_id' => '123e4567-e89b-42d3-a456-426614174000', 'expires_at' => time() + 60],
        'pending' => null,
    ]];
}
function pcv_current_identity(bool $refresh = false): array { return ['key' => str_repeat('a', 64), 'player_name' => 'Player']; }
function pcv_current_player_name(): string { return 'Player'; }
function pcvReadResolvedScope(): array { return $GLOBALS['pcv_test_scope']; }
function pcv_scope_name_key(string $name): string { return strtolower(trim($name)); }
function chimInteractionState(): array { return ['enabled' => true, 'generation' => 1]; }
function chimInteractionBegin(): void { $GLOBALS['chim_interaction_generation'] ??= 1; }
PHP;
$scopeSource = <<<'PHP'
<?php
PHP;
$moduleSource = <<<'PHP'
<?php
namespace ChimMindPoisoning;
const MIND_POISONING_REFLECTION_API_VERSION = 1;
interface StoreDb { public function activePlaythrough(): ?array; }
function reflectionAckPayload(array $request): ?array
{
    try { $payload = json_decode($request[3] ?? '', true, 8, JSON_THROW_ON_ERROR); }
    catch (\Throwable) { return null; }
    return is_array($payload) ? $payload : null;
}
function reflectionPlayerTransport(string $listener, string $player): bool
{
    return in_array(strtolower(trim($listener)), [strtolower(trim($player)), 'player', 'the player', 'dragonborn', 'the dragonborn'], true);
}
function reflectionSourceParts(...$args): array { return []; }
function mindPoisoningEvaluateReflection(...$args): string
{
    $GLOBALS['pcv_provider_calls']++;
    [$registration, $gameRequest, $store, $revalidate] = $args;
    if (!is_callable($revalidate) || !$revalidate($registration, 'pre_model') || !$revalidate($registration, 'transaction')) {
        return 'stale';
    }
    return 'committed';
}
class RequestLog { public function __construct() {} }
class PostgresStoreDb implements StoreDb
{
    public function __construct() { $GLOBALS['pcv_store_constructions']++; }
    public function activePlaythrough(): ?array { return ['id' => '1', 'player_name' => 'Player']; }
}
PHP;
directCapacityCheck(file_put_contents($server . '/state.php', $stateSource) === strlen($stateSource)
    && file_put_contents($server . '/scope.php', $scopeSource) === strlen($scopeSource)
    && file_put_contents($mindPoisoning . '/reflection.php', $moduleSource) === strlen($moduleSource),
    'Write isolated state, scope, and compatible Mind Poisoning fixtures.');

$runner = $testRoot . '/runner.php';
$runnerSource = <<<'PHP'
<?php
declare(strict_types=1);
define('PCV_LOG_TESTING', true);
require __SERVER__ . '/reflection.php';
if (!pcv_log_set_test_directory(__LOGS__)) { throw new RuntimeException('Could not isolate PCV logs.'); }
$GLOBALS['pcv_provider_calls'] = 0;
$GLOBALS['pcv_store_constructions'] = 0;
$GLOBALS['chim_interaction_generation'] = 1;
$GLOBALS['pcv_test_scope'] = [
    'status' => 'active',
    'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'actor_a_id' => '11',
    'scope' => ['scene_mode' => 'solo', 'actor_a' => 'Aela', 'actor_b' => null, 'exclude_player' => true],
];
$directory = pcv_state_directory(null);
$scope = pcv_reflection_current_solo_scope();
$targetId = 'utt_1234567890abcdef';
$targetSpeech = 'private test line';
$targetRequest = ['_speech', '', '', json_encode([
    'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => $targetSpeech, 'utterance_id' => $targetId,
], JSON_THROW_ON_ERROR)];
$record = [
    'version' => 2, 'pcv_key' => str_repeat('a', 64), 'config_id' => '123e4567-e89b-42d3-a456-426614174000',
    'actor_id' => 11, 'actor_name' => 'Aela', 'origin_request_type' => 'inputtext', 'origin_mode' => 'STANDARD',
    'route' => 'solo_reflection', 'created_at' => time(), 'status' => 'registered', 'claim_token' => null,
    'source_generation' => 1, 'ack_receipt' => null,
    'registration' => [
        'event_id' => 200, 'utterance_id' => $targetId, 'actor_id' => 11, 'actor_name' => 'Aela',
        'playthrough_id' => '1', 'config_id' => '123e4567-e89b-42d3-a456-426614174000',
        'rechat_target_hint' => 'explicit_disable_rechat', 'speech_hash' => hash('sha256', $targetSpeech),
    ],
];
$handle = pcv_lock_state($directory, true, LOCK_EX);
try { pcv_reflection_write_locked($directory, $record); }
finally { pcv_unlock_state($handle); }
for ($index = 0; $index < PCV_REFLECTION_RECEIPT_MAX_COUNT; $index++) {
    $id = sprintf('utt_receipt%08d', $index);
    $tuple = pcv_reflection_ack_tuple(['_speech', '', '', json_encode([
        'speaker' => 'Aela', 'listener' => 'Dragonborn', 'speech' => 'pending candidate', 'utterance_id' => $id,
    ], JSON_THROW_ON_ERROR)]);
    $stored = pcv_reflection_store_ack_receipt($tuple, $scope, 1, $directory);
    if (($stored['kind'] ?? null) !== 'ready') { throw new RuntimeException('Could not fill pending receipt map.'); }
}
$before = json_decode((string)file_get_contents($directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE), true, 16, JSON_THROW_ON_ERROR);
if (count($before['receipts'] ?? []) !== PCV_REFLECTION_RECEIPT_MAX_COUNT) { throw new RuntimeException('Receipt map did not reach its configured capacity.'); }
pcvReflectionEvaluateAck($targetRequest);
$after = json_decode((string)file_get_contents($directory . DIRECTORY_SEPARATOR . PCV_REFLECTION_RECEIPT_FILE), true, 16, JSON_THROW_ON_ERROR);
$registry = pcv_reflection_read_locked($directory);
if ($GLOBALS['pcv_provider_calls'] !== 1 || $GLOBALS['pcv_store_constructions'] !== 1
    || count($after['receipts'] ?? []) !== PCV_REFLECTION_RECEIPT_MAX_COUNT
    || ($registry['record']['status'] ?? null) !== 'consumed'
    || ($registry['record']['registration']['utterance_id'] ?? null) !== $targetId) {
    throw new RuntimeException('A known registered ACK must evaluate without consuming or depending on pending receipt capacity.');
}
if (count(array_filter($after['receipts'], static fn(array $receipt): bool => $receipt['utterance_id'] === $targetId)) !== 0) {
    throw new RuntimeException('A direct ACK must not be stored as a recovery receipt.');
}
echo "known registered ACK evaluated with eight unrelated receipts pending\n";
PHP;
$runnerSource = str_replace(['__SERVER__', '__LOGS__'], [var_export($server, true), var_export($logs, true)], $runnerSource);
directCapacityCheck(file_put_contents($runner, $runnerSource) === strlen($runnerSource), 'Write isolated direct-capacity runner.');
$process = proc_open([PHP_BINARY, $runner], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes);
directCapacityCheck(is_resource($process), 'Start isolated direct-capacity runner.');
fclose($pipes[0]);
$stdout = stream_get_contents($pipes[1]);
$stderr = stream_get_contents($pipes[2]);
fclose($pipes[1]);
fclose($pipes[2]);
$exitCode = proc_close($process);
directCapacityCheck($exitCode === 0, 'The direct-capacity runner failed: ' . $stderr);
directCapacityCheck($stdout === "known registered ACK evaluated with eight unrelated receipts pending\n",
    'Unexpected direct-capacity output: ' . $stdout);
directCapacityRemove($testRoot);
echo "PCV direct ACK receipt-capacity check passed.\n";
