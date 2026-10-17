<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The request-scope machinery behind {@see ServiceStore}'s request lane
 * (see {@see ServiceLifetime} for the lane itself).
 *
 * This class owns what only the scope world knows: the open scope as
 * resolution state ({@see ActiveScope}, saved and restored around the
 * scoped call so a factory resolving a fellow request-scoped
 * dependency lands it in the same scope) and the request lane's
 * verdicts -- the honest refusal when no scope is open ({@see
 * RequestScopeRefusals}), the equally honest one when the open scope
 * closed mid-resolution, and the memo the completed instance writes
 * through the scope's own seam ({@see RequestScope::answer()}). The
 * scopes themselves are the container's own construction
 * ({@see Container::createRequestScope()} wires each one to this
 * machinery).
 */
final readonly class RequestInstances
{
    private ActiveScope $activeScope;

    public function __construct(
        private ServiceStore $serviceStore,
    ) {
        $this->activeScope = new ActiveScope();
    }

    /**
     * Resolves with the scope open: request-scoped ids the resolution
     * reaches -- the requested one and any fellow request-scoped
     * dependency a factory resolves along the way -- memoise in it.
     */
    public function resolveScoped(string $id, Container $container, RequestScope $requestScope): mixed
    {
        return $this->activeScope->within(
            $requestScope,
            fn (): mixed => $this->serviceStore->resolve($id, $container),
        );
    }

    /**
     * The request lane: the open scope's own memo, the guarded factory
     * call and the memo write all live behind the scope's own answer
     * ({@see RequestScope::answer()}) -- this lane owns only the two
     * verdicts: the way-out refusal when no scope is open at all, and
     * the early one when the open scope closed mid-resolution (before
     * a single factory runs).
     */
    public function instance(string $id, Container $container): mixed
    {
        $scope = $this->activeScope->current();
        if (!$scope instanceof RequestScope) {
            throw RequestScopeRefusals::outsideScope($id);
        }

        if ($scope->isClosed()) {
            throw RequestScopeRefusals::closedMidResolution($id);
        }

        return $scope->answer($id, fn (): mixed => $this->serviceStore->guarded($id, $container));
    }
}
