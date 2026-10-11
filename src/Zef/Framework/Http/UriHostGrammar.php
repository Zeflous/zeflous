<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The URI host grammar: normalization (trim, lowercase, bracket-strip) and
 * validation of reg-names, IPv4 and IPv6 literals.
 *
 * Pure static helper, extracted from the parse pipeline so both the
 * constructor's parse and the withHost() wither consult one grammar.
 * RFC 3986 reg-name also permits '_' (common for intranet hosts, RFC 9110
 * Host = reg-name); the DNS-only class the reference implementation
 * replaced did not. The bracket strip is a capture over the address class
 * itself, so an IPv6 literal never re-enters validation with its brackets
 * and a non-address bracketed host is judged as written.
 */
final class UriHostGrammar
{
    private const string HOST_LABEL = '[A-Za-z0-9_](?:[A-Za-z0-9_-]{0,61}[A-Za-z0-9_])?';

    private const string HOST_NAME_PATTERN = '/^(?=.{1,253}$)' . self::HOST_LABEL
        . '(?:\.' . self::HOST_LABEL . ')*$/';

    private const string BRACKETED_HOST_PATTERN = '/^\[(?<address>[0-9A-Fa-f:.]*)\]\z/';

    /**
     * Normalizes and validates a host: surrounding whitespace trimmed,
     * lowercased with the full Unicode case map, IPv6 brackets stripped,
     * then fail-closed validation.
     *
     * @throws HttpException when the host is not a valid reg-name, IPv4 or
     *                       IPv6 literal
     */
    public static function sanitize(string $host): string
    {
        $host = mb_strtolower(mb_trim($host));
        if (preg_match(self::BRACKETED_HOST_PATTERN, $host, $matches) === 1) {
            $host = $matches['address'];
        }

        self::assertHost($host);

        return $host;
    }

    /**
     * @throws HttpException when a non-empty host is not a valid reg-name,
     *                       IPv4 or IPv6 literal
     */
    private static function assertHost(string $host): void
    {
        if ($host === '') {
            return;
        }

        UriGrammar::assertNoControls($host, 'URI host');
        if (str_contains($host, ':')) {
            if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV6) === false) {
                throw new HttpException('Invalid URI host.');
            }

            return;
        }

        if (filter_var($host, \FILTER_VALIDATE_IP, \FILTER_FLAG_IPV4) !== false) {
            return;
        }

        if (preg_match(self::HOST_NAME_PATTERN, $host) !== 1) {
            throw new HttpException('Invalid URI host.');
        }
    }
}
