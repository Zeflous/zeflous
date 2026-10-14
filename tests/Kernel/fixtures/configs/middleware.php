<?php

declare(strict_types=1);

/*
 * The application fixture's middleware configuration: the stack the
 * pipeline factory builds, ordering the services fixture's two middlewares
 * by priority -- the priority 900 entry runs outside the priority 10 one,
 * regardless of their declaration order in this list.
 */

return [
    'stack' => [
        ['service' => 'mw.inner', 'priority' => 10],
        ['service' => 'mw.outer', 'priority' => 900],
    ],
];
