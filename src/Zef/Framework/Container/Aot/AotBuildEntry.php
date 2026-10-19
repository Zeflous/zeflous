<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\ServiceLifetime;

/**
 * The load side of a build-kind artifact entry: the factory-shape
 * declaration as *data* -- the reconstruction input the install half
 * ({@see AotDefinitions}) rebuilds through the same
 * {@see \Zef\Framework\Container\FactoryClosures} builder the full lane
 * dispatches to, keeping that family's conditional resolution lane and
 * its failure messages single-sourced instead of duplicated into
 * generated code.
 *
 * The value object is its own grammar ({@see validated()}): the entry's
 * members checked into the typed form the moment it is read, its method
 * calls already the value objects the builder consumes -- the identifier
 * is the entry's key in the artifact's map, one spelling, one source.
 */
final readonly class AotBuildEntry
{
    /**
     * The members a build-kind entry carries, exactly -- the shape guard's
     * own spelling, shared with the docblock's promise.
     */
    private const array MEMBERS = ['kind', 'factory', 'arguments', 'calls', 'lifetime'];

    /**
     * @param string           $factory   the `Target::method` spelling, non-empty as validated
     * @param list<mixed>      $arguments the factory's argument list, exportable as validated
     * @param list<MethodCall> $calls     the method-call injection, in declaration order
     */
    public function __construct(
        public string $factory,
        public array $arguments,
        public array $calls,
        public ServiceLifetime $lifetime,
    ) {
    }

    /**
     * Validates one build-kind entry into its typed form: the members
     * exactly, the factory and arguments read through the members' own
     * narrow door, the calls delegated to their own walk, the lifetime
     * already converted by the dispatch that chose this kind.
     *
     * @param array<array-key, mixed> $entry the payload's entry
     */
    public static function validated(string $id, array $entry, ServiceLifetime $serviceLifetime): self
    {
        if (array_keys($entry) !== self::MEMBERS) {
            throw AotRefusals::entryMalformed($id);
        }

        return new self(
            AotMembers::string($id, $entry, 'factory'),
            AotMembers::list($id, $entry, 'arguments'),
            AotArtifactCalls::validated($id, $entry['calls']),
            $serviceLifetime,
        );
    }
}
