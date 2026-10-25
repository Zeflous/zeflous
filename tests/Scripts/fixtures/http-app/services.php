<?php

declare(strict_types=1);

/*
 * The HTTP inspection commands' fixture services: the terminal
 * handlers the fixture route table names -- class-shaped callables
 * (never closures), so the container's AOT artifact boundary can
 * render the plan and the boot's throwaway artifact is actually
 * written.
 */

use Zef\Test\Support\Http\MirrorHandler;
use Zef\Test\Support\Http\PingHandler;
use Zef\Test\Support\Http\UserHandler;

return [
    'app.ping' => [
        'class' => PingHandler::class,
        'arguments' => [],
    ],
    'app.user' => [
        'class' => UserHandler::class,
        'arguments' => [],
    ],
    'app.mirror' => [
        'class' => MirrorHandler::class,
        'arguments' => [],
    ],
];
