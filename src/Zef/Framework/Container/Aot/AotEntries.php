<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\AliasDefinition;
use Zef\Framework\Container\FactoryDefinition;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\MethodCall;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Framework\Container\ValueDefinition;

/**
 * The artifact's entry rendering: the planned definition set, one entry
 * per definition, in the two kinds the boundary carries.
 *
 * A `closure` entry holds the rendered closure code
 * ({@see AotClosures}) -- instance, alias and value shapes, the
 * zero-reflection lane. A `build` entry holds the factory shape as data
 * -- the declaration the loader reconstructs once through
 * {@see \Zef\Framework\Container\FactoryClosures}, keeping that family's
 * conditional resolution lane and its failure messages
 * single-sourced rather than duplicated into generated code.
 *
 * The order is the pipeline's own: entries land in the planned set's
 * declaration order, so an artifact's install loop registers in the same
 * order the full lane's install loop does -- the same identifier
 * re-declared twice keeps the same last-wins answer on both lanes.
 *
 * An unplanned definition reaching this rendering is the same misuse the
 * compiler refuses: the autowire pass plans the class shapes, so the
 * refusal names the run the definition never went through.
 *
 * The dump-side shapes the artifact boundary carries, aliased for the
 * spellings that would otherwise exceed a line:
 *
 * @phpstan-type ArtifactCall array{method: string, arguments: list<mixed>}
 *
 * @psalm-type ArtifactCall array{method: string, arguments: list<mixed>}
 *
 * @phpstan-type ArtifactClosureEntry array{kind: 'closure', closure: string, lifetime: string}
 *
 * @psalm-type ArtifactClosureEntry array{kind: 'closure', closure: string, lifetime: string}
 *
 * @phpstan-type ArtifactBuildEntry array{kind: 'build', factory: string, arguments: list<mixed>,
 *     calls: list<ArtifactCall>, lifetime: string}
 *
 * @psalm-type ArtifactBuildEntry array{kind: 'build', factory: string, arguments: list<mixed>,
 *     calls: list<ArtifactCall>, lifetime: string}
 *
 * @phpstan-type ArtifactDumpEntry ArtifactClosureEntry|ArtifactBuildEntry
 *
 * @psalm-type ArtifactDumpEntry ArtifactClosureEntry|ArtifactBuildEntry
 */
final readonly class AotEntries
{
    /**
     * Renders the planned definition set into its artifact entries.
     *
     * @param list<ServiceDefinition> $definitions the pipeline's planned set
     *
     * @return array<string, ArtifactDumpEntry> the entries, keyed by identifier
     */
    public static function all(array $definitions): array
    {
        $entries = [];

        foreach ($definitions as $definition) {
            $entries[$definition->id] = self::one($definition);
        }

        return $entries;
    }

    /**
     * @return ArtifactDumpEntry one definition's artifact entry
     */
    private static function one(ServiceDefinition $serviceDefinition): array
    {
        return match (true) {
            $serviceDefinition instanceof InstanceDefinition => [
                'kind' => 'closure',
                'closure' => AotClosures::instance($serviceDefinition),
                'lifetime' => $serviceDefinition->lifetime->value,
            ],
            $serviceDefinition instanceof AliasDefinition => [
                'kind' => 'closure',
                'closure' => AotClosures::alias($serviceDefinition),
                'lifetime' => $serviceDefinition->lifetime->value,
            ],
            $serviceDefinition instanceof ValueDefinition => [
                'kind' => 'closure',
                'closure' => AotClosures::value($serviceDefinition),
                'lifetime' => $serviceDefinition->lifetime->value,
            ],
            $serviceDefinition instanceof FactoryDefinition => self::build($serviceDefinition),
            default => throw new AotException(
                "The service '" . $serviceDefinition->id . "' reaches the AOT dump unplanned -- "
                . 'run the autowire compiler pass before the dump.',
            ),
        };
    }

    /**
     * The factory shape's data entry: the declaration as the data the
     * loader reconstructs, its argument lists verified exportable here
     * so the dump never writes a value the boundary cannot carry.
     *
     * @return array{kind: 'build', factory: string, arguments: list<mixed>,
     *     calls: list<array{method: string, arguments: list<mixed>}>, lifetime: string}
     */
    private static function build(FactoryDefinition $factoryDefinition): array
    {
        AotLiterals::assertExportable($factoryDefinition->arguments, $factoryDefinition->id, 'arguments');

        foreach ($factoryDefinition->calls as $call) {
            AotLiterals::assertExportable($call->arguments, $factoryDefinition->id, 'calls.' . $call->method);
        }

        return [
            'kind' => 'build',
            'factory' => $factoryDefinition->factory,
            'arguments' => $factoryDefinition->arguments,
            'calls' => array_map(
                static fn (MethodCall $methodCall): array => [
                    'method' => $methodCall->method,
                    'arguments' => $methodCall->arguments,
                ],
                $factoryDefinition->calls,
            ),
            'lifetime' => $factoryDefinition->lifetime->value,
        ];
    }
}
