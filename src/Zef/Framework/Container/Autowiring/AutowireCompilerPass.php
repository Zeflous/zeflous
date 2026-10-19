<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\UnplannedInstanceDefinition;

/**
 * The autowiring compile pass -- the constructor-planning lane the
 * declarative grammar's `class` shape grows into (the github v2.9
 * compile-pass model this family ports: reflection ONLY at boot, ONLY
 * through the collaborators below).
 *
 * The pass runs between the bootstrapper's parse and its graph
 * validation, over the combined declared set, and answers a rewritten
 * set of plain positional {@see InstanceDefinition}s -- the exact form
 * the graph already validates and the compiler already installs, so the
 * lane adds zero new runtime machinery:
 *
 * - an **unplanned** definition (the name-keyed or `autowire` forms) is
 *   planned parameter by parameter through
 *   {@see AutowireParameterPlanner}, the resulting positional list being
 *   the ordinary argument grammar (plain values and `['service' => id]`
 *   references) -- the edges the plan declares flow into the graph like
 *   any hand-written reference, and the two-time cycle discipline is
 *   untouched: the boot graph still refuses a declared cycle before a
 *   single service resolves, the runtime {@see \Zef\Framework\Container\CycleGuard}
 *   still refuses a factory that re-enters;
 * - a **positional** definition is read once for honesty ({@see PositionalFit}):
 *   the class must be instantiable and the declared list must fit the
 *   constructor's arity -- the plan itself stays verbatim, explicit wins
 *   over auto;
 * - a **birth** -- an unregistered, instantiable class a planned
 *   parameter's type names -- becomes its own declared definition under
 *   its own name ({@see AutowireReferences}): planned recursively,
 *   visible in the answered set and in the report, its edges riding the
 *   same graph. The container itself never births anything at runtime
 *   (the controlled reading of the roadmap's aggressive-vs-controlled
 *   bullet: undeclared identifiers never resolve, an unregistered class
 *   only ever lives because a *declared* parameter asked for it, at
 *   boot, on the record);
 * - a **materialised value** -- a planned parameter's `#[Value]`
 *   address ({@see AutowireValueServices}, the registration-completion
 *   family's lane) -- becomes the synthetic `@value:` value service the
 *   plan references, visible in the answered set and in the report's
 *   values column. The parameters themselves arrive as plain data
 *   ({@see ParameterValues}, threaded by the kernel-layer bootstrapper
 *   through {@see self::process()}), keeping this layer free of the
 *   configuration seam the deptrac build order guards.
 *
 * One run per {@see process()} call: the run's accumulators reset with
 * the {@see AutowireReferences::open()} and
 * {@see AutowireValueServices::open()} calls, so a pass instance may
 * serve successive boots without one boot's births or materialised
 * values leaking into the next.
 */
final readonly class AutowireCompilerPass
{
    private AutowireReferences $autowireReferences;

    private AutowireBirths $autowireBirths;

    private AutowireRequestPlanning $autowireRequestPlanning;

    private AutowireValueServices $autowireValueServices;

    public function __construct()
    {
        $this->autowireBirths = new AutowireBirths();
        $this->autowireValueServices = new AutowireValueServices();
        $this->autowireReferences = new AutowireReferences(
            new ReflectionMetadataExtractor(),
            $this->autowireBirths,
            $this->autowireValueServices,
        );
        $this->autowireRequestPlanning = new AutowireRequestPlanning($this->autowireReferences);
    }

    /**
     * Plans the declared set: every class-shaped definition rewritten to
     * the positional form, every birth appended, every `#[Value]` address
     * materialised, the report answering what the run generated, reused
     * and materialised.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function process(
        array $definitions,
        Container $container,
        ParameterValues $parameterValues = new ParameterValues(),
    ): AutowireResult {
        $this->autowireValueServices->open($parameterValues, $definitions, $container);
        $this->autowireReferences->open($definitions, $container);

        $planned = array_map(
            $this->plannedDefinition(...),
            $definitions,
        );

        return AutowireResult::of($planned, $this->autowireBirths, $this->autowireValueServices);
    }

    /**
     * One declared definition's planned form: the positional shapes
     * validated against the constructor (instantiable, arity), the
     * unplanned shapes planned into the positional grammar.
     */
    private function plannedDefinition(ServiceDefinition $serviceDefinition): ServiceDefinition
    {
        if ($serviceDefinition instanceof UnplannedInstanceDefinition) {
            return $this->autowireRequestPlanning->plan($serviceDefinition);
        }

        if ($serviceDefinition instanceof InstanceDefinition) {
            return AutowirePositionalCheck::checked($serviceDefinition);
        }

        return $serviceDefinition;
    }
}
