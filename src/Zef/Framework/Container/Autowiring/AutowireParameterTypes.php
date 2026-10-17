<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionIntersectionType;
use ReflectionNamedType;
use ReflectionParameter;
use ReflectionUnionType;

/**
 * The reflection type reader's classification half: the type name a
 * parameter declares, and the honest verdict for a type the lane cannot
 * plan (see {@see ReflectionMetadataExtractor} for the read itself).
 *
 * Split from the extractor so the type grammar -- named versus builtin,
 * union versus intersection -- keeps its own archeology-sized unit: a
 * class name answers the planner's binding arms, null answers the
 * scalar arms, and the unsupported reasons answer the refusals that
 * quote them verbatim.
 */
final readonly class AutowireParameterTypes
{
    /**
     * The class/interface/enum type name the parameter declares, or null
     * for builtins and untyped parameters (the planner's scalar arms).
     */
    public static function classNameOf(ReflectionParameter $reflectionParameter): ?string
    {
        $type = $reflectionParameter->getType();
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return $type->getName();
    }

    /**
     * The honest verdict for a type the lane cannot plan: unions and
     * intersections admit several types and thereby name no single
     * binding (the refusal message quotes the reason verbatim).
     */
    public static function unsupportedTypeReason(ReflectionParameter $reflectionParameter): ?string
    {
        $type = $reflectionParameter->getType();

        if ($type instanceof ReflectionUnionType) {
            return 'a union type names no single binding';
        }

        if ($type instanceof ReflectionIntersectionType) {
            return 'an intersection type names no single binding';
        }

        return null;
    }
}
