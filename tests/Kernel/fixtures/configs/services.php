<?php

declare(strict_types=1);

/*
 * The application fixture's container configuration: a purely declarative
 * service map exercising every definition shape the grammar owns -- two
 * `class` definitions chained through a service-reference argument, one
 * `factory` definition, and the two PSR-15 middlewares the middleware
 * fixture's stack orders (each constructed from a literal name and the
 * shared trace service).
 */

use Zef\Test\Support\Container\Announcer;
use Zef\Test\Support\Container\ExternalFactory;
use Zef\Test\Support\Container\Greeter;
use Zef\Test\Support\Middleware\MiddlewareTrace;
use Zef\Test\Support\Middleware\RecordingMiddleware;

return [
    'greeter' => [
        'class' => Greeter::class,
        'arguments' => ['Hello'],
    ],
    'announcer' => [
        'class' => Announcer::class,
        'arguments' => [
            ['service' => 'greeter'],
            'general',
        ],
    ],
    'made.subject' => [
        'factory' => ExternalFactory::class . '::make',
        'arguments' => ['fixture'],
    ],
    'mw.trace' => [
        'class' => MiddlewareTrace::class,
    ],
    'mw.outer' => [
        'class' => RecordingMiddleware::class,
        'arguments' => [
            'outer',
            ['service' => 'mw.trace'],
        ],
    ],
    'mw.inner' => [
        'class' => RecordingMiddleware::class,
        'arguments' => [
            'inner',
            ['service' => 'mw.trace'],
        ],
    ],
];
