<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Override;
use Zef\Framework\Container\ResolvedListener;

/**
 * The resolved event's observation point: a listener that records
 * every identifier it observed and answers null -- the keep spelling:
 * the constructed instance is what the memoisation records.
 */
final readonly class ObservingResolvedListener implements ResolvedListener
{
    public function __construct(
        private InterceptionLog $interceptionLog,
    ) {
    }

    #[Override]
    public function resolved(string $id, mixed $instance): mixed
    {
        $this->interceptionLog->record('observed: ' . $id);

        return null;
    }
}
