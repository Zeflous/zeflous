<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\EchoContextModule;

/*
 * The manifest whose schema file itself is malformed: the rule map's
 * fail-closed validation names the module and the field.
 */

return [
    'name' => 'test/echo',
    'version' => '1.0.0',
    'class' => EchoContextModule::class,
    'config_schema' => './config/schema.php',
];
