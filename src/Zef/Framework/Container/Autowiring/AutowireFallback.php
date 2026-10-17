<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Autowiring;

/**
 * The planner's last tier: the unbound parameter's fallback (see
 * {@see AutowireParameterPlanner} for the tiers above it).
 *
 * The constructor's own default, baked verbatim when there is one --
 * the honest value the class itself declared. Anything else is the
 * honest refusal: an unsupported type with its reason, a required
 * parameter the container cannot derive. Never a guessed value, never
 * a null sneaked into a slot the declaration did not spell.
 */
final readonly class AutowireFallback
{
    /**
     * @return list<mixed> the fallback stretch, in the definition grammar's own form
     */
    public static function stretch(string $ownerId, AutowireParameterSpec $autowireParameterSpec): array
    {
        if ($autowireParameterSpec->hasDefault) {
            return [$autowireParameterSpec->defaultValue];
        }

        if ($autowireParameterSpec->unsupportedTypeReason !== null) {
            throw AutowireRefusals::unsupportedType($ownerId, $autowireParameterSpec);
        }

        throw AutowireRefusals::underivable($ownerId, $autowireParameterSpec);
    }
}
