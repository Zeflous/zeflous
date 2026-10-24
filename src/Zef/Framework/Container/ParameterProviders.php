<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The parameter providers lane: the reserved `providers` declarations
 * validated, then invoked (see {@see ParameterMap} for the knob's own
 * parse).
 *
 * The two halves are deliberately separate so the host's code runs
 * only after the whole declaration set has proven coherent: the shape
 * validation ({@see self::declarations()}) refuses a malformed
 * declaration before any provider is constructed, and the invocation
 * ({@see self::values()}) runs each validated provider exactly once,
 * in declaration order, checking the answer through the family's
 * shared injectability predicate ({@see ParameterInjectability} -- the
 * offender is quoted, never a bare verdict).
 */
final readonly class ParameterProviders
{
    /**
     * The validated declarations: address => provider class, in
     * declaration order.
     *
     * @param mixed $source the reserved 'providers' map, unvalidated
     *
     * @return array<string, class-string<ParameterProviderInterface>>
     */
    public static function declarations(mixed $source): array
    {
        if (!\is_array($source)) {
            throw ParameterRefusals::providersNotAMap($source);
        }

        $declarations = [];

        foreach (array_keys($source) as $address) {
            if (!\is_string($address)) {
                throw ParameterRefusals::providerKeyNotAnAddress($address);
            }

            ParameterKeys::assert("The 'parameters' > 'providers' address", $address);
            $declarations += [$address => ParameterProviderCheck::assert($address, $source[$address])];
        }

        return $declarations;
    }
}
