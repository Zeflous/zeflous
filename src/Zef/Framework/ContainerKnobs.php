<?php

declare(strict_types=1);

namespace Zef\Framework;

use Zef\Framework\Config\Config;
use Zef\Framework\Container\Aot\AotDigest;
use Zef\Framework\Container\Aot\AotMap;
use Zef\Framework\Container\Autowiring\AutoconfigMap;
use Zef\Framework\Container\ContextualMap;
use Zef\Framework\Container\DecoratorMap;
use Zef\Framework\Container\DefinitionMap;
use Zef\Framework\Container\InterceptorsMap;
use Zef\Framework\Container\ListenersMap;
use Zef\Framework\Container\ParameterMap;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The boot's parsed knob set: the eight configuration knobs the
 * container boot reads, parsed and validated in one place so the
 * bootstrapper's lanes hand one object around instead of eight --
 * the definition maps (the framework's own registrations first, the
 * host's declarations second, the collision order the install loop
 * depends on), the parameters, the autoconfiguration rules, the
 * contextual bindings, the decoration chains, the interception
 * entries, and the AOT lane's own switch -- together with the
 * fingerprint of the source they were parsed from.
 *
 * The fingerprint is *lazy* by design ({@see fingerprint()}): the full
 * lane runs for every boot, but the digest the AOT lane needs folds
 * only when a lane actually asks -- a configuration that carries live
 * objects (a legitimate full-lane declaration) never pays the
 * data-only demand the fingerprint's grammar makes unless the AOT knob
 * is on.
 */
final readonly class ContainerKnobs
{
    /**
     * @param list<ServiceDefinition> $definitions the two definition maps, parsed and concatenated
     */
    public function __construct(
        private Config $config,
        public array $definitions,
        public ParameterMap $parameters,
        public AutoconfigMap $autoconfig,
        public ContextualMap $contextual,
        public DecoratorMap $decorators,
        public InterceptorsMap $interceptors,
        public ListenersMap $listeners,
        public AotMap $aot,
    ) {
    }

    /**
     * Parses the eight knobs straight out of the frozen repository.
     */
    public static function parsed(Config $config): self
    {
        return new self(
            $config,
            [
                ...DefinitionMap::parse('framework.services', $config->get('framework.services')),
                ...DefinitionMap::parse('services', $config->get('services')),
            ],
            ParameterMap::parse('parameters', $config->get('parameters')),
            AutoconfigMap::parse('tags', $config->get('tags')),
            ContextualMap::parse('contextual', $config->get('contextual')),
            DecoratorMap::parse('decorators', $config->get('decorators')),
            InterceptorsMap::parse('interceptors', $config->get('interceptors')),
            ListenersMap::parse('listeners', $config->get('listeners')),
            AotMap::parse('aot', $config->get('aot')),
        );
    }

    /**
     * The source configuration's fingerprint: the number that decides
     * whether an artifact still describes the configuration it was
     * dumped from -- eight source maps and the framework version, folded
     * only on the lane that asked.
     *
     * @return non-empty-string the SHA-256 fingerprint
     */
    public function fingerprint(): string
    {
        return AotDigest::over(
            [
                'framework.services' => $this->config->get('framework.services'),
                'services' => $this->config->get('services'),
                'parameters' => $this->config->get('parameters'),
                'tags' => $this->config->get('tags'),
                'contextual' => $this->config->get('contextual'),
                'decorators' => $this->config->get('decorators'),
                'interceptors' => $this->config->get('interceptors'),
                'listeners' => $this->config->get('listeners'),
            ],
            Version::current(),
        );
    }
}
