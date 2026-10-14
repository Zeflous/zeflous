<?php

declare(strict_types=1);

/*
 * The router's configuration file: everything under this key is the
 * `routes` list the configuration schema validates at boot and the radix
 * tree engine compiles.
 *
 * Each entry is one route in the Standard Route Schema:
 *
 *   'path'        (required)  the URL pattern, parameters in braces
 *                             ('/users/{id}')
 *   'method'      (required)  the list of HTTP methods answered
 *   'handler'     (required)  the controller reference
 *                             ('UserController::show' or a callable
 *                             service id)
 *   'name'        (optional)  the reverse-routing name
 *   'middleware'  (optional)  the per-route middleware list
 *   'constraints' (optional)  parameter validation patterns
 *   'meta'        (optional)  free-form metadata
 *
 * A malformed route halts the boot with its full path context; the
 * shipped profile declares no routes -- valid zero-routing -- and an
 * application fills this file with the route table it wants served.
 */

return [];
