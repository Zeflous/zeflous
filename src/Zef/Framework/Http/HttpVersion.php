<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The HTTP protocol version grammar: "digit" or "digit.digit" -- the
 * spellings HTTP/1.x carries and HTTP/2 negotiation answers ("2",
 * "2.0"), plus HTTP/3 ("3").
 */
final class HttpVersion
{
    private const string VERSION_PATTERN = '/^\d(?:\.\d)?\z/';

    /**
     * @throws HttpException when the version is not a valid HTTP
     *                       version spelling
     */
    public static function assert(string $version): void
    {
        if (preg_match(self::VERSION_PATTERN, $version) !== 1) {
            throw new HttpException('Invalid HTTP protocol version.');
        }
    }
}
