<?php

declare(strict_types=1);

/*
 * The auth-fixture application's stack: the framework's own
 * `security.auth` registration -- installed by the Default layer, not
 * by any fixture file -- opted into the pipeline by name.
 */

return [
    'stack' => ['security.auth'],
];
