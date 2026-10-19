<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The parameter providers lane's invocation half: each validated
 * declaration's provider constructed and invoked exactly once, its
 * answer checked injectable through the family's shared predicate
 * ({@see ParameterInjectability} -- the offender is quoted, never a
 * bare verdict).
 *
 * Kept its own unit so the providers lane's two halves stay separately
 * sized: {@see ParameterProviders::declarations()} refuses a malformed
 * declaration before any provider is constructed; this class runs only
 * the already-validated set, in declaration order -- the host's code
 * executes after the declaration set has proven coherent.
 */
final readonly class ParameterProviderAnswers
{
    /**
     * The computed values, in declaration order.
     *
     * @param array<string, class-string<ParameterProviderInterface>> $declarations
     *
     * @return array<string, mixed> address => computed value
     */
    public static function values(array $declarations): array
    {
        $computed = [];

        foreach ($declarations as $address => $provider) {
            $answer = new $provider()->value();
            $offender = ParameterInjectability::offender($answer);

            if ($offender !== null) {
                throw ParameterRefusals::providerAnsweredUninjectable($address, $provider, $offender);
            }

            $computed[$address] = $answer;
        }

        return $computed;
    }
}
