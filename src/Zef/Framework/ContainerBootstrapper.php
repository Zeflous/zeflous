<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Autowiring\AutoconfigMap;
use Zef\Framework\Container\Autowiring\Autoconfiguration;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\ContextualBindings;
use Zef\Framework\Container\ContextualMap;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\ParameterMap;
use Zef\Framework\Container\ServiceDefinitionGraph;
use Zef\Framework\Container\ServiceTagIndex;
use Zef\Framework\Container\TaggedServices;

/**
 * Bootstraps the container from the frozen configuration repository.
 *
 * The kernel owns both the container and the configuration (`Kernel`), so
 * the composition glue lives here: this class reads the repository's two
 * definition maps -- the framework's own registrations under the reserved
 * `framework.services` key (declared by {@see FrameworkDefaults}, the
 * Default layer the host cannot delete) and the host's declarations under
 * `services` (the `app/configs/services.php` surface, parsed by the same
 * {@see DefinitionMap} grammar) -- and installs every declarative
 * definition into the container through its existing public surface
 * ({@see Container::set()}).
 *
 * The two maps are deliberately separate: the merge contract counts an
 * empty array as a clearing list, so a host's empty `services.php` must
 * clear the host's own declaration surface without ever wiping the
 * framework's own machinery; and a same-identifier collision between the
 * maps is the host's explicit rebind of a framework registration, with
 * the host's definition winning (framework definitions install first).
 *
 * The container itself never depends on the configuration layer (the
 * deptrac build order); it only knows the plain definition grammar, which
 * is why this bootstrapper -- not a container method -- is the
 * config-driven seam.
 *
 * Fail-closed at boot, in order: each definition map is parsed and
 * validated by {@see DefinitionMap::parse()} (a missing/null key is a
 * valid empty set); the parameters knob is parsed to its validated
 * values ({@see ParameterMap::parse()} -- the global parameter
 * namespace the `#[Value]` attribute reads, its literal values, its
 * compile-phase providers, its reserved-machinery refusals); the
 * autoconfiguration knob is parsed to its
 * validated rules ({@see AutoconfigMap::parse()} -- an unknown key, a
 * missing interface or an invalid tag each halt the boot with the
 * knob's own name); the contextual-binding knob is parsed to its
 * validated bindings ({@see ContextualMap::parse()}); the whole set
 * then passes the autowiring compile pass
 * ({@see AutowireCompilerPass::process()}), which rewrites the
 * name-keyed and `autowire` declarations into the positional form,
 * reads every class-shaped constructor once for the instantiable and
 * arity verdicts, appends the classes a planned parameter birthed
 * as their own declared definitions and materialises the `#[Value]`
 * addresses as the synthetic value services the plan references; the
 * earned tags are merged in
 * ({@see Autoconfiguration::apply()} -- the marker rules and `#[Tag]`
 * attributes a class-shaped declaration earns, one tag one source or
 * the boot refuses); the contextual bindings are applied
 * ({@see ContextualBindings::apply()} -- each consumer's declared
 * dependency reference rewritten to flow through the binding's
 * synthetic alias); finally the set's reference graph
 * is proven resolvable and acyclic by
 * {@see ServiceDefinitionGraph::validate()} before a single definition
 * is installed.
 *
 * The tags lane's read side is registered before that install loop:
 * the reserved {@see FrameworkDefaults::TAGS_SERVICE} identifier
 * answers a {@see TaggedServices} locator over the boot-built
 * {@see ServiceTagIndex} of the planned set. Registered ahead of the
 * definitions for the same reason the profile's registrations install
 * first -- a host's same-identifier entry is an explicit rebind that
 * wins, so the framework's own machinery never overwrites the host's
 * deliberate choice.
 */
final readonly class ContainerBootstrapper
{
    public function __construct(
        private DefinitionCompiler $definitionCompiler = new DefinitionCompiler(),
        private AutowireCompilerPass $autowireCompilerPass = new AutowireCompilerPass(),
    ) {
    }

    /**
     * Installs the configuration's declarative definitions into the given
     * container and returns it.
     */
    public function bootstrap(Config $config, Container $container): Container
    {
        $definitions = [
            ...DefinitionMap::parse('framework.services', $config->get('framework.services')),
            ...DefinitionMap::parse('services', $config->get('services')),
        ];

        $parameterMap = ParameterMap::parse('parameters', $config->get('parameters'));

        $autoconfigMap = AutoconfigMap::parse('tags', $config->get('tags'));

        $contextualMap = ContextualMap::parse('contextual', $config->get('contextual'));

        $autowireResult = $this->autowireCompilerPass->process(
            $definitions,
            $container,
            $parameterMap->values(),
        );

        $planned = Autoconfiguration::apply($autowireResult->definitions, $autoconfigMap);

        $planned = ContextualBindings::apply($planned, $contextualMap);

        ServiceDefinitionGraph::validate($planned, $container);

        $container->set(
            FrameworkDefaults::TAGS_SERVICE,
            static fn (Container $container): TaggedServices => new TaggedServices(
                new ServiceTagIndex($planned),
                $container,
            ),
        );

        foreach ($planned as $definition) {
            $container->set($definition->id, $this->definitionCompiler->compile($definition), $definition->lifetime);
        }

        return $container;
    }
}
