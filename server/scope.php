<?php
declare(strict_types=1);

require_once __DIR__ . '/log.php';

/** Return a display-name key suitable for routing comparisons. */
function pcv_scope_name_key(string $name): string
{
    $name = trim($name);
    return function_exists('mb_strtolower') ? mb_strtolower($name, 'UTF-8') : strtolower($name);
}

/** Build the request-time allowlist from the live NPC catalog; ambiguous names are omitted. */
function pcvScopeKnownNpcs(array $rows, ?string $playerName): array
{
    $candidates = [];
    $idCounts = [];
    $nameCounts = [];
    $playerKey = is_string($playerName) && trim($playerName) !== '' ? pcv_scope_name_key($playerName) : '';
    $reserved = ['player', 'the player', 'player character', 'the player character', 'dragonborn', 'the dragonborn', 'the narrator', 'explicit_disable_rechat'];

    foreach ($rows as $row) {
        if (!is_array($row)) {
            continue;
        }
        $rawId = $row['id'] ?? null;
        if (is_int($rawId) && $rawId > 0) {
            $id = (string)$rawId;
        } elseif (is_string($rawId) && preg_match('/\A[1-9][0-9]*\z/D', $rawId) === 1
            && filter_var($rawId, FILTER_VALIDATE_INT) !== false) {
            $id = $rawId;
        } else {
            continue;
        }
        $profileId = filter_var($row['profile_id'] ?? null, FILTER_VALIDATE_INT);
        $name = $row['npc_name'] ?? null;
        if ($profileId === false || $profileId < 1 || !is_string($name)) {
            continue;
        }
        $name = trim($name);
        $nameKey = pcv_scope_name_key($name);
        if ($name === '' || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1f\x7f|]/', $name) === 1
            || $nameKey === $playerKey || in_array($nameKey, $reserved, true)) {
            continue;
        }
        $candidates[] = ['id' => $id, 'name' => $name, 'name_key' => $nameKey];
        $idCounts[$id] = ($idCounts[$id] ?? 0) + 1;
        $nameCounts[$nameKey] = ($nameCounts[$nameKey] ?? 0) + 1;
    }

    $known = [];
    foreach ($candidates as $candidate) {
        if ($idCounts[$candidate['id']] === 1 && $nameCounts[$candidate['name_key']] === 1) {
            $known[$candidate['id']] = $candidate['name'];
        }
    }
    return $known;
}

/** Intersect validated presence names with the current unambiguous NPC catalog. */
function pcvScopeEligibleMapFromPresence(array $actors, array $rows, ?string $playerName): array
{
    if (!is_string($playerName) || trim($playerName) === '') {
        return [];
    }

    $nameCounts = [];
    foreach ($actors as $actor) {
        $name = is_array($actor) && is_string($actor['name'] ?? null) ? trim($actor['name']) : '';
        if ($name === '' || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            continue;
        }
        $key = pcv_scope_name_key($name);
        $nameCounts[$key] = ($nameCounts[$key] ?? 0) + 1;
    }

    $eligible = [];
    foreach (pcvScopeKnownNpcs($rows, $playerName) as $id => $name) {
        if (($nameCounts[pcv_scope_name_key($name)] ?? 0) === 1) {
            $eligible[(string)$id] = $name;
        }
    }
    return $eligible;
}

/**
 * Catalog IDs of the eligible NPCs ordered nearest first from the input's presence actors (free scenes).
 * Ties keep the lower catalog ID first.
 */
function pcvScopeFreeCandidateOrder(array $actors, array $eligibleMap): array
{
    $idByName = [];
    foreach ($eligibleMap as $id => $name) {
        $idByName[pcv_scope_name_key((string)$name)] = (string)$id;
    }
    $candidates = [];
    foreach ($actors as $actor) {
        $name = is_array($actor) && is_string($actor['name'] ?? null) ? $actor['name'] : '';
        $distance = is_array($actor) && (is_int($actor['distance'] ?? null) || is_float($actor['distance'] ?? null))
            ? (float)$actor['distance'] : null;
        $id = $idByName[pcv_scope_name_key($name)] ?? null;
        if ($id !== null && $distance !== null && !isset($candidates[$id])) {
            $candidates[$id] = $distance;
        }
    }
    $ids = array_map('strval', array_keys($candidates));
    usort($ids, static fn(string $x, string $y): int => ($candidates[$x] <=> $candidates[$y])
        ?: (strlen($x) <=> strlen($y) ?: strcmp($x, $y)));
    return $ids;
}

function pcvRoutingLogType(string $requestType): string
{
    return in_array($requestType, pcv_log_enum_values('request_type'), true) ? $requestType : 'other';
}

function pcvRoutingLogMode(string $mode): string
{
    $mode = strtolower($mode);
    return in_array($mode, ['standard', 'close', 'whisper', 'autochat'], true) ? $mode : 'other';
}

/** Mirror the installed core's late player-respeech shortcut without changing its mode. */
function pcvEffectiveExecutionMode(?array $request = null): string
{
    $mode = strtoupper((string)($GLOBALS['CHIM_EXECUTION_MODE'] ?? ''));
    $request ??= is_array($GLOBALS['gameRequest'] ?? null) ? $GLOBALS['gameRequest'] : [];
    $type = strtolower((string)($request[0] ?? ''));
    if ($mode !== 'STANDARD' || empty($GLOBALS['PLAYER_RESPEECH'])
        || !in_array($type, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)
        || !is_string($request[3] ?? null)) {
        return $mode;
    }

    $raw = $request[3];
    $cleaned = preg_replace('/^[^:]+:/', '', $raw);
    if (!is_string($cleaned)) {
        return $mode;
    }
    $cleaned = addcslashes($cleaned, '"');
    return strpos($raw, '**') === 0 || strpos($cleaned, '**') === 0 ? 'AUTOCHAT' : $mode;
}

/** Recheck the original request's effective mode after PCV has rewritten the event. */
function pcvRequestScopeModeMatches(array $requestScope): bool
{
    $originMode = strtoupper((string)($requestScope['origin_mode'] ?? ''));
    $requestType = strtolower((string)($requestScope['origin_request_type'] ?? ''));
    if ($originMode === '' || $requestType === '') {
        return false;
    }
    $dialogue = $requestScope['origin_dialogue'] ?? null;
    $originRequest = [$requestType, '', '', is_string($dialogue) ? $dialogue : ''];
    return pcvEffectiveExecutionMode($originRequest) === $originMode;
}

function pcvSoloReflectionRequest(array $requestScope): bool
{
    return ($requestScope['status'] ?? null) === 'active'
        && ($requestScope['scope']['scene_mode'] ?? null) === 'solo'
        && ($requestScope['scope']['actor_b'] ?? null) === null
        && ($requestScope['scope']['exclude_player'] ?? null) === true
        && ($requestScope['origin_mode'] ?? null) === 'STANDARD'
        && in_array($requestScope['origin_request_type'] ?? null, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)
        && ($requestScope['route'] ?? null) === 'solo_reflection';
}

/**
 * Opener for a group turn: the member named earliest in the direction (full name, or a first word that is unique
 * among the members; whole words, case-insensitive), else the picked opener, else the first member.
 * Returns ['name' => member name, 'source' => named|picker|first].
 */
function pcvGroupPickOpener(string $direction, array $members, ?string $pickerName, ?string $targetName = null,
    bool $free = false): array
{
    $members = array_values(array_filter($members, static fn($name) => is_string($name) && trim($name) !== ''));
    $firstWord = static function (string $name): string {
        $parts = preg_split('/\s+/u', trim($name));
        return is_array($parts) ? (string)$parts[0] : '';
    };
    $wordCounts = [];
    foreach ($members as $name) {
        $key = pcv_scope_name_key($firstWord($name));
        $wordCounts[$key] = ($wordCounts[$key] ?? 0) + 1;
    }
    $best = null;
    foreach ($members as $name) {
        $patterns = [trim($name)];
        $word = $firstWord($name);
        if ($word !== '' && $word !== trim($name) && ($wordCounts[pcv_scope_name_key($word)] ?? 0) === 1) {
            $patterns[] = $word;
        }
        foreach ($patterns as $pattern) {
            $regex = '/(?<![\p{L}\p{N}])' . preg_quote($pattern, '/') . '(?![\p{L}\p{N}])/iu';
            if (preg_match($regex, $direction, $match, PREG_OFFSET_CAPTURE) !== 1) {
                continue;
            }
            $position = $match[0][1];
            $length = strlen($pattern);
            if ($best === null || $position < $best['position'] || ($position === $best['position'] && $length > $best['length'])) {
                $best = ['name' => $name, 'position' => $position, 'length' => $length];
            }
        }
    }
    if ($best !== null) {
        return ['name' => $best['name'], 'source' => 'named'];
    }
    if ($free) {
        // Free scenes (0.1.12): the NPC the player is facing, if a member, else the nearest (first) member.
        foreach ($members as $name) {
            if (is_string($targetName) && pcv_scope_name_key($targetName) === pcv_scope_name_key($name)) {
                return ['name' => $name, 'source' => 'target'];
            }
        }
        return ['name' => $members[0] ?? '', 'source' => 'nearest'];
    }
    if (is_string($pickerName) && in_array($pickerName, $members, true)) {
        return ['name' => $pickerName, 'source' => 'picker'];
    }
    return ['name' => $members[0] ?? '', 'source' => 'first'];
}

/** The NPC the player targets directly, from CHIM's decoded routing snapshot (listener with target_mode direct). */
function pcvSnapshotDirectTarget(array $snapshot): ?string
{
    $listener = $snapshot['listener'] ?? null;
    return ($snapshot['target_mode'] ?? null) === 'direct' && is_string($listener) && trim($listener) !== ''
        ? trim($listener) : null;
}

/** Catalog IDs of a stored config; mirrors pcv_config_actor_ids for callers that load scope.php without state.php. */
function pcvScopeConfigActorIds(array $config): array
{
    if (function_exists('pcv_config_actor_ids')) {
        return pcv_config_actor_ids($config);
    }
    if (($config['scene_mode'] ?? 'pair') === 'solo') {
        return [$config['actor_a'] ?? null];
    }
    return is_array($config['actor_ids'] ?? null) ? array_values($config['actor_ids'])
        : [$config['actor_a'] ?? null, $config['actor_b'] ?? null];
}

/** Largest member count for a scope: six for free scenes (0.1.12), else four. */
function pcvScopeMaxMembers(array $scope): int
{
    if (($scope['free'] ?? false) === true) {
        return defined('PCV_FREE_MAX_MEMBERS') ? PCV_FREE_MAX_MEMBERS : 6;
    }
    return defined('PCV_GROUP_MAX_MEMBERS') ? PCV_GROUP_MAX_MEMBERS : 4;
}

/** Member names of a resolved scope: the group list, or A/B for scopes resolved before 0.1.11; solo: A. */
function pcvScopeMembers(array $scope): array
{
    if (is_array($scope['members'] ?? null)) {
        return array_values(array_filter($scope['members'], static fn($name) => is_string($name) && $name !== ''));
    }
    $members = [];
    foreach (['actor_a', 'actor_b'] as $key) {
        if (is_string($scope[$key] ?? null) && $scope[$key] !== '') {
            $members[] = $scope[$key];
        }
    }
    return $members;
}

function pcvPairRoutedRequest(array $requestScope): bool
{
    $scope = $requestScope['scope'] ?? null;
    $type = $requestScope['origin_request_type'] ?? null;
    $route = $requestScope['route'] ?? null;
    $members = is_array($scope) ? pcvScopeMembers($scope) : [];
    if (($requestScope['status'] ?? null) !== 'active' || !is_array($scope)
        || ($scope['scene_mode'] ?? null) !== 'pair'
        || count($members) < 2 || count($members) > pcvScopeMaxMembers($scope)
        || count(array_unique(array_map('pcv_scope_name_key', $members))) !== count($members)
        || !is_bool($scope['exclude_player'] ?? null)
        || ($requestScope['origin_mode'] ?? null) !== 'STANDARD') {
        return false;
    }
    if (in_array($type, ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'], true)) {
        return $route === ($scope['exclude_player'] ? 'scene_direction' : 'player_speech');
    }
    if ($type === 'rechat') {
        return $route === 'rechat_clamped';
    }
    if (in_array($type, ['continue', 'continue_group'], true)) {
        return $route === 'pair_continuation' && $scope['exclude_player'] === false;
    }
    return false;
}

function pcvRoutingLogStart(string $requestType): void
{
    if (!empty($GLOBALS['PCV_ROUTING_LOG_STARTED'])) {
        return;
    }
    $type = pcvRoutingLogType($requestType);
    $GLOBALS['PCV_ROUTING_LOG_STARTED'] = true;
    $GLOBALS['PCV_ROUTING_LOG_REQUEST_TYPE'] = $type;
    pcv_log_begin_request();
    pcv_log_set_request_type($type);
    pcv_log_event('routing.request_started', 'info', 'ok', null, ['request_type' => $type]);
}

function pcvRoutingLogCurrentType(): string
{
    $type = $GLOBALS['PCV_ROUTING_LOG_REQUEST_TYPE'] ?? ($GLOBALS['gameRequest'][0] ?? 'other');
    return pcvRoutingLogType(is_string($type) ? $type : 'other');
}

function pcvRoutingLogSetState(array $state): void
{
    pcv_log_set_config_id(is_string($state['config_id'] ?? null) ? $state['config_id'] : null);
    $GLOBALS['PCV_ROUTING_LOG_ACTOR_IDS'] = [
        'actor_a_id' => is_string($state['actor_a_id'] ?? null) ? $state['actor_a_id'] : null,
        'actor_b_id' => is_string($state['actor_b_id'] ?? null) ? $state['actor_b_id'] : null,
    ];
}

function pcvRoutingLogActorContext(?array $scopeState = null): array
{
    $ids = is_array($scopeState) ? $scopeState : ($GLOBALS['PCV_ROUTING_LOG_ACTOR_IDS'] ?? []);
    $context = [];
    foreach (['actor_a_id', 'actor_b_id'] as $key) {
        if (is_string($ids[$key] ?? null)) {
            $context[$key] = $ids[$key];
        }
    }
    return $context;
}

function pcvRoutingLogSpeakerId(?string $speaker, ?array $scopeState = null): ?string
{
    if (!is_string($speaker) || !is_array($scopeState)) {
        return null;
    }
    foreach (is_array($scopeState['member_ids'] ?? null) ? $scopeState['member_ids'] : [] as $memberName => $memberId) {
        if (is_string($memberName) && strcasecmp(trim($speaker), $memberName) === 0 && is_string($memberId)) {
            return $memberId;
        }
    }
    if (strcasecmp(trim($speaker), (string)($scopeState['scope']['actor_a'] ?? '')) === 0
        && is_string($scopeState['actor_a_id'] ?? null)) {
        return $scopeState['actor_a_id'];
    }
    if (strcasecmp(trim($speaker), (string)($scopeState['scope']['actor_b'] ?? '')) === 0
        && is_string($scopeState['actor_b_id'] ?? null)) {
        return $scopeState['actor_b_id'];
    }
    return null;
}

function pcvRoutingLogDetail(string $phase, string $decision, string $requestType, ?array $scopeState = null, array $counts = []): void
{
    $context = [
        'phase' => $phase,
        'decision' => $decision,
        'request_type' => pcvRoutingLogType($requestType),
    ] + pcvRoutingLogActorContext($scopeState);
    $speakerId = pcvRoutingLogSpeakerId($GLOBALS['HERIKA_NAME'] ?? null, $scopeState);
    if ($speakerId !== null) {
        $context['speaker_id'] = $speakerId;
    }
    foreach (['audience_before_count', 'audience_after_count', 'present_before_count', 'present_after_count'] as $key) {
        if (is_int($counts[$key] ?? null)) {
            $context[$key] = $counts[$key];
        }
    }
    pcv_log_event('routing.request_detail', 'debug', 'ok', 'debug_detail', $context);
}

function pcvRoutingAudienceCount($audience): int
{
    if (!is_string($audience)) {
        return 0;
    }
    return count(array_filter(array_map('trim', explode('|', $audience)), static fn(string $name): bool => $name !== ''));
}

function pcvRoutingPresenceCount($presentActors): int
{
    return is_array($presentActors) ? count($presentActors) : 0;
}

function pcvRoutingLogException(string $phase, Throwable $error, ?array $scopeState = null): void
{
    $context = ['phase' => $phase, 'request_type' => pcvRoutingLogCurrentType()]
        + pcvRoutingLogActorContext($scopeState);
    if (empty($GLOBALS['PCV_ROUTING_LOG_TERMINAL'])) {
        $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
        pcv_log_set_terminal('failed', 'hook_exception', $context);
        pcv_log_exception('routing.request_error', 'error', 'failed', 'hook_exception', $error, $context);
    }
}

/** Resolve the current active state without changing a pending configuration. */
function pcvReadResolvedScope(): array
{
    $key = pcv_current_playthrough_key();
    if ($key === null) {
        return pcvScopeStoredStateExists()
            ? ['status' => 'unavailable', 'scope' => null, 'profile_id_a' => null, 'config_id' => null, 'actor_a_id' => null, 'actor_b_id' => null]
            : ['status' => 'identity_unavailable', 'scope' => null, 'profile_id_a' => null, 'config_id' => null, 'actor_a_id' => null, 'actor_b_id' => null];
    }
    $state = pcv_read($key);
    if (($state['status'] ?? null) !== 'active') {
        return pcvResolveLiveScopeState($state);
    }

    try {
        $playerName = pcv_current_player_name();
        if (!is_string($playerName) || trim($playerName) === '') {
            return pcvResolveLiveScopeState(array_replace($state, ['status' => 'unavailable', 'scope' => null]));
        }
        $rows = pcvScopeLoadNpcCatalog();
        $presence = pcv_read_eligible_npcs($key, $rows, $playerName);
        if (!in_array($presence['status'] ?? null, ['ready', 'empty'], true)) {
            $reason = pcvScopePresenceFailureReason($presence);
            if (in_array($reason, ['presence_missing', 'presence_stale'], true)) {
                pcvScopeLogSceneIneligible('read', $state['scope'] ?? []);
                $reason = 'scene_not_eligible';
            } elseif ($reason !== 'presence_unavailable' && $reason !== 'presence_key_mismatch') {
                pcv_log_event('state.unavailable', 'error', 'unavailable', $reason, ['operation' => 'begin']);
            }
            return pcvResolveLiveScopeState(array_replace($state, ['status' => 'unavailable', 'scope' => null, 'reason' => $reason]));
        }
        $eligibleMap = $presence['known_npcs'] ?? [];
        // 0.1.13: an active scene's reflection check accepts routing's in-scene evidence while reports are fresh.
        if (is_array($state['scope'] ?? null)) {
            $sceneIds = array_values(array_filter(pcv_config_actor_ids($state['scope']), 'is_string'));
            $eligibleMap = pcvScopeAckEligibleMap($eligibleMap, pcv_read_active_scene_npcs($key, $rows, $playerName, $sceneIds));
        }
        $resolved = pcvResolveLiveScopeState($state, $rows, $playerName, $eligibleMap);
        if (($resolved['status'] ?? null) === 'unavailable'
            && is_array($state['scope'] ?? null)
            && !pcv_config_actors_are_eligible($state['scope'], $eligibleMap)) {
            pcvScopeLogSceneIneligible('read', $state['scope']);
            $resolved['reason'] = 'scene_not_eligible';
        }
        return $resolved;
    } catch (Throwable $error) {
        pcv_log_exception('state.unavailable', 'error', 'unavailable', 'catalog_unavailable', $error, ['operation' => 'begin']);
        return pcvResolveLiveScopeState(array_replace($state, ['status' => 'unavailable', 'scope' => null, 'reason' => 'catalog_unavailable']));
    }
}

/**
 * The presence map for checks of an already active scene outside routing (reflection ACKs): the strict map plus
 * routing's in-scene evidence, but only when the close report is fresh. A heartbeat gap keeps the strict map.
 */
function pcvScopeAckEligibleMap(array $strictMap, array $inScene): array
{
    if (($inScene['report_fresh'] ?? false) !== true || ($inScene['error'] ?? false) === true) {
        return $strictMap;
    }
    return $strictMap + (is_array($inScene['known_npcs'] ?? null) ? $inScene['known_npcs'] : []);
}

/** Resolve active state and apply pending changes only when the caller marks an eligible input. */
function pcvBeginResolvedScope(bool $eligible, ?array $currentPresence = null, bool $usePresenceCache = false): array
{
    $key = pcv_current_playthrough_key();
    if ($key === null) {
        return pcvScopeStoredStateExists()
            ? ['status' => 'unavailable', 'scope' => null, 'profile_id_a' => null, 'config_id' => null, 'actor_a_id' => null, 'actor_b_id' => null]
            : ['status' => 'identity_unavailable', 'scope' => null, 'profile_id_a' => null, 'config_id' => null, 'actor_a_id' => null, 'actor_b_id' => null];
    }

    $observed = pcv_read($key);
    $pendingScope = is_array($observed['pending_scope'] ?? null) ? $observed['pending_scope'] : null;
    $pendingEnd = $eligible && is_array($pendingScope) && ($pendingScope['enabled'] ?? null) === false;
    $active = ($observed['status'] ?? null) === 'active';
    $pendingArm = $eligible && is_array($pendingScope) && ($pendingScope['enabled'] ?? null) === true;
    $needsEligibility = !$pendingEnd && ($active || $pendingArm);
    $rows = null;
    $playerName = null;
    $eligibleMap = null;
    $freeOrder = null;
    $failureReason = null;

    if ($needsEligibility) {
        if ($usePresenceCache) {
            try {
                $playerName = pcv_current_player_name();
                if (is_string($playerName) && trim($playerName) !== '') {
                    $rows = pcvScopeLoadNpcCatalog();
                    $presence = pcv_read_eligible_npcs($key, $rows, $playerName);
                    if (in_array($presence['status'] ?? null, ['ready', 'empty'], true)) {
                        $eligibleMap = $presence['known_npcs'] ?? [];
                    } else {
                        $failureReason = pcvScopePresenceFailureReason($presence);
                        if (!in_array($failureReason, ['presence_missing', 'presence_stale', 'presence_unavailable', 'presence_key_mismatch'], true)) {
                            pcv_log_event('state.unavailable', 'error', 'unavailable', $failureReason, ['operation' => 'begin']);
                        }
                    }
                }
            } catch (Throwable $error) {
                // A missing catalog or presence report leaves the map null; state applies fail-closed.
                $failureReason = 'catalog_unavailable';
                pcv_log_exception('state.unavailable', 'error', 'unavailable', $failureReason, $error, ['operation' => 'begin']);
            }
        } elseif ($eligible && is_array($currentPresence)
            && in_array($currentPresence['status'] ?? null, ['ready', 'empty'], true)) {
            $actors = is_array($currentPresence['actors'] ?? null) ? $currentPresence['actors'] : [];
            if (($currentPresence['status'] ?? null) === 'empty' || $actors === []) {
                $eligibleMap = [];
            } else {
                try {
                    $playerName = pcv_current_player_name();
                    if (is_string($playerName) && trim($playerName) !== '') {
                        $rows = pcvScopeLoadNpcCatalog();
                        $eligibleMap = pcvScopeEligibleMapFromPresence($actors, $rows, $playerName);
                        $freeOrder = pcvScopeFreeCandidateOrder($actors, $eligibleMap);
                    }
                } catch (Throwable $error) {
                    // Do not replace a missing current report with a cached report.
                    $failureReason = 'catalog_unavailable';
                    pcv_log_exception('state.unavailable', 'error', 'unavailable', $failureReason, $error, ['operation' => 'begin']);
                }
            }
        } elseif (!$eligible && !$usePresenceCache && is_array($currentPresence)
            && ($currentPresence['reason'] ?? null) === 'unsupported_mode') {
            $failureReason = 'unsupported_special_mode';
        } elseif ($eligible && is_array($currentPresence)
            && ($currentPresence['reason'] ?? null) === 'unsupported_mode') {
            $failureReason = 'unsupported_special_mode';
            pcv_log_event('state.unavailable', 'error', 'unavailable', $failureReason, ['operation' => 'begin']);
        } elseif ($eligible || $usePresenceCache) {
            $failureReason = pcvScopePresenceFailureReason(is_array($currentPresence) ? $currentPresence : []);
            if (!in_array($failureReason, ['presence_missing', 'presence_stale', 'presence_unavailable', 'presence_key_mismatch'], true)) {
                pcv_log_event('state.unavailable', 'error', 'unavailable', $failureReason, ['operation' => 'begin']);
            }
        }
    }

    // An already active scene tolerates a participant briefly out of close range (grace, wide report,
    // baseline report after a gap). Activation keeps the strict map.
    $activeMap = null;
    $activeCheck = null;
    $allowDrops = true;
    if ($active && !$pendingEnd && is_array($observed['scope'] ?? null)) {
        try {
            $playerName ??= pcv_current_player_name();
            if (is_string($playerName) && trim($playerName) !== '') {
                $rows ??= pcvScopeLoadNpcCatalog();
                $sceneIds = array_values(array_filter(pcv_config_actor_ids($observed['scope']), 'is_string'));
                $inScene = pcv_read_active_scene_npcs($key, $rows, $playerName, $sceneIds);
                $activeMap = ($eligibleMap ?? []) + $inScene['known_npcs'];
                $activeCheck = $inScene['missing'] === [] ? null : (string)reset($inScene['missing']);
                if (($inScene['error'] ?? false) === true) {
                    // 0.1.13: unreadable evidence refuses this turn; it never drops members for good.
                    $allowDrops = false;
                    $activeCheck = 'presence_error';
                }
            }
        } catch (Throwable $error) {
            $allowDrops = false;
            pcv_log_exception('state.unavailable', 'error', 'unavailable', 'catalog_unavailable', $error, ['operation' => 'begin']);
        }
    }

    // The state lock rechecks the observed state and rejects any concurrent enabled config without a map.
    $result = pcv_begin_request($key, $eligible, null, $eligibleMap, $activeMap, $activeCheck, $freeOrder, $allowDrops);
    if (($result['status'] ?? null) === 'unavailable' && $failureReason !== null
        && !in_array($failureReason, ['presence_missing', 'presence_stale'], true)) {
        $result['reason'] = $failureReason;
    }
    if (($result['status'] ?? null) !== 'active') {
        return pcvResolveLiveScopeState($result);
    }
    // A scene activated by this request used the strict map; one kept active may rely on the in-scene map.
    return pcvResolveLiveScopeState($result, $rows, $playerName, $activeMap ?? $eligibleMap);
}

/** Resolve the stored scene IDs against the current catalog and presence map. */
function pcvResolveLiveScopeState(
    array $state,
    ?array $rows = null,
    ?string $playerName = null,
    ?array $eligibleMap = null
): array
{
    $status = $state['status'] ?? 'unavailable';
    $storedScope = is_array($state['scope'] ?? null) ? $state['scope'] : [];
    $result = [
        'status' => $status,
        'scope' => null,
        'profile_id_a' => null,
        'config_id' => is_string($state['config_id'] ?? null) ? $state['config_id'] : null,
        'actor_a_id' => is_string($storedScope['actor_a'] ?? null) ? $storedScope['actor_a'] : null,
        'actor_b_id' => is_string($storedScope['actor_b'] ?? null) ? $storedScope['actor_b'] : null,
        'reason' => is_string($state['reason'] ?? null) ? $state['reason'] : null,
    ];
    if ($status !== 'active') {
        return $result;
    }
    $sceneMode = array_key_exists('scene_mode', $storedScope) ? $storedScope['scene_mode'] : 'pair';
    if (!is_array($state['scope'] ?? null) || !is_array($rows)
        || !is_string($playerName) || trim($playerName) === '' || !is_array($eligibleMap)
        || !in_array($sceneMode, ['pair', 'solo'], true)
        || ($sceneMode === 'solo' && ($storedScope['actor_b'] ?? null) !== null)) {
        return array_replace($result, ['status' => 'unavailable']);
    }
    foreach (pcvScopeConfigActorIds($state['scope']) as $memberId) {
        if (!is_string($memberId) || !array_key_exists($memberId, $eligibleMap)) {
            return array_replace($result, ['status' => 'unavailable']);
        }
    }
    $knownNpcs = pcvScopeKnownNpcs($rows, $playerName);
    $scope = pcvResolveScopeNames($state['scope'], $knownNpcs, $playerName);
    if ($scope === null) {
        return array_replace($result, ['status' => 'unavailable']);
    }

    // A needs exactly one valid profile row (as before 0.1.11). Other members' profiles are collected when they
    // are unambiguous so an auto or picked opener can start; an opener without one falls back to A.
    $memberNames = pcvScopeMembers($scope);
    $profiles = [];
    foreach (pcvScopeConfigActorIds($state['scope']) as $index => $memberId) {
        $profile = null;
        $ambiguous = false;
        foreach ($rows as $row) {
            if (!is_array($row) || (string)($row['id'] ?? '') !== (string)$memberId) {
                continue;
            }
            $candidate = filter_var($row['profile_id'] ?? null, FILTER_VALIDATE_INT);
            if ($candidate === false || $candidate < 1 || $profile !== null) {
                $ambiguous = true;
                break;
            }
            $profile = $candidate;
        }
        if ($index === 0 && ($ambiguous || !is_int($profile))) {
            return array_replace($result, ['status' => 'unavailable']);
        }
        if (!$ambiguous && is_int($profile) && isset($memberNames[$index])) {
            $profiles[$memberNames[$index]] = $profile;
        }
    }
    $memberIds = [];
    foreach (pcvScopeConfigActorIds($state['scope']) as $index => $memberId) {
        if (isset($memberNames[$index]) && is_string($memberId)) {
            $memberIds[$memberNames[$index]] = $memberId;
        }
    }
    return array_replace($result, [
        'status' => 'active', 'scope' => $scope, 'profile_id_a' => $profiles[$scope['actor_a']], 'profiles' => $profiles,
        'member_ids' => $memberIds,
    ]);
}

/** Load one current catalog snapshot for the entire request. */
function pcvScopeLoadNpcCatalog(): array
{
    if (!class_exists('NpcMaster')) {
        throw new RuntimeException('NPC catalog is unavailable.');
    }
    $catalog = new \NpcMaster();
    $rows = $catalog->getAll();
    if (!is_array($rows)) {
        throw new RuntimeException('NPC catalog is unavailable.');
    }
    return $rows;
}

function pcvScopePresenceFailureReason(array $presence): string
{
    $reason = $presence['reason'] ?? null;
    if (in_array($reason, ['presence_missing', 'presence_stale', 'presence_unavailable', 'presence_invalid', 'presence_key_mismatch'], true)) {
        return $reason;
    }
    if (($presence['status'] ?? null) === 'missing') {
        return 'presence_missing';
    }
    if (($presence['status'] ?? null) === 'stale') {
        return 'presence_stale';
    }
    return 'presence_unavailable';
}

/** A present state file cannot safely be matched while the live playthrough identity is unknown. */
function pcvScopeStoredStateExists(?string $stateDirectory = null): bool
{
    try {
        $directory = pcv_state_directory($stateDirectory);
        $path = $directory . DIRECTORY_SEPARATOR . 'state.json';
        if (!file_exists($path) && !is_link($path)) {
            return false;
        }
        // 0.1.13: a readable store with no active or pending scene cannot be leaked to; unreadable stores still count.
        if (function_exists('pcv_load_store')) {
            $handle = pcv_lock_state($directory, false, LOCK_SH);
            try {
                $loaded = pcv_load_store($directory);
            } finally {
                pcv_unlock_state($handle);
            }
            if ($loaded['kind'] === 'ready') {
                return $loaded['state']['active'] !== null || $loaded['state']['pending'] !== null;
            }
        }
        return true;
    } catch (Throwable) {
        return true;
    }
}

function pcvScopeLogSceneIneligible(string $operation, array $storedScope): void
{
    $sceneMode = $storedScope['scene_mode'] ?? 'pair';
    if (!in_array($sceneMode, ['pair', 'solo'], true)) {
        $sceneMode = 'pair';
    }
    pcv_log_event('state.scope_skipped', 'info', 'skipped', 'scene_not_eligible', [
        'operation' => $operation,
        'scene_mode' => $sceneMode,
    ]);
}

/** Resolve stable actor IDs against the live catalog; never trust stored names. */
function pcvResolveScopeNames(array $storedScope, array $knownNpcs, ?string $playerName): ?array
{
    $sceneMode = array_key_exists('scene_mode', $storedScope) ? $storedScope['scene_mode'] : 'pair';
    if (($storedScope['enabled'] ?? null) !== true
        || !in_array($sceneMode, ['pair', 'solo'], true)
        || !is_bool($storedScope['exclude_player'] ?? null)
        || !in_array($storedScope['bystander_mode'] ?? null, ['exclude', 'silent'], true)
        || ($sceneMode === 'solo' && ($storedScope['exclude_player'] !== true || ($storedScope['actor_b'] ?? null) !== null))) {
        return null;
    }

    // Group scenes (0.1.11) list 2-4 members; legacy pairs are A and B; solo is A only.
    $ids = pcvScopeConfigActorIds($storedScope);
    $maxMembers = pcvScopeMaxMembers($storedScope);
    if ($sceneMode === 'pair' && (count($ids) < 2 || count($ids) > $maxMembers)) {
        return null;
    }
    $playerKey = is_string($playerName) && trim($playerName) !== '' ? pcv_scope_name_key($playerName) : '';
    if (is_string($playerName) && str_contains($playerName, '|')) {
        return null;
    }
    $reserved = ['player', 'the player', 'player character', 'the player character', 'dragonborn', 'the dragonborn', 'the narrator', 'explicit_disable_rechat'];
    $names = [];
    $keys = [];
    foreach ($ids as $id) {
        if (!is_string($id) || $id === '' || isset($names[$id]) || !is_string($knownNpcs[$id] ?? null)) {
            return null;
        }
        $name = trim($knownNpcs[$id]);
        if ($name === '' || preg_match('//u', $name) !== 1 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1
            || str_contains($name, '|')) {
            return null;
        }
        $key = pcv_scope_name_key($name);
        if (isset($keys[$key]) || $key === $playerKey || in_array($key, $reserved, true)) {
            return null;
        }
        $keys[$key] = true;
        $names[$id] = $name;
    }
    $members = array_values($names);

    $opener = $members[0];
    if ($sceneMode === 'pair' && array_key_exists('opener', $storedScope)) {
        $opener = $storedScope['opener'] === 'auto' ? null : ($names[$storedScope['opener']] ?? null);
        if ($storedScope['opener'] !== 'auto' && $opener === null) {
            return null;
        }
    }

    if ($sceneMode === 'solo') {
        // Solo keeps its pre-0.1.11 shape; reflection code relies on it.
        return [
            'enabled' => true,
            'scene_mode' => 'solo',
            'actor_a' => $members[0],
            'actor_b' => null,
            'exclude_player' => $storedScope['exclude_player'],
            'bystander_mode' => $storedScope['bystander_mode'],
        ];
    }
    $resolved = [
        'enabled' => true,
        'scene_mode' => $sceneMode,
        'actor_a' => $members[0],
        'actor_b' => $members[1],
        'members' => $members,
        'opener' => $opener,
        'exclude_player' => $storedScope['exclude_player'],
        'bystander_mode' => $storedScope['bystander_mode'],
    ];
    if (($storedScope['free'] ?? false) === true) {
        $resolved['free'] = true;
    }
    return $resolved;
}

/**
 * Prepare an eligible ordinary player input for a private scene.
 * Player-excluded text is an instruction event with the exact current player prefix;
 * CHIM's instruction parser removes that prefix before it enters model context.
 * @return array{status:string,game_request:array,routing_snapshot:array,reason:?string}
 */
function pcvPrepareScopedInput(array $request, array $snapshot, ?array $resolvedScope, ?string $playerName, string $mode): array
{
    $result = ['status' => 'off', 'game_request' => $request, 'routing_snapshot' => $snapshot, 'reason' => null];
    if (!is_array($resolvedScope) || ($resolvedScope['enabled'] ?? false) !== true) {
        return $result;
    }

    $requestType = strtolower((string)($request[0] ?? ''));
    $inputTypes = ['inputtext', 'inputtext_s', 'ginputtext', 'ginputtext_s'];
    if (!in_array($requestType, $inputTypes, true) || strtoupper($mode) !== 'STANDARD') {
        $result['status'] = 'blocked';
        $result['reason'] = strtoupper($mode) === 'CLOSE' || strtoupper($mode) === 'WHISPER'
            ? 'unsupported_special_mode' : 'unsupported_mode';
        return $result;
    }

    $nameA = $resolvedScope['actor_a'] ?? null;
    $sceneMode = $resolvedScope['scene_mode'] ?? 'pair';
    $nameB = $resolvedScope['actor_b'] ?? null;
    if (!is_string($nameA) || $nameA === '' || !in_array($sceneMode, ['pair', 'solo'], true)
        || ($sceneMode === 'pair' && (!is_string($nameB) || $nameB === ''))
        || ($sceneMode === 'solo' && $nameB !== null)
        || !is_bool($resolvedScope['exclude_player'] ?? null)
        || !is_string($playerName) || trim($playerName) === '') {
        $result['status'] = 'unavailable';
        return $result;
    }

    $snapshot = pcvScopeRoutingSnapshot($snapshot, $resolvedScope, $playerName);

    if ($resolvedScope['exclude_player']) {
        if (str_contains($playerName, ':')) {
            $result['status'] = 'blocked';
            $result['reason'] = 'invalid_input_prefix';
            return $result;
        }
        $text = $request[3] ?? null;
        if (!is_string($text)) {
            $result['status'] = 'blocked';
            $result['reason'] = 'invalid_input_prefix';
            return $result;
        }
        if (preg_match('//u', $text) !== 1) {
            $result['status'] = 'blocked';
            $result['reason'] = 'invalid_input_encoding';
            return $result;
        }

        $knownPrefix = $playerName . ':';
        if (str_starts_with($text, $knownPrefix)) {
            $text = ltrim(substr($text, strlen($knownPrefix)), " \t");
        } elseif (preg_match('/\A[^:\r\n]{1,96}:\s/u', $text) === 1) {
            // Refuse ambiguous speaker prefixes; never synchronize or spoof the player identity.
            $result['status'] = 'blocked';
            $result['reason'] = 'invalid_input_prefix';
            return $result;
        }

        if (trim($text) === '') {
            $result['status'] = 'blocked';
            $result['reason'] = 'empty_input';
            return $result;
        }
        $request[0] = 'instruction';
        $request[3] = $playerName . ': ' . $text;
    }

    $result['status'] = 'applied';
    $result['game_request'] = $request;
    $result['routing_snapshot'] = $snapshot;
    return $result;
}

/** Keep routing audience to the configured pair and remove all explicit bystander presence. */
function pcvScopeRoutingSnapshot(array $snapshot, array $resolvedScope, string $playerName): array
{
    $nameA = $resolvedScope['actor_a'] ?? null;
    $sceneMode = $resolvedScope['scene_mode'] ?? 'pair';
    $nameB = $resolvedScope['actor_b'] ?? null;
    if (!is_string($nameA) || $nameA === '' || !in_array($sceneMode, ['pair', 'solo'], true)
        || ($sceneMode === 'pair' && (!is_string($nameB) || $nameB === ''))
        || ($sceneMode === 'solo' && $nameB !== null)) {
        return $snapshot;
    }
    if ($sceneMode === 'solo') {
        $snapshot['audience'] = '|' . $nameA . '|';
    } else {
        // Exactly the members (plus the player when included): this is also each line's witness list.
        $people = pcvScopeMembers($resolvedScope);
        if (($resolvedScope['exclude_player'] ?? true) !== true) {
            array_unshift($people, $playerName);
        }
        $snapshot['audience'] = '|' . implode('|', $people) . '|';
    }
    $snapshot['present_actors'] = [];
    return $snapshot;
}

/** Who a group speaker may address: every other member, in member order. */
function pcvGroupListeners(string $speaker, array $resolvedScope): array
{
    $speakerKey = pcv_scope_name_key($speaker);
    return array_values(array_filter(pcvScopeMembers($resolvedScope),
        static fn(string $member) => pcv_scope_name_key($member) !== $speakerKey));
}

/** Add the selected pair as the sole set of eligible rechat speakers. */
function pcvClampRechatActiveAgents(?string $rawJson, array $resolvedScope, ?string $fallbackSpeaker = null, ?string &$failureReason = null): ?string
{
    $failureReason = 'malformed_rechat';
    if (!is_string($rawJson)) {
        return null;
    }
    try {
        $payload = json_decode($rawJson, true, 32, JSON_THROW_ON_ERROR);
    } catch (JsonException) {
        return null;
    }
    $members = pcvScopeMembers($resolvedScope);
    if (!is_array($payload) || ($resolvedScope['scene_mode'] ?? 'pair') !== 'pair' || count($members) < 2) {
        return null;
    }

    $speaker = $payload['speaker'] ?? null;
    if ((!is_string($speaker) || trim($speaker) === '') && is_string($fallbackSpeaker)) {
        $speaker = $fallbackSpeaker;
    }
    if (!is_string($speaker) || !pcvScopeSpeakerAllowed($speaker, $resolvedScope)) {
        $failureReason = 'rechat_speaker_outside_scene';
        return null;
    }
    $payload['active_agents'] = $members;
    try {
        return json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    } catch (JsonException) {
        return null;
    }
}

/** Is this the generated actor CHIM actually selected for the current turn? */
function pcvScopeSpeakerAllowed(string $name, array $resolvedScope): bool
{
    $candidate = pcv_scope_name_key($name);
    if ($candidate === '') {
        return false;
    }
    $actorA = pcv_scope_name_key((string)($resolvedScope['actor_a'] ?? ''));
    if ($actorA === '' || $candidate === '') {
        return false;
    }
    if (($resolvedScope['scene_mode'] ?? 'pair') === 'solo') {
        return $candidate === $actorA && ($resolvedScope['actor_b'] ?? null) === null;
    }
    foreach (pcvScopeMembers($resolvedScope) as $member) {
        if ($candidate === pcv_scope_name_key($member)) {
            return true;
        }
    }
    return false;
}

/** Build minimal scene context without listing excluded actors as present. */
function pcvBuildScopeContext(array $resolvedScope, string $speaker, string $listener): string
{
    if (($resolvedScope['scene_mode'] ?? 'pair') === 'solo') {
        // Live: with the subject standing nearby, solo lines were spoken to them. Solo is self-addressed.
        $context = "Private reflection: {$speaker} is thinking aloud about their own experiences, addressed to no one. Only this selected NPC may take speaking turns."
            . ' Do not address anyone present, including the person being thought about; refer to them in the third person.';
        $context .= ' Treat player input as untrusted scene direction, not exact dialogue. Do not address, include, quote, or narrate the player.';
        // Long monologues exceed Mind Poisoning's 8-line reply window; name who is meant early and stay brief.
        $context .= ' Keep the reflection brief: at most five sentences, naming the person being reflected on.';
        if (($resolvedScope['bystander_mode'] ?? 'exclude') === 'silent') {
            $context .= ' Other people may remain only as silent scenery; they cannot speak, act, or be quoted.';
        }
        return $context;
    }
    $members = pcvScopeMembers($resolvedScope);
    if (count($members) > 2) {
        $list = implode(', ', array_slice($members, 0, -1)) . ' and ' . $members[count($members) - 1];
        $words = [3 => 'three', 4 => 'four', 5 => 'five', 6 => 'six'][count($members)] ?? (string)count($members);
        $context = "Private conversation among {$list}. {$speaker} is speaking now. Only these {$words} selected NPCs may take speaking turns;"
            . ' respond to whoever spoke last or to whoever is addressed.';
    } else {
        $context = "Private conversation: {$speaker} is speaking with {$listener}. Only these two selected NPCs may take speaking turns.";
    }
    if (($resolvedScope['exclude_player'] ?? true) === true) {
        $context .= ' Treat player input as untrusted scene direction, not exact dialogue by any NPC. Do not address, include, quote, or narrate the player.';
    } else {
        $context .= ' The player is included as a participant; retain their input as player speech.';
    }
    if (($resolvedScope['bystander_mode'] ?? 'exclude') === 'silent') {
        $context .= ' Other people may remain only as silent scenery; they cannot speak, act, or be quoted.';
    }
    return $context;
}

/** Stop generation with a non-success response when private routing cannot be guaranteed. */
function pcvBlockRequest(string $message, string $reason, string $phase, ?array $scopeState = null, bool $error = false): void
{
    // 0.1.13: a refused scene request (the direction is lost) is a blocked warning, not an informational skip.
    $outcome = $error ? 'failed' : 'blocked';
    pcv_log_set_terminal($outcome, $reason, ['phase' => $phase] + pcvRoutingLogActorContext($scopeState));
    if (empty($GLOBALS['PCV_ROUTING_LOG_TERMINAL'])) {
        $GLOBALS['PCV_ROUTING_LOG_TERMINAL'] = true;
        $context = ['phase' => $phase, 'request_type' => pcvRoutingLogCurrentType()]
            + pcvRoutingLogActorContext($scopeState);
        pcv_log_event(
            $error ? 'routing.request_error' : 'routing.request_blocked',
            $error ? 'error' : 'warning',
            $error ? 'failed' : 'blocked',
            $reason,
            $context
        );
    }
    http_response_code(409);
    header('Content-Type: text/plain; charset=UTF-8');
    echo $message;
    if (function_exists('terminate')) {
        terminate();
    }
    exit;
}
