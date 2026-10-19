<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The materialisation's reference proof: a class-shaped interceptor
 * whose constructor takes a service reference -- resolved through the
 * container once, at the boot's materialisation, through the plain
 * lane (the onion does not exist yet). The pass-through link keeps the
 * proof about the arm alone.
 */
final readonly class DependentInterceptor implements ServiceInterceptor
{
    public function __construct(
        public object $dependency,
    ) {
    }

    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        return $next();
    }
}
