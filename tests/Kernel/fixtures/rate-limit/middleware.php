<?php

declare(strict_types=1);

/*
 * The rate-limit fixture application's stack: the framework's own
 * `security.rate_limit` registration -- installed by the Default layer,
 * not by any fixture file -- opted into the pipeline by name.
 */

return [
    'stack' => ['security.rate_limit'],
];
