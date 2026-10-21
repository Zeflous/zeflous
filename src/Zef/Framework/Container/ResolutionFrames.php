<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The ledger of in-progress wider-lifetime resolution frames.
 *
 * The boot-time graph refuses a singleton edge into a request-scoped
 * definition before a single service resolves ({@see LifetimeCaptures})
 * -- but the programmatic `set()` path never walks that graph, and a
 * singleton factory that resolves a request-scoped id mid-construction
 * captures the instance into the singleton's forever memo: the classic
 * cross-request leak under the persistent workers the kernel targets.
 * This ledger is the runtime mirror of the boot verdict: the singleton
 * lane opens a frame around its factory call, and the request lane
 * refuses any request-scoped id that resolves beneath an open frame
 * ({@see RequestScopeRefusals::capturedByAWiderFrame()}) -- the capture
 * the declarative graph already refuses, answered at the one moment it
 * can still be seen.
 *
 * The ledger is depth-honest: nested singleton frames name the
 * innermost captor (the resolution whose memo will actually hold the
 * instance), and the frame closes in `finally` so a refused or thrown
 * factory never leaves a stale captor behind.
 */
final class ResolutionFrames
{
    /** @var list<string> the singleton ids whose factories are currently resolving, outermost first */
    private array $singletonFrames = [];

    /**
     * The innermost open singleton frame's id, or null when no wider
     * frame is resolving.
     */
    public function capturingSingleton(): ?string
    {
        return $this->singletonFrames === []
            ? null
            : $this->singletonFrames[\count($this->singletonFrames) - 1];
    }

    /**
     * Opens a singleton frame around one resolution.
     *
     * @param string           $id         the singleton whose factory is resolving
     * @param Closure(): mixed $resolution the resolution the frame wraps
     */
    public function withinSingleton(string $id, Closure $resolution): mixed
    {
        $this->singletonFrames[] = $id;

        try {
            return $resolution();
        } finally {
            array_pop($this->singletonFrames);
        }
    }
}
