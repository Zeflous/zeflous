<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Interception;

use Override;
use Zef\Framework\Container\ResolvingListener;

/**
 * The resolving event's observation point: a listener that records
 * every identifier whose construction started, into the shared log.
 */
final readonly class RecordingResolvingListener implements ResolvingListener
{
    public function __construct(
        private InterceptionLog $interceptionLog,
    ) {
    }

    #[Override]
    public function resolving(string $id): void
    {
        $this->interceptionLog->record('resolving: ' . $id);
    }
}
