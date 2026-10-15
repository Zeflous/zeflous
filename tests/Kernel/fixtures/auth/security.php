<?php

declare(strict_types=1);

/*
 * The auth-fixture application's knobs: the bearer boundary's own
 * principal and the read-only anonymous posture -- the static token
 * table deliberately NOT here, because the environment overlay is
 * where runtime credentials belong (a committed file is a leak with a
 * history); the boot test supplies it through EnvSource, exactly as a
 * deployment would.
 */

return [
    'auth' => [
        'principal' => 'fixture-service',
        'allow_anonymous' => true,
    ],
];
