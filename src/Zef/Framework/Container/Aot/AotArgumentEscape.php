<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The rendering lane's literal-escape recognition: the other half of the
 * argument grammar wall ({@see AotArgumentReference} the reference
 * half), its own unit so the rendering ({@see AotArguments}) stays the
 * code half alone.
 *
 * The escape is the same grammar's own spelling
 * ({@see \Zef\Framework\Container\ArgumentResolution::resolvedEntry()}
 * answers it identically at the runtime walk): a single-key
 * `['literal' => $value]` map answers `$value` itself -- the one way to
 * hand the resolution a value whose own shape is the reference form.
 * A map that carries the 'literal' key beside other keys is neither the
 * escape nor data, and is refused instead with the escape named
 * ({@see AotRefusals::argumentGrammarCarrier()}): the ambiguity is
 * surfaced, never silently resolved.
 */
final readonly class AotArgumentEscape
{
    /**
     * The literal escape's own value -- or the plain entry itself when the
     * entry is not the escape, the lane the verbatim rendering answers.
     *
     * @param string $serviceId the definition the argument serves, for the refusal
     * @param string $path      the dotted position, for the refusal
     */
    public static function value(mixed $argument, string $serviceId, string $path): mixed
    {
        if (!\is_array($argument) || !\array_key_exists('literal', $argument)) {
            return $argument;
        }

        if (\count($argument) !== 1) {
            throw AotRefusals::argumentGrammarCarrier($serviceId, $path, 'literal');
        }

        return $argument['literal'];
    }
}
