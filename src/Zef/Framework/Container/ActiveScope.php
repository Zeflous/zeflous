<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Closure;

/**
 * The open request scope as resolution state -- the small guard the
 * scope machinery threads a scoped resolution through (the same
 * pattern {@see CycleGuard} gives the store: state that lives for the
 * duration of a guarded call, visible to every nested resolution).
 *
 * A scope opened while another is resolving nests honestly: the inner
 * scope's resolutions land in the inner scope, and the outer scope's
 * own resolution resumes with the outer scope open again.
 */
final class ActiveScope
{
    private ?RequestScope $requestScope = null;

    /**
     * Resolves with the scope open: the given resolution -- and every
     * nested resolution it performs through the container's root --
     * sees the scope as the open one.
     *
     * @param Closure(): mixed $resolution
     */
    public function within(RequestScope $requestScope, Closure $resolution): mixed
    {
        $previousScope = $this->requestScope;
        $this->requestScope = $requestScope;

        try {
            return $resolution();
        } finally {
            $this->requestScope = $previousScope;
        }
    }

    /**
     * The open scope, or null when resolution runs unscoped (the
     * request lane's outside-scope refusal).
     */
    public function current(): ?RequestScope
    {
        return $this->requestScope;
    }
}
