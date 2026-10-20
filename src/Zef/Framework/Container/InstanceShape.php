<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `class` shape's parser (one of the four definition shapes; see
 * {@see DefinitionShapes} for the dispatch).
 *
 * The shape carries a class name and its arguments in one of three
 * forms -- and the form IS the planning decision, dispatched by
 * {@see InstanceArguments}: positional (the hand-written plan), the
 * name-keyed hybrid, or the whole-constructor `autowire` request.
 *
 * The class must be a non-empty string naming an existing class: a
 * definition that survives boot can only fail at instantiation through
 * its arguments' references (which the {@see ServiceDefinitionGraph}
 * has already proven resolvable) -- never through a missing class. The
 * `calls` methods are proven through the arguments-form dispatch too
 * ({@see InstanceArguments}): the class is known at parse time, so a
 * declared call that names a missing method is a boot refusal rather
 * than a first-resolution surprise.
 *
 * The `lazy` key asks the resolution lane to defer the construction
 * behind a native proxy of the declared class ({@see
 * LazyDeclarations::deferred()} for the request both shapes spell
 * identically, {@see LazyClasses} for the instantiability proof) --
 * a request both arguments forms carry to the planned definition
 * unchanged, refused for the one lifetime whose materialisation could
 * not name its scope ({@see LazyRefusals}). The class name itself is
 * the {@see ClassSpellings} unit's own question.
 */
final readonly class InstanceShape
{
    /**
     * @param array<array-key, mixed> $entry
     */
    public static function fromEntry(string $id, array $entry): ServiceDefinition
    {
        $class = ClassSpellings::validated($id, $entry['class']);
        $lifetime = ServiceLifetime::fromKey($id, $entry['lifetime'] ?? null);

        return InstanceArguments::definition(
            $id,
            $class,
            $entry,
            $lifetime,
            ServiceTagKey::fromKey($id, $entry['tags'] ?? null),
        )->withLazyClass(
            LazyDeclarations::deferred($id, $entry, $lifetime)
                ? LazyClasses::of($id, $class)
                : null,
        );
    }
}
