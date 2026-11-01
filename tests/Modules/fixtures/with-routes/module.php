<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\RoutesModule;

/*
 * The manifest with its own routes file: the relative routes spelling the
 * discovery fold resolves against this directory, the same rule the
 * config_schema path rides.
 */

return [
    'name' => 'test/shop',
    'version' => '1.0.0',
    'class' => RoutesModule::class,
    'routes' => './config/routes.php',
];
