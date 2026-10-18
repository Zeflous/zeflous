<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ValueDefinition;

/**
 * The configuration-value materialisation lane -- the reference
 * container's `AutowireValueServices`, adapted to the declarative
 * grammar (one collaborator of the compile pass, opened per run).
 *
 * A planned parameter carrying `#[Value('db.host')]` routes here
 * ({@see AutowireParameterPlanner} threads the resolver, the value arm
 * answers): the address's value is looked up in the parsed parameters
 * map ({@see ParameterValues} -- plain data the kernel-layer
 * bootstrapper threaded in, so the container layer never learns the
 * configuration layer exists) and materialised as the synthetic
 * `@value:db.host` value service, memoised so every consumer of the
 * address shares one definition ({@see AutowireValueMaterialisations}).
 * The plan entry is the ordinary `['service' => id]` reference, so the
 * boot graph validates the materialised value exactly like any
 * hand-declared dependency -- unknown targets, cycles and captive
 * lifetimes ride the same proof -- and the runtime resolves it as the
 * plain value service it is, with zero new machinery on the hot path.
 *
 * The `@value:` prefix is the lane's own namespace
 * ({@see SyntheticValueGuard}): a foreign spelling of it is a
 * collision, refused with both spellings named, never a silent
 * shadowing of the attribute's intent. A missing address is the
 * honest refusal too -- the attribute is an explicit binding intent,
 * so the constructor's own default is not consulted while it is
 * present; the message names the way out instead.
 *
 * One instance serves one {@see AutowireCompilerPass::process()} run
 * and resets with it ({@see self::open()}), so a pass instance may
 * serve successive boots without one boot's materialisations leaking
 * into the next.
 */
final class AutowireValueServices
{
    private SyntheticValueGuard $syntheticValueGuard;

    private readonly AutowireValueMaterialisations $autowireValueMaterialisations;

    public function __construct(private ParameterValues $parameterValues = new ParameterValues())
    {
        $this->syntheticValueGuard = new SyntheticValueGuard();
        $this->autowireValueMaterialisations = new AutowireValueMaterialisations();
    }

    /**
     * Opens the run: the parameters to read, the declared set and the
     * container whose spellings guard the synthetic namespace, the
     * bookkeeping reset.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public function open(ParameterValues $parameterValues, array $definitions, Container $container): void
    {
        $this->parameterValues = $parameterValues;
        $this->syntheticValueGuard = SyntheticValueGuard::fromDefinitions($definitions, $container);
        $this->autowireValueMaterialisations->reset();
    }

    /**
     * The value arm's answer: the synthetic identifier the parameter's
     * plan references, materialising the value service on first use
     * (the whole first-use sequence lives with the record it
     * maintains, {@see AutowireValueMaterialisations::ensure()}).
     */
    public function service(string $ownerId, AutowireParameterSpec $autowireParameterSpec): string
    {
        $key = $autowireParameterSpec->valueKey;

        if ($key === null) {
            throw AutowireValueRefusals::missingAttribute($ownerId, $autowireParameterSpec->name);
        }

        return $this->autowireValueMaterialisations->ensure(
            $ownerId,
            $autowireParameterSpec->name,
            $key,
            $this->parameterValues,
            $this->syntheticValueGuard,
        );
    }

    /**
     * @return list<ValueDefinition> the materialised value services, in materialisation order
     */
    public function definitions(): array
    {
        return $this->autowireValueMaterialisations->definitions();
    }

    /**
     * @return list<string> the materialised synthetic identifiers, in materialisation order
     */
    public function materialized(): array
    {
        return $this->autowireValueMaterialisations->ids();
    }
}
