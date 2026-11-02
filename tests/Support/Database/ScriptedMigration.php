<?php

declare(strict_types=1);

namespace Zef\Test\Support\Database;

use Closure;
use Override;
use Zef\Framework\Database\ConnectionInterface;
use Zef\Framework\Database\MigrationInterface;
use Zef\Framework\Database\SqlQuery;

/**
 * Scripted migration double: the declarative body the runner books --
 * raw statement lists executed in order, or closures over the connection
 * when a test needs to observe the run (the lock row a heartbeat left
 * behind, a mid-step clock jump, a second runner's steal attempt).
 *
 * The statement lists and the closures are alternative spellings of the
 * same two verbs: a closure, when given, owns its side of the double.
 */
final readonly class ScriptedMigration implements MigrationInterface
{
    /** @var Closure(ConnectionInterface): void the up() body */
    private Closure $up;

    /** @var Closure(ConnectionInterface): void the down() body */
    private Closure $down;

    /**
     * @param list<string>                            $upStatements   raw SQL applied by up()
     * @param list<string>                            $downStatements raw SQL applied by down()
     * @param null|Closure(ConnectionInterface): void $upClosure      owns up() when given
     * @param null|Closure(ConnectionInterface): void $downClosure    owns down() when given
     */
    public function __construct(
        private string $versionStamp,
        private string $migrationName = 'm',
        array $upStatements = ['SELECT 1'],
        array $downStatements = ['SELECT 1'],
        ?Closure $upClosure = null,
        ?Closure $downClosure = null,
        private ?float $lockTtl = null,
    ) {
        $this->up = $upClosure ?? $this->runStatements($upStatements);
        $this->down = $downClosure ?? $this->runStatements($downStatements);
    }

    #[Override]
    public function version(): string
    {
        return $this->versionStamp;
    }

    #[Override]
    public function name(): string
    {
        return $this->migrationName;
    }

    #[Override]
    public function up(ConnectionInterface $connection): void
    {
        ($this->up)($connection);
    }

    #[Override]
    public function down(ConnectionInterface $connection): void
    {
        ($this->down)($connection);
    }

    #[Override]
    public function getLockTtl(): ?float
    {
        return $this->lockTtl;
    }

    /**
     * @param list<string> $statements
     *
     * @return Closure(ConnectionInterface): void
     */
    private function runStatements(array $statements): Closure
    {
        return static function (ConnectionInterface $connection) use ($statements): void {
            foreach ($statements as $statement) {
                $connection->execute(SqlQuery::raw($statement));
            }
        };
    }
}
