<?php
declare(strict_types=1);

// 0.1.11 group mode: the opener is the member named earliest in the direction (full name, or a first name that is
// unique among the members); otherwise the picked opener; otherwise the first member. Fixes "What does Bruce
// reply?" getting the first member (live issue 6).

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';
require_once dirname(__DIR__) . '/server/state.php';
require_once dirname(__DIR__) . '/server/scope.php';

function opCheck(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

try {
    $members = ['Lidia Sobieska', 'Aela the Huntress', 'Bruce Wayne'];
    $cases = [
        ['What does Bruce reply to Lidia?', null, 'Bruce Wayne', 'named'],
        ['Lidia tells Bruce Wayne to go home.', null, 'Lidia Sobieska', 'named'],
        ['Aela the Huntress laughs at both.', null, 'Aela the Huntress', 'named'],
        ['BRUCE answers first.', null, 'Bruce Wayne', 'named'],
        ['Everyone stares at the snow.', 'Bruce Wayne', 'Bruce Wayne', 'picker'],
        ['Everyone stares at the snow.', null, 'Lidia Sobieska', 'first'],
        ['Brucey is not a name match.', null, 'Lidia Sobieska', 'first'],
        ['Lidia-style hats are back.', null, 'Lidia Sobieska', 'named'],
    ];
    foreach ($cases as [$direction, $picker, $expected, $source]) {
        $got = pcvGroupPickOpener($direction, $members, $picker);
        echo json_encode([$direction, $got]) . "\n";
        opCheck(($got['name'] ?? null) === $expected && ($got['source'] ?? null) === $source, "Opener for: $direction");
    }
    $ambiguous = pcvGroupPickOpener('Guard looks around.', ['Guard One', 'Guard Two', 'Stalleo'], null);
    opCheck(($ambiguous['name'] ?? null) === 'Guard One' && $ambiguous['source'] === 'first', 'An ambiguous first name never selects a member.');
    $full = pcvGroupPickOpener('Stalleo nods; Guard Two speaks.', ['Guard One', 'Guard Two', 'Stalleo'], null);
    opCheck(($full['name'] ?? null) === 'Stalleo' && $full['source'] === 'named', 'The earliest named member wins.');
    $badPicker = pcvGroupPickOpener('Nobody named.', $members, 'Someone Else');
    opCheck($badPicker['name'] === 'Lidia Sobieska' && $badPicker['source'] === 'first', 'A picker outside the members is ignored.');
    echo "PASS opener: earliest named member (full or unique first name), else picker, else first\n";
    exit(0);
} catch (Throwable $error) {
    fwrite(STDERR, 'FAIL: ' . $error->getMessage() . "\n");
    exit(1);
}
