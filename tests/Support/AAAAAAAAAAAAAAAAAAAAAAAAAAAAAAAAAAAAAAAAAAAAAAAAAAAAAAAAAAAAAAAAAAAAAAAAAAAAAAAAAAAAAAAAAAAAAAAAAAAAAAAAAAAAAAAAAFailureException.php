<?php

declare(strict_types=1);

namespace Zef\Test\Support;

use RuntimeException;

/**
 * The over-long failure the probe truncation test reads: a fully
 * qualified class name past the 128-character bound the crash
 * containment's message carries -- the one fixture that crosses it,
 * because the bound is only observable from the far side. The class
 * declaration line cannot be split, so the coding standard's soft
 * line limit excludes this one file (the exclusion is documented in
 * `phpcs.xml.dist` beside this fixture's own record).
 */
final class AAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAAFailureException extends RuntimeException
{
}
