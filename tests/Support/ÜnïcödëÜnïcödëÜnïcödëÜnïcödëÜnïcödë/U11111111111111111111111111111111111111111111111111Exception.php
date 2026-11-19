<?php

declare(strict_types=1);

namespace Zef\Test\Support\ÜnïcödëÜnïcödëÜnïcödëÜnïcödëÜnïcödë;

use Zef\Framework\Exception\ZefException;

/**
 * A typed-exception shell whose fully qualified name crosses the
 * crash-containment truncation bound (128) in BYTES while staying
 * under it in CHARACTERS, with the multibyte half of the identifier
 * grammar riding the namespace segments ahead of an ASCII class
 * name: the character-position cut and the byte-position cut then
 * disagree about where the containment bound lands, and only the
 * character scale keeps the record's own name intact -- every
 * ASCII-only name answers the same position under both scales.
 */
final class U11111111111111111111111111111111111111111111111111Exception extends ZefException
{
}
