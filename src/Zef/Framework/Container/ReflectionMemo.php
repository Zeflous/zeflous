<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use ReflectionClass;
use ReflectionException;

/**
 * The resolution lane's reflection memo (ROADMAP, "CONTAINER" area 11
 * -- "Reflection caching: hasil refleksi disimpan agar introspeksi
 * tidak mahal"): one {@see ReflectionClass} per class name, held for
 * the lifetime of the unit that owns the memo, so a resolution path
 * that reflects the same class again pays a map lookup instead of a
 * fresh reflection construction.
 *
 * The two lanes that pay reflection *per resolution* -- not per boot
 * -- are the ones threaded with a memo: the definition compiler's
 * class-shape closure ({@see DefinitionCompiler}, whose
 * `newInstanceArgs` construction ran a fresh reflection for every
 * resolve, so a transient service reflected on every `get()`), and
 * the lazy lane's proxy construction ({@see LazyDeferrals}, whose
 * native `newLazyProxy` rode a fresh reflection for every deferral
 * answer). Every other reflection in the tree is a boot-time
 * once-per-boot read each unit owns for itself ({@see Autowiring\ReflectionMetadataExtractor}
 * the discipline's own docblock spells) -- those stay where they are,
 * paid exactly once per boot by design, never per resolution.
 *
 * The memo is deliberately *not* shared between the two lanes: the
 * container's public constructor stays parameter-free (the
 * minimal-surface rule the facade keeps), so the compiler's memo and
 * the resolution lane's memo are two instances, and a class that is
 * both eagerly compiled and lazily proxied reflects twice per boot --
 * twice, not twice per resolution: the bound the trade accepts and
 * the KDD records. The AOT artifact lane needs no memo at all: its
 * baked closures inline the literal `new \Class(...)` construction,
 * the zero-reflection promise the area's own docblock makes.
 *
 * The census ({@see count()}) answers how many distinct classes the
 * memo carries -- the observability the resolution tests pin the
 * reuse with: two resolutions, one carried class.
 */
final class ReflectionMemo
{
    /** @var array<string, ReflectionClass<object>> the classes already reflected */
    private array $reflected = [];

    /**
     * Answers the class's reflection, memoised on the first ask: the
     * same instance every later ask returns, so a per-resolution path
     * reflecting the same class repeatedly pays the map lookup alone.
     *
     * A name no class answers is refused at the boundary, reflection's
     * own refusal carried verbatim ({@see ReflectionException}) -- the
     * memo stores answers, it never invents them, and the guard is what
     * proves the carried key's class claim for the type system (the
     * narrowing {@see class_exists()} answers).
     *
     * @param string $class the class name to reflect
     *
     * @return ReflectionClass<object>
     */
    public function reflect(string $class): ReflectionClass
    {
        if (!class_exists($class)) {
            throw new ReflectionException("The class '{$class}' does not exist");
        }

        return $this->reflected[$class] ??= new ReflectionClass($class);
    }

    /**
     * How many distinct classes the memo carries -- the census the
     * resolution tests pin the reuse with (two resolutions of one
     * class answer one, not two).
     */
    public function count(): int
    {
        return \count($this->reflected);
    }
}
