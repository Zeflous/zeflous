<?php

declare(strict_types=1);

namespace Zef\Test\Support\Excëption;

use Zef\Framework\Exception\ZefException;

/**
 * A typed-exception shell whose namespace segment carries a multibyte
 * byte before the final separator. The default error code derives its
 * short class name from the separator position, and only a name with a
 * multibyte byte before that separator makes the character-position and
 * byte-position arithmetic disagree -- every ASCII namespace answers the
 * same position under both scales.
 */
final class UnicodeNamespaceException extends ZefException
{
}
