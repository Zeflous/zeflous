<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The parameters family's refusal vocabulary (the registration-
 * completion lane's own words, so every message the family can raise
 * lives in one place -- the same discipline the injector and
 * contextual-binding families follow).
 */
final readonly class ParameterRefusals
{
    /** The value-type speller's tail, shared by the map-shaped refusals. */
    private const string GIVEN = ' given.';

    /** The knob itself is not a map of addresses to values. */
    public static function notAMap(string $key, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration must be a map of addresses to values, "
            . self::given($source) . self::GIVEN,
        );
    }

    /** A non-string key sits where an address belongs. */
    public static function keyNotAnAddress(string $key, int $offset): DefinitionException
    {
        return new DefinitionException(
            "The '{$key}' configuration keys must be dot-addresses, "
            . "the integer key {$offset} given.",
        );
    }

    /** A value under an address the container cannot inject -- the offender quoted. */
    public static function leafNotInjectable(string $address, mixed $offender): DefinitionException
    {
        return new DefinitionException(
            "The 'parameters' value at '{$address}' is not injectable, " . self::given($offender)
            . ' given -- a parameter is a scalar, null or an array of such.',
        );
    }

    /** The reserved providers key is not a map of declarations. */
    public static function providersNotAMap(mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The 'providers' key under 'parameters' must be a map of addresses to "
            . 'parameter-provider class names, ' . self::given($source) . self::GIVEN,
        );
    }

    /** A providers declaration key is not an address. */
    public static function providerKeyNotAnAddress(int|string $key): DefinitionException
    {
        return new DefinitionException(
            "The 'providers' key under 'parameters' must map addresses to parameter-provider "
            . "class names -- the key {$key} names no address.",
        );
    }

    /** A providers declaration does not name a class string. */
    public static function providerNotAClassName(string $address, mixed $source): DefinitionException
    {
        return new DefinitionException(
            "The 'parameters' > 'providers' entry for '{$address}' must name a "
            . ParameterProviderInterface::class . ' class, ' . self::given($source) . self::GIVEN,
        );
    }

    /** The named provider class does not exist. */
    public static function providerMissing(string $address, string $provider): DefinitionException
    {
        return new DefinitionException(
            "The parameter provider '{$provider}' (for the parameter '{$address}') does not exist.",
        );
    }

    /** The named provider class does not implement the interface. */
    public static function providerNotImplementing(string $address, string $provider): DefinitionException
    {
        return new DefinitionException(
            "The parameter provider '{$provider}' (for the parameter '{$address}') must implement "
            . ParameterProviderInterface::class . '.',
        );
    }

    /** The provider's constructor needs arguments the lane cannot supply. */
    public static function providerNotBareConstructible(string $address, string $provider): DefinitionException
    {
        return new DefinitionException(
            "The parameter provider '{$provider}' (for the parameter '{$address}') declares "
            . 'constructor parameters -- a provider constructs with none.',
        );
    }

    /** The provider's answer is not injectable -- the offender quoted. */
    public static function providerAnsweredUninjectable(
        string $address,
        string $provider,
        mixed $offender,
    ): DefinitionException {
        return new DefinitionException(
            "The parameter provider '{$provider}' (for the parameter '{$address}') answered a "
            . 'non-injectable value, ' . self::given($offender) . ' given -- a parameter is a '
            . 'scalar, null or an array of such.',
        );
    }

    /** One address spelled twice: as a literal and as a provider. */
    public static function addressDoubleSourced(string $address): DefinitionException
    {
        return new DefinitionException(
            "The parameter '{$address}' is declared both as a literal value and through "
            . "'parameters' > 'providers' -- one address, one source.",
        );
    }

    /** The value-type speller for refusal messages. */
    private static function given(mixed $source): string
    {
        return get_debug_type($source);
    }
}
