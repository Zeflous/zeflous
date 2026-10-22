<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The compile run's reuse query: whether an identifier a plan may
 * reference already answers -- a declared definition, a class this run
 * already birthed, or a service the container itself carries (see
 * {@see AutowireReferences} for the arms that ask).
 *
 * Reuse never re-plans: the first spelling wins, and every reuse is
 * recorded on the run's {@see AutowireBirths} bookkeeping -- the
 * report's `reused` column. A container-registered service answers
 * through the definition edges' leaf semantics: the host application's
 * own behaviour, validated no further and exactly as without this lane.
 */
final class AutowireReuse
{
    /** @var array<string, ServiceDefinition> every declared definition, by identifier */
    private array $declaredById = [];

    private ?Container $container = null;

    public function __construct(
        private readonly AutowireBirths $autowireBirths,
    ) {
    }

    /**
     * Opens the run: the declared set to resolve against, the container
     * whose own services answer as reuse, the bookkeeping reset.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function open(array $definitions, Container $container): void
    {
        $this->declaredById = [];
        $this->autowireBirths->reset();
        $this->container = $container;

        foreach ($definitions as $definition) {
            $this->declaredById[$definition->id] = $definition;
        }
    }

    /**
     * Whether the identifier already answers (recording the reuse when
     * it does).
     */
    public function known(string $id): bool
    {
        $answered = isset($this->declaredById[$id])
            || $this->autowireBirths->has($id)
            || $this->container?->has($id) === true;
        if ($answered) {
            $this->autowireBirths->recordReuse($id);
        }

        return $answered;
    }

    /**
     * Whether an identifier would resolve at all: the reuse verdict or
     * the birth verdict -- the consultative probe the defaulted
     * nullable's yield rule reads ({@see AutowireBindingTiers}), so an
     * unbound type name must not veto the constructor's own default.
     */
    public function resolvable(string $id): bool
    {
        return $this->known($id) || ConstructorSpecs::birthable($id);
    }
}
