<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The contextual-binding knob's parsed form: the `contextual` configuration
 * entry as validated bindings (ROADMAP, "CONTAINER" area 4 -- the fluent
 * `when()->needs()->give()` idea of the reference container, adapted to
 * the declarative grammar).
 *
 * The knob's grammar is a map of consumers to dependency overrides:
 *
 *   ['billing' => ['PaymentGateway' => 'gateway.stripe']]
 *
 * -- one consumer key, one map of dependency identifiers to the target
 * each binding gives instead, validated by {@see ContextualConsumers}.
 *
 * The empty knob -- absent, null, or an empty map -- is the valid
 * no-bindings boot: the framework declares the empty map itself
 * ({@see FrameworkDefaults}), a host's bindings arrive with the host's
 * configuration.
 */
final readonly class ContextualMap
{
    /**
     * @param array<string, array<string, string>> $bindings consumer => dep => target
     */
    private function __construct(
        private array $bindings,
    ) {
    }

    /**
     * The validated bindings: the map the boot apply walks.
     *
     * @return array<string, array<string, string>>
     */
    public function bindings(): array
    {
        return $this->bindings;
    }

    /**
     * Parses the `contextual` configuration entry into its validated
     * bindings.
     *
     * @param mixed $source the raw `contextual` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self([]);
        }

        if (!\is_array($source)) {
            throw ContextualRefusals::notAMap($key, $source);
        }

        return new self(ContextualConsumers::bindings($key, $source));
    }
}
