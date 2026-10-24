<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's bounds: the two range invariants the family
 * holds, asserted at the policy's construction -- the {@see CsrfBounds}
 * pattern over the session knobs.
 *
 * The idle timeout's corridor (1..2_592_000, one second to thirty
 * days) exists to catch the minutes-instead-of-seconds unit mistake a
 * raw integer knob invites: a timeout spelled as `30` where `1800`
 * was meant retires every session half a minute in, and the floor
 * keeps the knob honest the other way. The identifier entropy's
 * corridor (16..64 bytes) holds the CSRF family's own 16-byte floor
 * -- a session identifier is a longer-lived bearer credential than a
 * CSRF token, so it rides above the same floor -- and its ceiling
 * keeps the folded base64url spelling inside the cookie value's own
 * grammar at every step.
 *
 * The asserts answer at the constructor rather than the map reading
 * so the corridor holds at BOTH construction paths: the config-driven
 * one ({@see SessionPolicy::fromMap()}) and the programmatic one
 * (direct construction) -- a host spelling an out-of-corridor value
 * by hand gets the same named halt an operator's typo gets.
 */
final class SessionBounds
{
    private const int MIN_IDLE_TIMEOUT = 1;

    private const int MAX_IDLE_TIMEOUT = 2592000;

    private const int MIN_ID_BYTES = 16;

    private const int MAX_ID_BYTES = 64;

    /**
     * Asserts the idle timeout's corridor.
     *
     * @param int $idleTimeout the storage-side sliding expiry, in seconds
     *
     * @throws HttpException when the timeout is outside 1..2592000
     */
    public static function assertIdleTimeout(int $idleTimeout): void
    {
        if ($idleTimeout < self::MIN_IDLE_TIMEOUT || $idleTimeout > self::MAX_IDLE_TIMEOUT) {
            throw new HttpException(
                'The http.session idle_timeout knob must be between 1 and 2592000, '
                . $idleTimeout . ' given.',
            );
        }
    }

    /**
     * Asserts the identifier entropy's corridor.
     *
     * @param int $idBytes the session identifier's entropy, in bytes
     *
     * @throws HttpException when the entropy is outside 16..64
     */
    public static function assertIdBytes(int $idBytes): void
    {
        if ($idBytes < self::MIN_ID_BYTES || $idBytes > self::MAX_ID_BYTES) {
            throw new HttpException(
                'The http.session id_bytes knob must be between 16 and 64, '
                . $idBytes . ' given.',
            );
        }
    }
}
