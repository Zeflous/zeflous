<?php

declare(strict_types=1);

/*
 * The deliberate-empty-stack fixture: a host that spells an empty
 * `middleware.stack` clears the default profile's minimum -- the
 * explicit empty choice wins exactly as any other host list does.
 */

return [
    'stack' => [],
];
