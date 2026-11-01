<?php

declare(strict_types=1);

/*
 * The module's route table: Standard Route Schema entries, the paths
 * spelled module-locally (the leading slash is the module's own choice).
 */

return [
    [
        'path' => '/orders',
        'method' => ['GET'],
        'handler' => 'ShopOrdersController@index',
        'name' => 'shop.orders',
    ],
    [
        'path' => 'cart',
        'method' => ['POST'],
        'handler' => 'ShopCartController@store',
    ],
];
