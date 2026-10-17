<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Autowiring\AutowireCompilerPass;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\ServiceDefinitionGraph;

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
 * valid empty set); the whole set then passes the autowiring compile
 * pass ({@see AutowireCompilerPass::process()}), which rewrites the
 * name-keyed and `autowire` declarations into the positional form,
 * reads every class-shaped constructor once for the instantiable and
 * arity verdicts, and appends the classes a planned parameter birthed
 * as their own declared definitions; finally the set's reference graph
 * is proven resolvable and acyclic by
 * {@see ServiceDefinitionGraph::validate()} before a single definition
 * is installed.
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

        $autowireResult = $this->autowireCompilerPass->process($definitions, $container);

        ServiceDefinitionGraph::validate($autowireResult->definitions, $container);

        foreach ($autowireResult->definitions as $definition) {
            $container->set($definition->id, $this->definitionCompiler->compile($definition));
        }

        return $container;
    }
}
