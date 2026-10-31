<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\AlphaModule;

/*
 * The manifest spelling of the fixture chain's head: the same identity the
 * class answers, declared declaratively -- the agreement check's green arm.
 */

return [
    'name' => 'test/alpha',
    'version' => '1.0.0',
    'class' => AlphaModule::class,
];
