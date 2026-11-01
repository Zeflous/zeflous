<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\EchoContextModule;

/*
 * The malformed manifest: an unknown key and a wrong kind -- the field
 * schema's violations, rendered with every field named.
 */

return [
    'name' => 'test/echo',
    'version' => 'one',
    'requires' => 'test/alpha',
    'class' => EchoContextModule::class,
    'unknown_key' => true,
];
