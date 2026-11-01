<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\EchoContextModule;

/*
 * The manifest with its own configuration schema: the relative
 * config_schema spelling the discovery fold resolves against this
 * directory, and the field-rule map the context assembler asserts the
 * module's block against.
 */

return [
    'name' => 'test/echo',
    'version' => '1.0.0',
    'class' => EchoContextModule::class,
    'config_schema' => './config/schema.php',
];
