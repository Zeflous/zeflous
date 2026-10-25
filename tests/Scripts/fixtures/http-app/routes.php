<?php

declare(strict_types=1);

/*
 * The HTTP inspection commands' fixture route table: the surface the
 * `bin/zef http:dump` and `http:headers` tests answer against -- a
 * plain GET route, a parameterised route with its constraint, and a
 * POST route whose answer carries the captured parameter, so the dump
 * spells a request with a body over a route that answered from it.
 */

return [
    [
        'path' => '/ping',
        'method' => ['GET'],
        'handler' => 'app.ping',
    ],
    [
        'path' => '/users/{id}',
        'method' => ['GET'],
        'handler' => 'app.user',
        'constraints' => ['id' => '\d+'],
    ],
    [
        'path' => '/mirror/{name}',
        'method' => ['POST'],
        'handler' => 'app.mirror',
    ],
];
