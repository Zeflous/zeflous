<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The engine's fopen() mode grammar: one leading access letter, an optional
 * translation flag, an optional update flag and an optional
 * portability/e-directive marker, in either flag order.
 *
 * The dual anchored patterns are the reference implementation's grammar
 * verbatim (legacy `zef-framework` `Psr17Factory`), including the rejection
 * message -- only the exception root is folded into the layer's own. Static
 * helper; the factory is its single consumer.
 */
final class StreamMode
{
    private const string FLAG_THEN_UPDATE_PATTERN = '/^[rwaxtc](?:[bt])?(?:\+)?e?\z/';

    private const string UPDATE_THEN_FLAG_PATTERN = '/^[rwaxtc](?:\+)?[bt]e?\z/';

    /**
     * @param string $mode the fopen() mode to validate
     *
     * @psalm-assert non-empty-string $mode
     *
     * @throws HttpException when the mode does not match the grammar
     */
    public static function assert(string $mode): void
    {
        if (
            preg_match(self::FLAG_THEN_UPDATE_PATTERN, $mode) !== 1
            && preg_match(self::UPDATE_THEN_FLAG_PATTERN, $mode) !== 1
        ) {
            throw new HttpException("Invalid stream mode '{$mode}'.");
        }
    }
}
