<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ServiceDefinition;

/**
 * The autoconfiguration apply: the boot step that merges the tags a
 * class-shaped declaration earns -- from the marker interfaces its
 * class implements (the {@see AutoconfigMap} rules) and from the
 * `#[Tag]` attributes its class carries -- into the declaration's own
 * explicit tags (ROADMAP, "CONTAINER" area 3).
 *
 * Runs over the compile pass's planned output, before the graph and
 * the install: the tags a declaration ends up carrying are the tags
 * the boot-built {@see \Zef\Framework\Container\ServiceTagIndex} reads,
 * so the automatic and the explicit spellings are indistinguishable
 * downstream -- one tag list, however it was earned.
 *
 * The coverage is honest about what it reaches: only the class shape
 * names its class, so only the class shape can earn automatic tags (a
 * factory shape's product type is not spelled by the declaration; an
 * alias and a value name no class at all). A class the declared set
 * never names carries its `#[Tag]` attributes unheard -- this lane
 * discovers nothing, the declaration is the source of what boots.
 *
 * The verdicts belong to the collaborators: {@see AutoconfigClaims}
 * gathers what each source would hand the declaration,
 * {@see AutoconfigEarned} refuses the collisions and answers the
 * earned list, and this class only threads one declaration through
 * them and merges the answer in.
 */
final readonly class Autoconfiguration
{
    /**
     * Applies the rules to the planned set: every class-shaped
     * definition answered with its earned tags merged in, every other
     * shape passed through untouched.
     *
     * @param list<ServiceDefinition> $definitions the compile pass's planned output
     *
     * @return list<ServiceDefinition>
     */
    public static function apply(array $definitions, AutoconfigMap $autoconfigMap): array
    {
        $configured = [];
        foreach ($definitions as $definition) {
            $configured[] = self::configured($definition, $autoconfigMap);
        }

        return $configured;
    }

    private static function configured(
        ServiceDefinition $serviceDefinition,
        AutoconfigMap $autoconfigMap,
    ): ServiceDefinition {
        if (!$serviceDefinition instanceof InstanceDefinition) {
            return $serviceDefinition;
        }

        $autoTags = AutoconfigEarned::tags(
            $serviceDefinition,
            AutoconfigClaims::on($serviceDefinition, $autoconfigMap),
        );
        if ($autoTags === []) {
            return $serviceDefinition;
        }

        return $serviceDefinition->withAddedTags($autoTags);
    }
}
