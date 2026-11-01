<?php

declare(strict_types=1);

/*
 * The manifest whose class does not exist: the resolution refusal names the
 * manifest that declared it. The class spelling is a string literal -- the
 * missing class IS the fixture's subject, so no symbol reference is made.
 */

return [
    'name' => 'test/missing',
    'version' => '1.0.0',
    'class' => 'Zef\Test\Modules\Fixtures\MissingModule',
];
