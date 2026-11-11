<?php

declare(strict_types=1);

namespace Zef\Test\Support\Database;

use Override;
use PDO;
use PDOException;
use PDOStatement;

/**
 * Recording PDO double: a real SQLite handle over `:memory:` whose
 * driver primitives (BEGIN/COMMIT/ROLLBACK) and administrative
 * statements (SAVEPOINT/RELEASE/ROLLBACK TO) are recorded as the verb
 * stream the nested-transaction machine issues -- the shape the
 * transaction family's boundary needs to evidence WHICH statements the
 * walk spelled, not just the data outcomes they leave behind (a
 * savepoint-only walk can answer the same rows a BEGIN-ed one does).
 *
 * The last-insert identity answer is steerable: the port's null
 * contract folds the driver's `''`/`false`/`'0'` spellings, and only a
 * double can produce the `false` arm a real SQLite handle never
 * answers. The default `'0'` spelling is the no-insert answer.
 *
 * The three refusal arms are steerable too: a commit that throws, a
 * rollback that throws and an administrative statement that throws --
 * the driver-refusal paths a healthy real handle can never produce for
 * the mapping folds to be observed under. Statement execution can be
 * bypassed entirely for the network drivers' spellings (SET TRANSACTION
 * above all) that the SQLite engine would refuse at parse time: the
 * verb stream still carries them.
 *
 * The dead-socket arm is the healing fold's evidence source: a
 * `deadSocket` exception armed on the double makes EVERY driver ask on
 * it throw that same instance -- sticky by construction, because a
 * socket that died mid-flight stays dead, and only a test whose
 * SECOND ask lands on a re-materialised handle can ever observe a
 * success after it.
 */
final class RecordingPdo extends PDO
{
    /** @var list<string> the driver verbs and statements, in issue order */
    public array $verbs = [];

    /** @var false|string the identity the lastInsertId double answers */
    public false|string $lastInsertIdAnswer = '0';

    public bool $refuseCommit = false;

    public bool $refuseRollback = false;

    public bool $refuseStatements = false;

    public bool $runStatements = true;

    /** @var null|PDOException when armed, every driver ask on this handle throws it -- the dead-socket arm */
    public ?PDOException $deadSocket = null;

    public function __construct()
    {
        parent::__construct('sqlite::memory:');
    }

    #[Override]
    public function beginTransaction(): bool
    {
        $this->answerDeadSocket();
        $this->verbs[] = 'BEGIN';

        return parent::beginTransaction();
    }

    #[Override]
    public function commit(): bool
    {
        $this->answerDeadSocket();
        $this->verbs[] = 'COMMIT';

        if ($this->refuseCommit) {
            throw new PDOException('simulated driver refusal');
        }

        return parent::commit();
    }

    #[Override]
    public function rollBack(): bool
    {
        $this->answerDeadSocket();
        $this->verbs[] = 'ROLLBACK';

        if ($this->refuseRollback) {
            throw new PDOException('simulated rollback refusal');
        }

        return parent::rollBack();
    }

    /**
     * @param array<array-key, mixed> $options
     */
    #[Override]
    public function prepare(string $query, array $options = []): false|PDOStatement
    {
        $this->answerDeadSocket();

        return parent::prepare($query, $options);
    }

    #[Override]
    public function exec(string $statement): false|int
    {
        $this->answerDeadSocket();
        $this->verbs[] = $statement;

        if ($this->refuseStatements) {
            throw new PDOException('simulated statement refusal');
        }

        if (!$this->runStatements) {
            return 0;
        }

        return parent::exec($statement);
    }

    #[Override]
    public function lastInsertId(?string $name = null): false|string
    {
        $this->answerDeadSocket();

        return $this->lastInsertIdAnswer;
    }

    /**
     * The dead-socket guard: when the arm is set, the ask dies before
     * the engine ever sees it -- the socket is gone, and every verb
     * below the recording seam shares the one refusal.
     */
    private function answerDeadSocket(): void
    {
        if ($this->deadSocket instanceof PDOException) {
            throw $this->deadSocket;
        }
    }
}
