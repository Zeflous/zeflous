<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\Container;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The synthetic value namespace's occupancy guard: which `@value:`
 * identifiers the declared set and the container already spell, so the
 * value lane can refuse a materialisation that would collide with
 * them ({@see AutowireValueServices} for the lane itself).
 *
 * The prefix is the machinery's own -- a declared definition or a
 * container service already carrying such an identifier is a foreign
 * spelling of the namespace, and the lane refuses it with both
 * spellings named rather than silently shadowing the attribute's
 * intent (the synthetic-namespace discipline the contextual-binding
 * lane follows). Non-prefixed identifiers guard nothing: the scan
 * lifts only the lane's own prefix out of the declared set, and the
 * container is consulted per identifier at use, never enumerated.
 *
 * The container is nullable for the unopened default: a guard that
 * has not been opened ({@see self::fromDefinitions()}) answers
 * nothing occupied -- the lane's own misuse surface is the
 * missing-address refusal that follows, never a phantom container
 * allocation the default construction would have to pay.
 */
final readonly class SyntheticValueGuard
{
    /** The synthetic service identifier prefix this lane owns. */
    public const string VALUE_SERVICE_PREFIX = '@value:';

    /**
     * @param array<string, true> $occupied  the foreign `@value:` spellings, by identifier
     * @param null|Container      $container the run's container, when opened
     */
    public function __construct(
        private array $occupied = [],
        private ?Container $container = null,
    ) {
    }

    /**
     * Lifts the declared set's `@value:` spellings.
     *
     * @param list<ServiceDefinition> $definitions
     */
    public static function fromDefinitions(array $definitions, Container $container): self
    {
        $occupied = [];

        foreach ($definitions as $definition) {
            if (!str_starts_with($definition->id, self::VALUE_SERVICE_PREFIX)) {
                continue;
            }

            $occupied[$definition->id] = true;
        }

        return new self($occupied, $container);
    }

    /** Whether a declared definition or a container service already spells the identifier. */
    public function occupied(string $id): bool
    {
        return isset($this->occupied[$id]) || $this->container?->has($id) === true;
    }
}
