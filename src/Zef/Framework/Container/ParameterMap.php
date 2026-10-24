<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The `parameters` configuration knob's parse (ROADMAP, container area
 * 1 -- the registration-completion family the `#[Value]` attribute
 * consumes).
 *
 * The knob is a map of addresses to injectable values:
 *
 *     'parameters' => [
 *         'db' => ['host' => 'localhost', 'port' => 3306],
 *         'session_driver' => 'array',
 *     ]
 *
 * A string-keyed, non-empty nested map continues the address
 * (`db.host`); anything else -- a scalar, null, or an array -- is the
 * parameter's whole value. The empty knob, absent or null, is the
 * valid no-parameters boot: the framework declares the empty map
 * itself ({@see \Zef\Framework\FrameworkDefaults}), a host's
 * parameters arrive with the host's configuration.
 *
 * The reserved `providers` key names the parameter providers -- the
 * addresses whose values are computed at the compile phase, each
 * declaration a {@see ParameterProviderInterface} class name
 * ({@see ParameterProviders} for the lane's own validation and
 * invocation). The reservation is total: the key always reads as the
 * machinery's own, so a literal value under it is refused rather than
 * guessed into a meaning, the same way the `tags` knob's
 * `interfaces` key reads.
 *
 * Environment values close by construction, not by machinery: the knob
 * lives in the layered configuration tree, and the environment source
 * the kernel already folds (`APP_PARAMETERS__DB__HOST` onto
 * `parameters.db.host`) is the environment layer's own addressing --
 * a parameter takes its value from the running process's environment
 * without the container layer learning the environment exists.
 *
 * One address, one source: a literal and a provider on the same
 * address is refused with both spellings named, the same single-source
 * discipline the autoconfiguration lane enforces on a tag.
 */
final readonly class ParameterMap
{
    /** The reserved machinery key inside the knob: the provider declarations. */
    public const string PROVIDERS_KEY = 'providers';

    private function __construct(
        private ParameterValues $parameterValues,
    ) {
    }

    /** The parsed values: the flat, dot-addressed map the compile pass reads. */
    public function values(): ParameterValues
    {
        return $this->parameterValues;
    }

    /**
     * Parses the `parameters` configuration entry into its validated
     * values.
     *
     * @param mixed $source the raw `parameters` value from the configuration
     */
    public static function parse(string $key, mixed $source): self
    {
        if ($source === null) {
            return new self(ParameterValues::empty());
        }

        if (!\is_array($source)) {
            throw ParameterRefusals::notAMap($key, $source);
        }

        foreach (array_keys($source) as $entryKey) {
            if (!\is_string($entryKey)) {
                throw ParameterRefusals::keyNotAnAddress($key, $entryKey);
            }
        }

        $literals = $source;

        if (\array_key_exists(self::PROVIDERS_KEY, $literals)) {
            unset($literals[self::PROVIDERS_KEY]);
        }

        $literalValues = ParameterLiterals::values($literals);

        $declarations = ParameterProviders::declarations(self::providersMember($source));

        foreach (array_keys($declarations) as $address) {
            if (\array_key_exists($address, $literalValues)) {
                throw ParameterRefusals::addressDoubleSourced($address);
            }
        }

        return new self(new ParameterValues([
            ...$literalValues,
            ...ParameterProviderAnswers::values($declarations),
        ]));
    }

    /**
     * The reserved providers member itself -- the validating fold the
     * declarations lane consumes -- or the empty map when the knob
     * carries no providers half at all: the member's own presence is
     * the distinction the empty answer keeps honest (an absent key is
     * no providers, a present-but-unvalidated member is the refusal
     * the declarations lane raises in its own words).
     *
     * @param array<array-key, mixed> $source the knob's whole map
     *
     * @return mixed the providers member as the configuration carried it
     */
    private static function providersMember(array $source): mixed
    {
        if (\array_key_exists(self::PROVIDERS_KEY, $source)) {
            return $source[self::PROVIDERS_KEY];
        }

        return [];
    }
}
