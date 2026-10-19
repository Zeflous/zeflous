<?php

declare(strict_types=1);

namespace Zef\Test\Support\Exception;

use Zef\Framework\Exception\ZefException;

/**
 * A concrete shell over the typed exception root for tests that exercise
 * the constructor's own contracts -- the error code, the trace id and the
 * developer details -- or that simply need a typed failure carrying
 * explicit diagnostic material. The constructor is inherited as-is: the
 * root's validation and the details' redaction are the behaviour under
 * test, so the shell adds nothing.
 */
final class ExplanatoryException extends ZefException
{
}
