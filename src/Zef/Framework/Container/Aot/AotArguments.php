<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The argument grammar's rendering half: one validated argument entry as
 * PHP code inside a generated closure.
 *
 * The recognition mirrors {@see \Zef\Framework\Container\ArgumentResolution::resolvedEntry()}
 * exactly -- a map whose `service` key holds a string answers the
 * container's service (`$c->get('...')`), everything else is the plain
 * value rendered verbatim -- so a closure the artifact carries resolves
 * its arguments identically to the full lane's runtime walk: the same
 * entries become the same answers through the same container calls. The
 * nested case stays verbatim on both sides: a service-shaped map *inside*
 * a plain array value is data the resolution passes through untouched,
 * so the rendering exports it untouched too.
 */
final readonly class AotArguments
{
    /**
     * Renders one argument entry: the reference form as the container
     * call, anything else as the literal.
     *
     * @param string $serviceId the definition the argument serves
     * @param string $path      the dotted position, for the refusal
     */
    private static function code(mixed $argument, string $serviceId, string $path): string
    {
        if (\is_array($argument) && \array_key_exists('service', $argument) && \is_string($argument['service'])) {
            return '$c->get(' . var_export($argument['service'], true) . ')';
        }

        return AotLiterals::export($argument, $serviceId, $path);
    }

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

        foreach ($arguments as $index => $argument) {
            $rendered[] = self::code($argument, $serviceId, $path . '.' . $index);
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

        foreach ($arguments as $index => $argument) {
            $rendered[] = self::code($argument, $serviceId, $path . '.' . $index);
        }

        return '[' . implode(', ', $rendered) . ']';
    }
}
