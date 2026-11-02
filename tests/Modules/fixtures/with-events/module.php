<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\EventsModule;

/*
 * The manifest with its own events file: the relative events spelling
 * the discovery fold resolves against this directory, the same rule
 * the config_schema and routes paths ride.
 */

return [
    'name' => 'test/shop',
    'version' => '1.0.0',
    'class' => EventsModule::class,
    'events' => './config/events.php',
];
