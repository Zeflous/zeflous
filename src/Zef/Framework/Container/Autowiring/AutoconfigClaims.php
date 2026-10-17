<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

use ReflectionClass;
use Zef\Framework\Container\InstanceDefinition;

/**
 * The automatic claims on one class-shaped declaration: every tag a
 * marker rule or a class attribute would earn it, with the source
 * that claims it (see {@see Autoconfiguration} for the merge these
 * feed).
 *
 * Two sources, one loop each: the marker rules a class's interfaces
 * match ({@see AutoconfigMap}), and the `#[Tag]` attributes the class
 * carries (an attribute may repeat -- a class earns as many tags as
 * it declares). Both claim under the tag they would hand the
 * declaration, so one tag claimed twice is visible here as two
 * sources -- the collision the merge refuses.
 */
final readonly class AutoconfigClaims
{
    /**
     * @return array<string, list<string>> tag => the sources claiming it
     */
    public static function on(InstanceDefinition $instanceDefinition, AutoconfigMap $autoconfigMap): array
    {
        return self::merged(
            self::markerClaims($instanceDefinition->class, $autoconfigMap),
            self::attributeClaims($instanceDefinition->class),
        );
    }

    /**
     * @param class-string $class
     *
     * @return array<string, list<string>>
     */
    private static function markerClaims(string $class, AutoconfigMap $autoconfigMap): array
    {
        $claims = [];
        foreach ($autoconfigMap->interfaceTags() as $interface => $tag) {
            if (!is_a($class, $interface, true)) {
                continue;
            }

            $claims[$tag][] = "the tags.interfaces rule for '{$interface}'";
        }

        return $claims;
    }

    /**
     * @param class-string $class
     *
     * @return array<string, list<string>>
     */
    private static function attributeClaims(string $class): array
    {
        $claims = [];
        foreach (new ReflectionClass($class)->getAttributes(Tag::class) as $attribute) {
            $claims[$attribute->newInstance()->tag][] = 'the #[Tag] attribute';
        }

        return $claims;
    }

    /**
     * @param array<string, list<string>> $markerClaims
     * @param array<string, list<string>> $attributeClaims
     *
     * @return array<string, list<string>>
     */
    private static function merged(array $markerClaims, array $attributeClaims): array
    {
        $claims = $markerClaims;
        foreach ($attributeClaims as $tag => $sources) {
            $claims[$tag] = [...($claims[$tag] ?? []), ...$sources];
        }

        return $claims;
    }
}
