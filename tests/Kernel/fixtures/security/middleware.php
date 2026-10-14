<?php

declare(strict_types=1);

/*
 * The security-fixture application's stack: the framework's own
 * `security.headers` registration -- installed by the Default layer, not
 * by any fixture file -- opted into the pipeline by name.
 */

return [
    'stack' => ['security.headers'],
];
