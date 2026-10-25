<?php

declare(strict_types=1);

/*
 * The HTTP inspection fixture application's header knobs: HSTS and
 * CSP opted in, the other knobs left to the framework's own defaults
 * -- the merge the boot replays, and the posture the fixture's
 * https-scheme audits judge a fully passing one.
 */

return [
    'headers' => [
        'hsts' => true,
        'csp' => true,
    ],
];
