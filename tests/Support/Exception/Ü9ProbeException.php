<?php

declare(strict_types=1);

namespace Zef\Test\Support\Exception;

use Zef\Framework\Exception\ZefException;

/**
 * A typed-exception shell whose class name begins with a multibyte letter
 * followed by a digit. PHP refuses a leading digit, so the letter has to
 * be multibyte to place a digit at the head of the derived error code --
 * the one shape whose normalisation falls back to FRAMEWORK_ERROR instead
 * of starting with an uppercase ASCII letter.
 */
final class Ü9ProbeException extends ZefException
{
}
