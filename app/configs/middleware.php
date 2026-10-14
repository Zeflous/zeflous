<?php

declare(strict_types=1);

/*
 * The middleware pipeline's configuration file: everything under this key
 * is the `middleware` map whose `stack` entry the pipeline factory builds
 * the PSR-15 execution lane from.
 *
 * Each stack entry is one middleware, in one of two spellings:
 *
 *   'middleware.service.id'
 *       A bare string: the container service id of a middleware that
 *       implements the PSR-15 MiddlewareInterface. Priority defaults
 *       to 0 -- declaration order decides.
 *
 *   ['service' => 'middleware.service.id', 'priority' => 500]
 *       The map spelling. Priority is HIGHEST FIRST: 500 runs outside
 *       100, ties keep declaration order. The service must resolve
 *       through the container and implement MiddlewareInterface, or
 *       the build fails closed.
 *
 * The shipped profile declares an empty stack -- the terminal handler
 * alone answers, which is a valid pipeline; an application fills this
 * file with the stack it wants executed around its handlers.
 */

return [
    'stack' => [],
];
