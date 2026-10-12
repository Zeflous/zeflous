<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Derives the comparison form of a host and answers its validity.
 *
 * The comparison form is the reference implementation's, move-verbatim:
 * trimmed, lowercased through the full Unicode case map (the Kelvin
 * sign lower-cases to the ASCII `k`), and with exactly one FQDN root
 * dot stripped ('example.com.') because the URI host grammar rejects
 * trailing dots -- the same header must not half-validate here and then
 * crash downstream. Validity is an address literal (IPv4 or IPv6) or a
 * {@see DnsHost} name.
 */
final class HostComparison
{
    /**
     * Answers the host in its comparison form.
     */
    public static function normalise(string $host): string
    {
        return mb_strtolower(preg_replace('/\.\z/', '', mb_trim($host)) ?? '');
    }

    /**
     * Answers whether the host is an address literal or a valid DNS name.
     */
    public static function isValid(string $host): bool
    {
        return filter_var($host, \FILTER_VALIDATE_IP) !== false || DnsHost::isValid($host);
    }
}
