<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The generic RFC 3986 assertions the URI pipeline guards with: control
 * characters, the scheme grammar and the port range, plus the scheme/port
 * default-table the authority renderer consults.
 *
 * Pure static helper -- the same shape as the configuration layer's route
 * grammar. Every assertion fails closed with the layer's exception,
 * byte-identical to the reference implementation (legacy `zef-framework`
 * `Adapters\Http\UriGrammar`). The scheme fold lives here once so the
 * constructor's parse and the withScheme() wither share one policy point.
 */
final class UriGrammar
{
    private const string SCHEME_PATTERN = '/^[A-Za-z][A-Za-z0-9+.-]*\z/';

    private const string CONTROL_PATTERN = '/[\x00-\x1F\x7F]/';

    /**
     * @throws HttpException when the value carries control characters
     */
    public static function assertNoControls(string $value, string $label): void
    {
        if (preg_match(self::CONTROL_PATTERN, $value) === 1) {
            throw new HttpException("Invalid {$label} control characters.");
        }
    }

    /**
     * Lower-cases the scheme with the full Unicode case map (the Kelvin
     * sign folds to ASCII "k") and validates the folded result, so an
     * upper-case or exotic-case spelling normalizes into a valid scheme
     * while anything still unparsable after folding is rejected.
     *
     * @throws HttpException when the scheme carries control characters or
     *                       its folded form is not a valid RFC 3986 scheme
     */
    public static function normalizeScheme(string $scheme): string
    {
        self::assertNoControls($scheme, 'URI scheme');
        $scheme = mb_strtolower($scheme);
        self::assertScheme($scheme);

        return $scheme;
    }

    /**
     * @throws HttpException When a non-null port falls outside 1..65535.
     */
    public static function assertPort(?int $port): void
    {
        if ($port !== null && ($port < 1 || $port > 65535)) {
            throw new HttpException("Invalid port: {$port}.");
        }
    }

    /**
     * A port equal to the scheme's default (http:80, https:443) is
     * indistinguishable from no port at all per PSR-7's "SHOULD omit".
     */
    public static function isDefaultPortForScheme(string $scheme, int $port): bool
    {
        return ($scheme === 'http' && $port === 80)
            || ($scheme === 'https' && $port === 443);
    }

    /**
     * @throws HttpException when a non-empty scheme is not a valid RFC 3986 scheme
     */
    private static function assertScheme(string $scheme): void
    {
        if ($scheme !== '' && preg_match(self::SCHEME_PATTERN, $scheme) !== 1) {
            throw new HttpException('Invalid URI scheme.');
        }
    }
}
