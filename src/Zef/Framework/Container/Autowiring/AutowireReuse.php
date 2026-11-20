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
 * report's `reused` column. The recording answers the RESOLUTION arm
 * alone ({@see AutowireReferences::resolve()}): the consultative
 * probes read the same verdict through the pure {@see self::peek()}
 * spelling and write nothing, so a defaulted parameter whose binding
 * never yields counts no phantom reuse (issue #800's finding 3). A
 * container-registered service answers through the definition edges'
 * leaf semantics: the host application's own behaviour, validated no
 * further and exactly as without this lane.
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
        if (!self::answered($id)) {
            return false;
        }

        $this->autowireBirths->recordReuse($id);

        return true;
    }

    /**
     * The pure verdict of {@see self::known()}: the same
     * declared/birthed/carried arms read for the answer alone, no
     * bookkeeping written. This is the consultative probe's own
     * spelling (issue #800's finding 3): the first spelling recorded a
     * reuse on every consultation -- so a defaulted parameter whose
     * binding never resolves counted a reuse that never happened, and
     * the report's `reused` column overcounted the references the run
     * actually planned.
     */
    public function peek(string $id): bool
    {
        return self::answered($id);
    }

    /**
     * Whether an identifier would resolve at all: the reuse verdict or
     * the birth verdict -- the consultative probe the defaulted
     * nullable's yield rule reads ({@see AutowireBindingTiers}), so an
     * unbound type name must not veto the constructor's own default.
     * The consultation is pure: it reads the same verdict through the
     * probe's own spelling ({@see self::peek()}) and records nothing.
     */
    public function resolvable(string $id): bool
    {
        return $this->peek($id) || ConstructorSpecs::birthable($id);
    }

    /**
     * The three arms every verdict reads: a declared definition, a
     * class this run already birthed, or a service the container itself
     * carries.
     */
    private function answered(string $id): bool
    {
        return isset($this->declaredById[$id])
            || $this->autowireBirths->has($id)
            || $this->container?->has($id) === true;
    }
}
