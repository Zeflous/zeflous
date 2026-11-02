<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\StandingModule;

/*
 * The module's event declarations: one event, several listeners, and a
 * declaration order deliberately not the tags' own order -- the standing
 * the wired dispatcher must answer is the order spelled here, never a
 * sort the tags or the service identifiers would produce on their own.
 */

return [
    [
        'name' => 'shop.order.audited',
        'fields' => [
            'orderId' => 'int',
        ],
        'listeners' => [
            StandingModule::THIRD_SERVICE,
            StandingModule::FIRST_SERVICE,
            StandingModule::SECOND_SERVICE,
        ],
    ],
];
