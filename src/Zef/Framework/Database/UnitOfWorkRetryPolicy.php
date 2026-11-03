<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

use InvalidArgumentException;
use Throwable;

/**
 * Opt-in retry policy for the {@see UnitOfWork} flush phase.
 *
 * The framework's standing contract is "the surrounding transaction
 * rollback is the failure story; a retried command re-records fresh
 * operations". This value object carves a narrow exception for
 * transient DB failures at the COMMIT/FLUSH boundary — deadlock,
 * lock-wait-timeout, serialization-failure — where retrying the same
 * writes on a fresh connection snapshot is safe and the alternative
 * is surfacing a 5xx to the caller for a problem that resolves itself
 * in milliseconds.
 *
 * Design contract:
 *
 * - Only the FLUSH phase is retried — never the command handler body.
 *   Side effects produced during dispatch (event publishes, log writes)
 *   cannot be re-run safely.
 * - The UnitOfWork queue is cleared on the FIRST flush attempt
 *   ({@see UnitOfWork::flush()}'s own behaviour); a retry via
 *   {@see UnitOfWork::flushRetrying()} therefore replays the queue
 *   snapshot it preserved. Callers that need deterministic ordering
 *   should NOT enable retry.
 * - Default is OFF: the no-retry behaviour is simply not calling
 *   {@see UnitOfWork::flushRetrying()}.
 *
 * The telemetry lane's {@see \Zef\Framework\Telemetry\RetryBackoffPolicy}
 * is the validation/backoff sibling this value object mirrors, adding
 * the transient-failure taxonomy (retryable class names + SQLSTATE
 * codes + lost-connection message patterns) specific to DB writes —
 * the taxonomy's judgement lives in {@see RetryTaxonomy}, the backoff
 * compute in {@see RetryBackoff}, the message lane in
 * {@see RetryMessages}.
 */
final readonly class UnitOfWorkRetryPolicy
{
    /**
     * Default retryable SQLSTATE codes — see
     * https://www.postgresql.org/docs/current/errcodes-appendix.html
     * and https://dev.mysql.com/doc/mysql-errors/mysql-error-rel/en/ .
     *
     * - 40001 (serialization_failure) — Postgres / SQL standard
     * - 40P01 (deadlock_detected) — Postgres
     * - 55P03 (lock_not_available / lock-wait-timeout) — Postgres
     * - 1213 (ER_LOCK_DEADLOCK) — MySQL/MariaDB
     * - 1205 (ER_LOCK_WAIT_TIMEOUT) — MySQL/MariaDB
     * - 1040 (ER_CON_COUNT_ERROR) — too many connections (transient)
     * - 2006 (CR_SERVER_GONE / CR_SERVER_LOST) — connection lost
     *
     * Duplicated inline as the constructor default for
     * `retryableSqlStates` to avoid `self::CONSTANT` as a default value
     * for a promoted property (parser edge case — kept simple so
     * Doctum's nikic/php-parser stays happy).
     */
    public const array DEFAULT_RETRYABLE_SQL_STATES = [
        '40001',
        '40P01',
        '55P03',
        '1213',
        '1205',
        '1040',
        '2006',
    ];

    /**
     * Default retryable lost-connection message patterns — the
     * field-proven spellings the mysqlnd and libpq drivers answer when
     * they fill no informative SQLSTATE or driver code, curated from
     * the reference posture (Laravel's DetectsLostConnections, 11.x).
     *
     * Matched case-insensitively against the throwable's message, as a
     * complement to the code lane: a code the taxonomy lists wins
     * first, and the class-name gate still applies upstream. Every
     * pattern names a lost-connection symptom — the deliberately
     * EXCLUDED reference entries (a missing unix socket's 'no such
     * file or directory', the permanent DNS defect 'name or service
     * not known', the non-PDO drivers' spellings, and the concurrency
     * strings the code taxonomy already owns) are recorded in
     * `docs/ci-notes/database-retry-messages.md`.
     *
     * Duplicated inline as the constructor default for
     * `retryableMessagePatterns` for the same parser-edge-case reason
     * as {@see DEFAULT_RETRYABLE_SQL_STATES}.
     */
    public const array DEFAULT_RETRYABLE_MESSAGE_PATTERNS = [
        // The mysqlnd family.
        'server has gone away',
        'lost connection',
        'error while sending',
        'broken pipe',
        // The TCP/socket family (driver code 2002's transient arms).
        'connection refused',
        'connection timed out',
        'network is unreachable',
        'temporary failure in name resolution',
        'reset by peer',
        // The libpq family.
        'server closed the connection unexpectedly',
        'no connection to the server',
        'ssl connection has been closed unexpectedly',
        'ssl syscall error',
        'decryption failed or bad record mac',
        // The generic spelling both drivers answer.
        'connection lost',
    ];

    /**
     * @param int          $maxAttempts              Total attempts
     *                                               including the first one. Must be >= 1.
     * @param int          $initialDelayMs           Delay before the
     *                                               2nd attempt. Must be >= 0.
     * @param int          $maxDelayMs               Upper bound on the
     *                                               computed backoff. Must be >= $initialDelayMs.
     * @param float        $multiplier               Backoff growth factor.
     *                                               Must be >= 1.0.
     * @param int          $jitterMs                 Randomised +/- jitter
     *                                               added to each delay to avoid thundering-herd. Must be >= 0.
     * @param list<string> $retryableClassNames      FQCNs matched with
     *                                               `instanceof`. A throwable is retryable if it is an instance of
     *                                               any listed class. Default: PDOException (parent for the
     *                                               framework's PDO adapter).
     * @param list<string> $retryableSqlStates       SQLSTATE codes
     *                                               matched against PDOException::getCode() and the
     *                                               PDOException::errorInfo[0] slot. Empty list = match on
     *                                               class names only.
     * @param list<string> $retryableMessagePatterns Lost-connection
     *                                               message patterns matched case-insensitively against
     *                                               the throwable's message when the code lane answers no
     *                                               retryable state — the complement that answers the
     *                                               driver failures no informative code accompanies (the
     *                                               refused socket, the failed handshake, the code-0
     *                                               wrapper). Every pattern must be a non-empty string: an
     *                                               empty pattern would contain-match every message and
     *                                               silently retry the world. Empty list = the code lane
     *                                               alone (the pre-message-lane behaviour, exactly).
     *
     * @throws InvalidArgumentException when any knob sits outside its
     *                                  corridor (the telemetry retry
     *                                  policy's own posture)
     */
    public function __construct(
        public int $maxAttempts = 3,
        public int $initialDelayMs = 100,
        public int $maxDelayMs = 30_000,
        public float $multiplier = 2.0,
        public int $jitterMs = 0,
        public array $retryableClassNames = ['PDOException'],
        public array $retryableSqlStates = [
            '40001',
            '40P01',
            '55P03',
            '1213',
            '1205',
            '1040',
            '2006',
        ],
        public array $retryableMessagePatterns = [
            'server has gone away',
            'lost connection',
            'error while sending',
            'broken pipe',
            'connection refused',
            'connection timed out',
            'network is unreachable',
            'temporary failure in name resolution',
            'reset by peer',
            'server closed the connection unexpectedly',
            'no connection to the server',
            'ssl connection has been closed unexpectedly',
            'ssl syscall error',
            'decryption failed or bad record mac',
            'connection lost',
        ],
    ) {
        if (
            $maxAttempts < 1
            || $initialDelayMs < 0
            || $maxDelayMs < $initialDelayMs
            || $multiplier < 1.0
            || $jitterMs < 0
            || $this->carriesAnInvalidMessagePattern($retryableMessagePatterns)
        ) {
            throw new InvalidArgumentException('Invalid UnitOfWork retry policy.');
        }
    }

    /**
     * True when an additional attempt is permitted — i.e. the policy has
     * not been exhausted yet.
     *
     * @param int $attempt 1-indexed: 1 = first attempt, 2 = first retry, ...
     */
    public function shouldRetry(int $attempt): bool
    {
        return $attempt < $this->maxAttempts;
    }

    /**
     * Milliseconds to sleep before the next attempt. Exponential backoff
     * shaped by `$multiplier`, capped by `$maxDelayMs`, with optional
     * randomised jitter.
     *
     * @param int $attempt 1-indexed: pass the attempt that just failed
     *
     * @throws InvalidArgumentException when the attempt is not positive
     */
    public function delayMs(int $attempt): int
    {
        return RetryBackoff::delayMs(
            $attempt,
            $this->initialDelayMs,
            $this->maxDelayMs,
            $this->multiplier,
            $this->jitterMs,
        );
    }

    /**
     * True when the throwable is retryable per the policy:
     *   - matches one of `$retryableClassNames` via instanceof, AND
     *   - if SQLSTATE filtering is configured, the throwable's SQLSTATE
     *     (from getCode() or PDO errorInfo) is in `$retryableSqlStates`,
     *     OR its message carries one of
     *     `$retryableMessagePatterns`.
     *
     * When `$retryableSqlStates` is empty, only the class-name match
     * applies (the message lane is moot: everything of the class
     * already matches).
     */
    public function isRetryable(Throwable $throwable): bool
    {
        return RetryTaxonomy::matches(
            $throwable,
            $this->retryableClassNames,
            $this->retryableSqlStates,
            $this->retryableMessagePatterns,
        );
    }

    /**
     * The message-pattern corridor's own guard: every pattern must be
     * a non-empty string. A non-string entry would surface as a
     * TypeError at flush time, far from the policy's construction —
     * the corridor refuses it where the policy is spelled; an empty
     * pattern would contain-match every message and silently retry
     * the world.
     *
     * @param array<int, mixed> $retryableMessagePatterns the raw entries,
     *                                                    judged before the
     *                                                    list<string> contract is trusted
     */
    private function carriesAnInvalidMessagePattern(array $retryableMessagePatterns): bool
    {
        return array_any(
            $retryableMessagePatterns,
            static fn ($pattern): bool => !\is_string($pattern) || $pattern === '',
        );
    }
}
