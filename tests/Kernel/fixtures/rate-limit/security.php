<?php

declare(strict_types=1);

/*
 * The rate-limit fixture application's knobs: the throttle armed with a
 * budget of one request per hour-long window, the memory store -- and
 * the headers family left to the framework's own defaults -- the merge
 * the boot replays.
 */

return [
    'rate_limit' => [
        'enabled' => true,
        'limit' => 1,
        'window' => 3600,
    ],
];
