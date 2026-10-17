<?php

declare(strict_types=1);

namespace Zef\Benchmark;

use PhpBench\Attributes\Revs;
use stdClass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\RequestScope;
use Zef\Framework\Container\ServiceLifetime;

/**
 * The scope-and-lifecycle lane's economics: the three sharing policies
 * side by side. The memoised lanes -- a singleton at the container's
 * root, a request-scoped id inside its scope -- should cost the same
 * order (a map lookup each, the scope's just behind one WeakMap
 * offset); the transient lane pays the full factory call every
 * resolution, which is the honest price of "fresh every time" (see
 * `ROADMAP.md`, "CONTAINER" area 5 -- the lifetime taxonomy the gitlab
 * reference ports, an enum in this repo's own idiom).
 */
final readonly class ScopeResolutionBench
{
    private Container $container;

    private RequestScope $requestScope;

    public function __construct()
    {
        $this->container = new Container();
        $this->container->set('app.shared', static fn (): stdClass => new stdClass());
        $this->container->set('app.scoped', static fn (): stdClass => new stdClass(), ServiceLifetime::REQUEST);
        $this->container->set('app.fresh', static fn (): stdClass => new stdClass(), ServiceLifetime::TRANSIENT);

        $this->container->get('app.shared');

        $this->requestScope = $this->container->createRequestScope();
        $this->requestScope->get('app.scoped');
    }

    #[Revs(10000)]
    public function benchASingletonMemoHit(): void
    {
        $this->container->get('app.shared');
    }

    #[Revs(10000)]
    public function benchARequestScopedMemoHitInsideTheScope(): void
    {
        $this->requestScope->get('app.scoped');
    }

    #[Revs(2000)]
    public function benchATransientPaysTheFactoryEveryTime(): void
    {
        $this->container->get('app.fresh');
    }

    #[Revs(2000)]
    public function benchAScopeOpensAndClosesPerRequest(): void
    {
        $requestScope = $this->container->createRequestScope();
        $requestScope->get('app.scoped');
        $requestScope->close();
    }
}
