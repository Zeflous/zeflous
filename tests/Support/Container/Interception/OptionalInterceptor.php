<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Closure;
use Override;
use Zef\Framework\Container\ServiceInterceptor;

/**
 * The constructor fit's optional boundary: an interceptor whose
 * constructor parameters are all optional -- an empty declared plan
 * lets the constructor's own defaults answer, the fit's honest
 * boundary.
 */
final readonly class OptionalInterceptor implements ServiceInterceptor
{
    public function __construct(
        private string $name = 'optional',
        private ?InterceptionLog $interceptionLog = null,
    ) {
    }

    #[Override]
    public function intercept(string $id, Closure $next): mixed
    {
        $this->interceptionLog?->record($this->name . ' > ' . $id);
        $answer = $next();
        $this->interceptionLog?->record($this->name . ' < ' . $id);

        return $answer;
    }
}
