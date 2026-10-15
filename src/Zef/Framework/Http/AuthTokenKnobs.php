<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The auth family's token-table knob reading: the two spellings one
 * list carries -- a native list of strings (the file's declaration)
 * or the comma-separated string an environment overlay writes
 * (`APP_SECURITY__AUTH__TOKENS=first,second`) -- over the shared
 * {@see ListKnobs} reading the rate-limit family's trusted-proxy list
 * and this family's token table both follow.
 *
 * The shape of each entry (the token68 grammar, the entropy floor,
 * the ceiling) is the bounds' own ({@see AuthBounds::assertTokenShape()}),
 * asserted by the policy once the table is read.
 */
final class AuthTokenKnobs
{
    /**
     * Reads the static token table knob's value.
     *
     * @return list<string>
     *
     * @throws HttpException when the value is neither a list of strings
     *                       nor a comma-separated string
     */
    public static function read(mixed $value): array
    {
        return ListKnobs::read($value, 'security.auth tokens');
    }
}
