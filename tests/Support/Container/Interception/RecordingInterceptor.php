<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The onion's observation point: an interceptor that records its own
 * name and the identifier on both sides of the `$next()` call, into
 * the shared log its siblings share -- the tape the ordering and
 * nesting tests read -- and always calls `$next()` so the inner layers
 * still run.
 */
final readonly class RecordingInterceptor implements ServiceInterceptor
{
    public function __construct(
        private string $name,
        private InterceptionLog $interceptionLog,
    ) {
    }

    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        $this->interceptionLog->record($this->name . ' > ' . $id);
        $answer = $next();
        $this->interceptionLog->record($this->name . ' < ' . $id);

        return $answer;
    }
}
