<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\StandingModule;

/*
 * The manifest with its own events file: the relative events spelling
 * the discovery fold resolves against this directory, the same rule
 * the config_schema and routes paths ride.
 */

return [
    'name' => 'test/standing',
    'version' => '1.0.0',
    'class' => StandingModule::class,
    'events' => './config/events.php',
];
