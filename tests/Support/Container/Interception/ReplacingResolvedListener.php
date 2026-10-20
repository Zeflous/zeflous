<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Override;
use Zef\Framework\Container\ResolvedListener;

/**
 * The resolved event's replacement point: a listener that records the
 * replacement and answers {@see ReplacedService} over the instance --
 * the non-null answer the memoisation records instead of the
 * constructed instance.
 */
final readonly class ReplacingResolvedListener implements ResolvedListener
{
    public function __construct(
        private InterceptionLog $interceptionLog,
    ) {
    }

    #[Override]
    public function resolved(string $id, mixed $instance): mixed
    {
        $this->interceptionLog->record('replaced: ' . $id);

        return new ReplacedService($instance);
    }
}
