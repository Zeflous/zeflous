<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The rendering lane's reference-form recognition: one half of the
 * argument grammar wall ({@see AotArgumentEscape} the other half), its
 * own unit so the rendering ({@see AotArguments}) stays the code half
 * alone.
 *
 * The recognition mirrors {@see \Zef\Framework\Container\ArgumentResolution::resolvedEntry()}
 * exactly -- a single-key map whose 'service' key holds a non-empty string
 * answers the container's service -- so a closure the artifact carries
 * resolves its arguments identically to the full lane's runtime walk
 * (issue #800's finding 1 aligned the grammar: the reference is the
 * single-key form alone). A map that carries the 'service' key in any
 * other shape -- extra keys beside it, or a non-string or empty
 * identifier -- is refused instead with the escape named
 * ({@see AotRefusals::argumentGrammarCarrier()}), because the ambiguity
 * is the plan's own defect: the rendering serves the validated plan,
 * whose entries already carry the validator's refusals, and the same
 * refusal answers the hand-built artifact the plan never saw.
 */
final readonly class AotArgumentReference
{
    /**
     * The single-key reference form's identifier, or null when the entry
     * is not the form: the rendering answers a null with its own literal
     * lane.
     *
     * @param string $serviceId the definition the argument serves, for the refusal
     * @param string $path      the dotted position, for the refusal
     */
    public static function target(mixed $argument, string $serviceId, string $path): ?string
    {
        if (!\is_array($argument) || !\array_key_exists('service', $argument)) {
            return null;
        }

        if (\count($argument) !== 1 || !\is_string($argument['service']) || $argument['service'] === '') {
            throw AotRefusals::argumentGrammarCarrier($serviceId, $path, 'service');
        }

        return $argument['service'];
    }
}
