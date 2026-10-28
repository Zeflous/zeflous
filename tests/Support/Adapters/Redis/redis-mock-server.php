<?php

declare(strict_types=1);

namespace Zef\Test\Support\Adapters\Redis;

/*
 * The Redis mock server's process script: the entry point the
 * spawner (RedisMockServer) execs directly -- the array-form
 * command, so the process the spawner owns IS the server its
 * shutdown signal stops. The loop reports its ephemeral address to
 * the report file the spawner polls, then serves until stopped.
 *
 * Internal test support, never shipped.
 */

require __DIR__ . '/../../../../vendor/autoload.php';

$reportFile = $argv[1] ?? '';

if ($reportFile === '') {
    exit(2);
}

new RedisMockServerLoop($reportFile)->run();
