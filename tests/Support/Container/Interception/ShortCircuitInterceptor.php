<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The short-circuit link: an interceptor that answers its own
 * sentinel -- carried as a constructor argument so the knob's service
 * reference grammar can spell it -- without calling `$next()`: the
 * construction never runs, the sentinel becomes the instance (still
 * through the resolved listeners and the per-lifetime memoisation).
 */
final readonly class ShortCircuitInterceptor implements ServiceInterceptor
{
    public function __construct(
        public object $sentinel,
    ) {
    }

    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        return $this->sentinel;
    }
}
