<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\DefinitionCompiler;
use Zef\Framework\Container\DefinitionException;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ServiceDefinitionGraph;

/**
 * Bootstraps the container from the frozen configuration repository.
 *
 * The kernel owns both the container and the configuration (`Kernel`), so
 * the composition glue lives here: this class reads the `services` key of
 * the repository the configuration layer already loaded, merged, validated
 * and cached, and installs every declarative definition into the container
 * through its existing public surface ({@see Container::set()}).
 *
 * The container itself never depends on the configuration layer (the deptrac
 * build order); it only knows the plain definition grammar, which is why
 * this bootstrapper -- not a container method -- is the config-driven seam.
 *
 * Fail-closed at boot, in order: the `services` value must be a definition
 * map (a missing/null key is a valid empty service set); the keys must be
 * non-empty service identifiers; each entry is parsed by
 * {@see ServiceDefinition::fromArray()}; the whole set's reference graph is
 * proven resolvable and acyclic by {@see ServiceDefinitionGraph::validate()}
 * before a single definition is installed.
 */
final readonly class ContainerBootstrapper
{
    public function __construct(
        private DefinitionCompiler $definitionCompiler = new DefinitionCompiler(),
    ) {
    }

    /**
     * Installs the configuration's declarative definitions into the given
     * container and returns it.
     */
    public function bootstrap(Config $config, Container $container): Container
    {
        $services = $config->get('services');

        if ($services === null) {
            return $container;
        }

        if (!\is_array($services)) {
            throw new DefinitionException(
                "The 'services' configuration must be a map of definitions, " . get_debug_type($services) . ' given.',
            );
        }

        $definitions = [];
        foreach (array_keys($services) as $key) {
            if (!\is_string($key) || $key === '') {
                throw new DefinitionException(
                    "The 'services' configuration keys must be non-empty service identifiers, "
                    . get_debug_type($key) . ' given.',
                );
            }

            $definitions[] = ServiceDefinition::fromArray($key, $services[$key]);
        }

        ServiceDefinitionGraph::validate($definitions, $container);

        foreach ($definitions as $definition) {
            $container->set($definition->id, $this->definitionCompiler->compile($definition));
        }

        return $container;
    }
}
