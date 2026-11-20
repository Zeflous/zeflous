<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The argument grammar's rendering half: one validated argument entry as
 * PHP code inside a generated closure.
 *
 * The recognition is the grammar wall's own pair of units
 * ({@see AotArgumentReference} the reference half, {@see AotArgumentEscape}
 * the escape half) -- they mirror {@see \Zef\Framework\Container\ArgumentResolution::resolvedEntry()}
 * exactly and refuse every malformed carrier of either reserved key
 * with the escape named, so this half stays the code alone: the
 * reference form as the container call, the literal escape as its own
 * value, anything else as the literal -- and a closure the artifact
 * carries resolves its arguments identically to the full lane's runtime
 * walk. The nested case stays verbatim on both sides: a service-shaped
 * map *inside* a plain array value is data the resolution passes
 * through untouched, so the rendering exports it untouched too.
 * (Issue #800's finding 1 aligned the grammar: the reference is the
 * single-key form alone, and the literal escape is its own single-key
 * spelling -- the rendering serves the validated plan, whose entries
 * already carry the validator's refusals.)
 */
final readonly class AotArguments
{
    /**
     * Renders a positional argument list for a call site: the entries,
     * comma-joined.
     *
     * @param list<mixed> $arguments the validated argument list
     * @param string      $serviceId the definition the arguments serve
     * @param string      $path      the dotted position of the list
     */
    public static function parameterList(array $arguments, string $serviceId, string $path): string
    {
        $rendered = [];

        foreach (array_keys($arguments) as $index) {
            $rendered[] = self::code($arguments[$index], $serviceId, $path . '.' . $index);
        }

        return implode(', ', $rendered);
    }

    /**
     * Renders a positional argument list as an array literal: the entries,
     * bracket-joined -- the form a reconstructed {@see \Zef\Framework\Container\MethodCall}
     * constructor carries.
     *
     * @param list<mixed> $arguments the validated argument list
     * @param string      $serviceId the definition the arguments serve
     * @param string      $path      the dotted position of the list
     */
    public static function arrayLiteral(array $arguments, string $serviceId, string $path): string
    {
        $rendered = [];

        foreach (array_keys($arguments) as $index) {
            $rendered[] = self::code($arguments[$index], $serviceId, $path . '.' . $index);
        }

        return '[' . implode(', ', $rendered) . ']';
    }

    /**
     * Renders one argument entry: the reference form as the container
     * call, the literal escape as its own value, anything else as the
     * literal.
     *
     * @param string $serviceId the definition the argument serves
     * @param string $path      the dotted position, for the refusal
     */
    private static function code(mixed $argument, string $serviceId, string $path): string
    {
        $serviceTarget = AotArgumentReference::target($argument, $serviceId, $path);

        if ($serviceTarget !== null) {
            return '$c->get(' . var_export($serviceTarget, true) . ')';
        }

        return AotLiterals::export(AotArgumentEscape::value($argument, $serviceId, $path), $serviceId, $path);
    }
}
