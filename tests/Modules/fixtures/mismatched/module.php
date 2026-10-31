<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\WrongIdentityModule;

/*
 * The lying manifest: it declares test/wrong@1.0.0 while the class answers
 * test/other@2.0.0 -- the identity agreement check's refusal arm.
 */

return [
    'name' => 'test/wrong',
    'version' => '1.0.0',
    'class' => WrongIdentityModule::class,
];
