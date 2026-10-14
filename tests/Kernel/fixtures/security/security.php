<?php

declare(strict_types=1);

/*
 * The security-fixture application's header knobs: HSTS opted in, the
 * Permissions-Policy baseline toggle switched off, and the other knobs
 * left to the framework's own defaults -- the merge the boot replays.
 */

return [
    'headers' => [
        'hsts' => true,
        'permissions_policy' => false,
    ],
];
