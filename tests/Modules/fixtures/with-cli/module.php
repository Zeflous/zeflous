<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\CliModule;

/*
 * The manifest with its own commands file: the relative cli spelling
 * the discovery fold resolves against this directory, the same rule
 * the config_schema, routes and events paths ride.
 */

return [
    'name' => 'test/shop',
    'version' => '1.0.0',
    'class' => CliModule::class,
    'cli' => './commands.php',
];
