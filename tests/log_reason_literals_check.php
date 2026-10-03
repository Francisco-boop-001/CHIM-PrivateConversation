<?php
declare(strict_types=1);

// 0.1.13 E9c: pcv_log_event silently drops events whose event/severity/outcome/reason do not match the schema.
// Every literal call in server/ must match, so a typo in new code cannot make events vanish.

define('PCV_LOG_TESTING', true);
require_once dirname(__DIR__) . '/server/log.php';

$failures = [];
$checked = 0;
$pattern = "/pcv_log_(?:event|exception)\\(\\s*'([a-z_.]+)'\\s*,\\s*'([a-z]+)'\\s*,\\s*'([a-z_]+)'\\s*,\\s*(?:'([a-z_]+)'|null)\\s*[,)]/";
foreach (glob(dirname(__DIR__) . '/server/*.php') ?: [] as $file) {
    $source = (string)file_get_contents($file);
    if (preg_match_all($pattern, $source, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE) === false) {
        continue;
    }
    foreach ($matches as $match) {
        $checked++;
        [$event, $severity, $outcome] = [$match[1][0], $match[2][0], $match[3][0]];
        $reason = isset($match[4]) && $match[4][0] !== '' ? $match[4][0] : null;
        if (!pcv_log_rule_matches($event, $severity, $outcome, $reason)) {
            $line = substr_count(substr($source, 0, $match[0][1]), "\n") + 1;
            $failures[] = basename($file) . ":$line $event/$severity/$outcome/" . ($reason ?? 'null');
        }
    }
}
echo "checked $checked literal log calls\n";
if ($checked < 40) {
    fwrite(STDERR, "FAIL: the scan found too few calls; the pattern is probably broken\n");
    exit(1);
}
if ($failures !== []) {
    fwrite(STDERR, "FAIL: literal log calls rejected by the schema:\n  " . implode("\n  ", $failures) . "\n");
    exit(1);
}
echo "PASS every literal log call in server/ matches the event schema\n";
exit(0);
