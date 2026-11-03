<?php

declare(strict_types=1);

namespace Zef\Test\Database;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Database\RetryMessages;
use Zef\Framework\Database\UnitOfWorkRetryPolicy;

/**
 * The transient-failure taxonomy's message lane: every default
 * lost-connection pattern answered by a representative driver message
 * (the spellings mysqlnd and libpq emit when they fill no informative
 * code), the case-insensitive containment that absorbs the reference's
 * dual-case spellings, and the honest refusals — the messages that
 * name configuration defects, not transient losses, stay out.
 *
 * @internal
 */
#[CoversClass(RetryMessages::class)]
#[CoversClass(UnitOfWorkRetryPolicy::class)]
final class RetryMessagesTest extends TestCase
{
    /**
     * The pattern curriculum: every default pattern, each carried by a
     * message its driver family actually answers — a lane whose list
     * only half-remembers its spellings is a lane that silently stops
     * retrying the failures it exists for. The walk rides the constant
     * itself, so a pattern added without its curriculum message (or a
     * typo in either) fails here, not in production.
     */
    public function testEveryDefaultPatternAnswersItsDriverMessage(): void
    {
        /** @var array<string, string> $driverMessages the curriculum, keyed by pattern */
        $driverMessages = [
            // The mysqlnd family.
            'server has gone away' => 'SQLSTATE[HY000]: General error: 2006 MySQL server has gone away',
            'lost connection' => 'Lost connection to MySQL server during query',
            'error while sending' => 'Error while sending QUERY packet. PID = 42',
            'broken pipe' => 'MySQL server has gone away: Broken pipe',
            // The TCP/socket family (driver code 2002's transient arms).
            'connection refused' => 'SQLSTATE[HY000] [2002] Connection refused',
            'connection timed out' => 'SQLSTATE[HY000] [2002] Connection timed out',
            'network is unreachable' => 'SQLSTATE[HY000] [2002] Network is unreachable',
            'temporary failure in name resolution' => 'php_network_getaddresses: getaddrinfo failed:'
                . ' Temporary failure in name resolution',
            'reset by peer' => 'readv() failed: Connection reset by peer',
            // The libpq family.
            'server closed the connection unexpectedly' => 'SQLSTATE[08006] server closed the connection unexpectedly',
            'no connection to the server' => 'no connection to the server',
            'ssl connection has been closed unexpectedly' => 'SSL connection has been closed unexpectedly',
            'ssl syscall error' => 'SQLSTATE[HY000]: General error: 7 SSL SYSCALL error: EOF detected',
            'decryption failed or bad record mac' => 'SSL error: decryption failed or bad record mac',
            // The generic spelling both drivers answer.
            'connection lost' => 'The connection lost while executing the query',
        ];

        foreach (UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS as $pattern) {
            $message = $driverMessages[$pattern] ?? null;
            self::assertNotNull($message, "the curriculum carries a driver message for pattern '{$pattern}'");
            self::assertTrue(
                RetryMessages::matches($message, [$pattern]),
                "pattern '{$pattern}' must answer its driver message",
            );
        }
    }

    /**
     * A driver's case spelling is not a contract: the reference list
     * carries 'server has gone away' AND 'Server has gone away' as two
     * entries for exactly this reason — the case-insensitive spelling
     * carries them as one.
     */
    public function testTheContainmentIsCaseInsensitive(): void
    {
        self::assertTrue(RetryMessages::matches('SERVER HAS GONE AWAY', ['server has gone away']));
        self::assertTrue(RetryMessages::matches('Server Has Gone Away', ['server has gone away']));
        self::assertTrue(RetryMessages::matches('SQLSTATE[HY000] [2002] CONNECTION REFUSED', ['connection refused']));
        $closed = 'SERVER CLOSED THE CONNECTION UNEXPECTEDLY';
        self::assertTrue(RetryMessages::matches($closed, ['server closed the connection unexpectedly']));
    }

    public function testTheLaneRefusesAMessageNoPatternCarries(): void
    {
        self::assertFalse(
            RetryMessages::matches('SQLSTATE[42601] syntax error at or near "FROM"', ['server has gone away']),
        );
        self::assertFalse(
            RetryMessages::matches('syntax error', UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS),
        );
    }

    /**
     * The deliberate exclusions, pinned: a missing unix socket is a
     * configuration defect a retry cannot heal, and a permanent DNS
     * failure is a defect too — the 2002 family's transient arms are
     * curated in, its configuration arms stay out.
     */
    public function testTheConfigurationDefectSpellingsStayOut(): void
    {
        $missingSocket = 'SQLSTATE[HY000] [2002] No such file or directory';
        self::assertFalse(
            RetryMessages::matches($missingSocket, UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS),
        );

        $permanentDns = 'php_network_getaddresses: getaddrinfo failed: Name or service not known';
        self::assertFalse(
            RetryMessages::matches($permanentDns, UnitOfWorkRetryPolicy::DEFAULT_RETRYABLE_MESSAGE_PATTERNS),
        );
    }

    public function testAnEmptyPatternListMatchesNothing(): void
    {
        self::assertFalse(RetryMessages::matches('MySQL server has gone away', []));
    }

    public function testAnyOnePatternAnswersAlone(): void
    {
        // A host narrowing the knob to a single spelling keeps a
        // working lane — the containment probes the list it is handed.
        self::assertTrue(RetryMessages::matches('SQLSTATE[HY000] [2002] Connection refused', ['connection refused']));
        self::assertFalse(RetryMessages::matches('SQLSTATE[HY000] [2002] Connection refused', ['broken pipe']));
    }
}
