<?php

define('CLI_SCRIPT', true);

require(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');

$marker = __DIR__ . '/_enable_task_runnow_' . date('Ymd_His') . '.txt';
@file_put_contents($marker, "ran at " . date('c') . "\n");

// Enable the Task scheduler "Run now" links.
set_config('enablerunnow', 1, 'tool_task');

// Also persist a PHP CLI path if we can detect one.
if (!empty($CFG->pathtophp)) {
    set_config('pathtophp', $CFG->pathtophp);
}

mtrace('tool_task/enablerunnow=' . (int)get_config('tool_task', 'enablerunnow'));
mtrace('pathtophp=' . (string)get_config('core', 'pathtophp'));
mtrace('marker=' . $marker);
