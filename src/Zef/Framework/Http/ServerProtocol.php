<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Normalises the `SERVER_PROTOCOL` token into a PSR-7 protocol version.
 *
 * SAPIs expose the raw request-line protocol (`HTTP/1.1`, `HTTP/2`, ...)
 * while PSR-7 messages carry only the version (`1.1`, `2`). The reference
 * implementation's two-stage grammar is preserved verbatim: a dotted
 * major.minor pair first, then a bare single-digit major (bug fix #19:
 * `HTTP/2` must answer `2`, not `1.1`), and any malformed token -- a
 * two-digit major such as `HTTP/22`, a suffix, garbage -- degrades to the
 * `1.1` default so an unspeakable protocol line never aborts extraction.
 * The result is re-validated by {@see HttpVersion} in the message
 * constructor, keeping this normaliser a pure total function.
 */
final class ServerProtocol
{
    private const string DEFAULT_VERSION = '1.1';

    /**
     * Answers the protocol version for a raw `SERVER_PROTOCOL` token.
     *
     * @param string $protocol the raw protocol token, empty when absent
     *
     * @return non-empty-string the version the message constructor validates
     */
    public static function version(string $protocol): string
    {
        if (preg_match('/^HTTP\/(\d+\.\d+)$/', $protocol, $matches) === 1) {
            return $matches[1];
        }

        if (preg_match('/^HTTP\/(\d)$/', $protocol, $matches) === 1) {
            return $matches[1];
        }

        return self::DEFAULT_VERSION;
    }
}
