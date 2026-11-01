<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\ConfigReadingModule;

/*
 * The config service's registration-phase reader's manifest: no routes of
 * its own -- the module only reads the boot's service.
 */

return [
    'name' => 'test/reader',
    'version' => '1.0.0',
    'class' => ConfigReadingModule::class,
];
