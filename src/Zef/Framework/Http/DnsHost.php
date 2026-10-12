<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Answers whether a host is a valid DNS name.
 *
 * The grammar is RFC 1123, stated as one anchored pattern: dot-separated
 * labels of one to sixty-three alphanumeric characters with hyphens
 * forbidden at the edges (the first and last character classes exclude
 * the hyphen while the middle admits it), the underscore the reg-name
 * grammar accepts (intranet names such as `my_service.internal` must
 * validate identically here and in {@see UriHostGrammar}), and the
 * whole name at most 253 bytes. The reference implementation's
 * validator, reformulated as the layer's grammar-collaborator house
 * style with identical answers: every byte the label classes reject
 * also failed the reference implementation's alphabet walk, and a
 * trailing root dot is stripped by the caller's normalisation instead.
 */
final class DnsHost
{
    /**
     * The whole DNS-name grammar: labels of 1..63 bytes with hyphens
     * only inside, joined by dots, at most 253 bytes in total.
     */
    private const string DNS_PATTERN = '~^(?!.{254})'
        . '[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?'
        . '(?:\.[a-z0-9_](?:[a-z0-9_-]{0,61}[a-z0-9_])?)*\z~';

    /**
     * Answers whether the host is a valid DNS name.
     */
    public static function isValid(string $host): bool
    {
        return preg_match(self::DNS_PATTERN, $host) === 1;
    }
}
