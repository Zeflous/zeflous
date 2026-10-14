<?php

declare(strict_types=1);

/*
 * The application fixture's route table: two Standard Route Schema
 * entries the radix tree engine compiles and the schema validator
 * accepts at boot -- one static path, one with a named parameter.
 */

return [
    [
        'path' => '/health',
        'method' => ['GET'],
        'handler' => 'HealthController::check',
        'name' => 'health.check',
    ],
    [
        'path' => '/users/{id}',
        'method' => ['GET'],
        'handler' => 'UserController::show',
        'name' => 'users.show',
    ],
];
