<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * One provider declaration's validation: the address is a
 * grammar-valid address, the entry is a class name, the class exists,
 * implements {@see ParameterProviderInterface}, and constructs with no
 * arguments (through {@see ParameterProviderConstruction} -- a
 * provider declaring constructor parameters is a declaration defect,
 * and guessing a source would be exactly the magic the grammar
 * refuses everywhere else).
 *
 * Its own unit the way the literals walk's guards are: the providers
 * lane's declaration half stays a list of one-concern checks, each
 * refusing with the address and the provider named through
 * {@see ParameterRefusals}.
 */
final readonly class ParameterProviderCheck
{
    /**
     * Asserts one declaration's provider entry: a class name that
     * exists, implements the interface, and constructs with no
     * arguments. The validated class is handed back, so the caller's
     * answer carries the class-string type the assertion earned.
     *
     * @return class-string<ParameterProviderInterface>
     */
    public static function assert(string $address, mixed $provider): string
    {
        if (!\is_string($provider)) {
            throw ParameterRefusals::providerNotAClassName($address, $provider);
        }

        if (!class_exists($provider)) {
            throw ParameterRefusals::providerMissing($address, $provider);
        }

        if (!is_a($provider, ParameterProviderInterface::class, true)) {
            throw ParameterRefusals::providerNotImplementing($address, $provider);
        }

        if (!ParameterProviderConstruction::bare($provider)) {
            throw ParameterRefusals::providerNotBareConstructible($address, $provider);
        }

        return $provider;
    }
}
