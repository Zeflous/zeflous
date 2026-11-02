<?php

declare(strict_types=1);

use Zef\Test\Modules\Fixtures\EventsModule;

/*
 * The module's event declarations: Standard Events Schema entries -- the
 * names the module author owns, the payload contracts the dispatches
 * answer, and the listener services the module's own registration phase
 * claimed.
 */

return [
    [
        'name' => 'shop.order.paid',
        'fields' => [
            'orderId' => 'int',
            'amount' => 'int',
            'currency' => 'string',
        ],
        'listeners' => [
            EventsModule::LISTENER_SERVICE,
        ],
    ],
    [
        'name' => 'shop.order.dispatched',
        'fields' => [
            'carrier' => 'string',
        ],
    ],
];
