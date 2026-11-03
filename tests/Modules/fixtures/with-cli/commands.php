<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\CliModule;

/*
 * The module's command declarations: Standard CLI Schema entries -- the
 * verbs the module author owns, the one-line purposes the directory
 * renders, and the handler services the module's own registration phase
 * claimed.
 */

return [
    [
        'name' => 'shop:ping',
        'description' => "Answer the shop module's health probe.",
        'service' => CliModule::HANDLER_SERVICE,
    ],
    [
        'name' => 'shop:report',
        'description' => "Render the shop module's own report.",
        'service' => CliModule::HANDLER_SERVICE,
    ],
];
