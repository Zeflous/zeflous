<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The constructor fit's null-constructor boundary: an interceptor with
 * no constructor at all -- the reflection's honest empty parameter
 * list, an empty declared plan's own fit (the optional boundary's
 * degenerate sibling, kept as its own fixture so the empty list a
 * class-without-a-constructor answers stays observable).
 */
final class ParameterlessInterceptor implements ServiceInterceptor
{
    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        return $next();
    }
}
