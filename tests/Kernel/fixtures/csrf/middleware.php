<?php

declare(strict_types=1);

/*
 * The csrf-fixture application's stack: the framework's own
 * `security.csrf` registration -- installed by the Default layer, not
 * by any fixture file -- opted into the pipeline by name.
 */

return [
    'stack' => ['security.csrf'],
];
