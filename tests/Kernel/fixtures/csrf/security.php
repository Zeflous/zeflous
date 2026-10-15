<?php

declare(strict_types=1);

/*
 * The csrf-fixture application's knobs: the double-submit boundary's
 * own field names and the Lax SameSite posture -- the secret
 * deliberately NOT here, because the environment overlay is where a
 * signing secret belongs (a committed file is a leak with a history);
 * the boot test supplies it through EnvSource, exactly as a deployment
 * would.
 */

return [
    'csrf' => [
        'cookie_name' => 'APP-CSRF-TOKEN',
        'header_name' => 'X-App-Csrf',
        'same_site' => 'Lax',
    ],
];
