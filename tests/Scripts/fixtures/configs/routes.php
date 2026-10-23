<?php

declare(strict_types=1);

/*
 * The route inspection commands' fixture route table: the surface the
 * `bin/zef route:list`, `route:test` and `route:tree` tests answer
 * against -- every optional Standard Route Schema declaration the
 * rendering spells (a named route, an unnamed route, a multi-method
 * route, a host-claimed route with its middleware and version and
 * producible media types, a deprecated route with its sunset and
 * documentation link), in a declaration order deliberately unlike the
 * tree's insertion order so the list's order contract is pinned.
 */

return [
    [
        'path' => '/users/{id}',
        'method' => ['GET'],
        'handler' => 'UserController::show',
        'name' => 'users.show',
        'constraints' => ['id' => '\d+'],
    ],
    [
        'path' => '/users',
        'method' => ['GET', 'POST'],
        'handler' => 'UserController::index',
        'name' => 'users.index',
    ],
    [
        'path' => '/admin/reports',
        'method' => ['GET'],
        'handler' => 'ReportController::show',
        'name' => 'reports.show',
        'host' => 'admin.example.com',
        'middleware' => ['auth.session'],
        'version' => '2',
        'produces' => ['application/json'],
    ],
    [
        'path' => '/legacy/status',
        'method' => ['GET'],
        'handler' => 'StatusController::show',
        'name' => 'status.legacy',
        'deprecation' => [
            'date' => '2026-01-15',
            'sunset' => '2026-06-30',
            'link' => '/docs/migration',
        ],
    ],
    [
        'path' => '/ping',
        'method' => ['GET'],
        'handler' => 'PingController::pong',
    ],
];
