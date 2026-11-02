<?php

declare(strict_types=1);

namespace Zef\Test\Support\Database;

use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\Repository;

/**
 * The gateway double's concrete face: a table-data-gateway bound to the
 * `users` table (or whichever table a test spells), over whatever
 * connection it is handed -- the real SQLite handle for the round trips,
 * the scripted connection for the decode guards.
 */
final class UsersRepository extends Repository
{
    public function __construct(ConnectionInterface $connection, string $table = 'users')
    {
        parent::__construct($connection, $table);
    }

    /**
     * The bound builder's spelling, exposed for the suite: the
     * protected table-builder fold is part of the gateway's extension
     * surface (a private spelling would refuse this very call).
     */
    public function tableSql(): string
    {
        return $this->qb()->build()->sql;
    }
}
