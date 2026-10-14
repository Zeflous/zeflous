<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Exception\ZefException;

/**
 * Thrown when the middleware pipeline is misconfigured (fail closed).
 *
 * Every configuration ambiguity -- an unknown stack entry shape, a priority
 * that is not numeric, a middleware service that is not registered, a
 * service that does not implement the PSR-15 contract -- is a boot-time
 * failure raised through this class. The pipeline never silently drops or
 * reorders a declared middleware.
 */
final class MiddlewareException extends ZefException
{
}
