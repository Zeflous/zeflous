<?php

declare(strict_types=1);

namespace Zef\Test\Support\Exception\Excëption;

use RuntimeException;

/**
 * An untyped failure whose namespace segment carries a multibyte letter
 * ahead of the class-name separator: the fault recorder's exception-type
 * tag resolves the short class name through a character-based separator
 * search and a character-based tail read, and this shell is the one shape
 * that can disagree with the byte-based spellings -- the tag must carry
 * the whole short name, never a tail that starts one character late.
 *
 * @internal
 */
final class LocaleFailure extends RuntimeException
{
}
