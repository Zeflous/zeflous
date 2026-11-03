<?php

declare(strict_types=1);

namespace Zef\Framework\Database;

/**
 * The transient-failure taxonomy's message lane: the lost-connection
 * spellings a driver emits when it fills no informative SQLSTATE or
 * driver code -- the handshake failure, the socket error, the code-0
 * wrapper -- answered by case-insensitive containment against the
 * policy's curated patterns.
 *
 * The complement, never the replacement, of the code lane
 * ({@see RetryCandidates}): the class-name gate still applies upstream
 * ({@see RetryTaxonomy}), and a code the taxonomy already lists wins
 * before any pattern is probed. The lane exists because a lost
 * connection regularly arrives with nothing but its message -- a
 * refused socket answers 2002 in no slot a strict comparison reads,
 * and a handshake failure answers code 0 -- the gap the reference
 * audit recorded against the field-proven posture (Laravel's
 * {@see https://github.com/laravel/framework/blob/11.x/src/Illuminate/Database/DetectsLostConnections.php
 * DetectsLostConnections}).
 *
 * @internal
 */
final class RetryMessages
{
    /**
     * True when the message contains one of the retryable patterns,
     * case-insensitively -- a driver's case spelling is not a contract,
     * so one entry absorbs every casing (the reference list carries
     * 'server has gone away' and 'Server has gone away' as two entries
     * for exactly this reason; the case-insensitive spelling carries
     * them as one).
     *
     * @param list<string> $retryableMessagePatterns
     */
    public static function matches(string $message, array $retryableMessagePatterns): bool
    {
        return array_any(
            $retryableMessagePatterns,
            static fn (string $pattern): bool => mb_stripos($message, $pattern) !== false,
        );
    }
}
