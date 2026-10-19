<?php

declare(strict_types=1);

namespace Zef\Test\Support\Excëptïon2;

use Zef\Framework\Exception\ZefException;

/**
 * A typed-exception shell whose derived error code crosses the 64-character
 * truncation bound from inside a namespace that carries two extra multibyte
 * bytes and ends on a digit before the final separator: the short class name
 * is a 65-character digit run, so the default error code keeps exactly the
 * first 64 characters, and any off-by-one in the separator arithmetic or the
 * bound changes the answer's length, head, or validity.
 */
final class U1111111111111111111111111111111111111111111111111111111111111111Exception extends ZefException
{
}
