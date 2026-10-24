<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The body-parser family's validated knob set: the `http.body_parser`
 * configuration subtree as one immutable value object -- following the
 * compression family's own {@see CompressionPolicy} lineage, which
 * followed the timing family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. The arm reads through
 * the shared toggle reader ({@see StrictToggles}), the depth ceiling
 * through the integer seam ({@see StrictIntegers}), and the
 * unknown-key census is {@see BodyParserKnobs}: a wrong type, an
 * out-of-corridor bound or an unknown key halts the boot with the
 * knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The family arms ON by default: a deployment that composes the
 * middleware into its stack is asking for parsed bodies, exactly as a
 * deployment that composes the compression middleware is asking for
 * compressed answers. The depth default (32) is the replay lane's own
 * documented ceiling ({@see ReplayBodyDecoder}) -- the same number
 * the error system already refuses past for a structured body, so the
 * two JSON decoders in the process refuse at the same depth by
 * default. The corridor's ceiling (512) is the engine's own
 * `json_decode` maximum: no knob value can ask the engine for a
 * recursion ceiling it cannot deliver.
 */
final readonly class BodyParserPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool        $enabled   the family's arm: a disabled family is a
     *                               pass-through that parses nothing
     * @param int<1, 512> $jsonDepth the engine recursion ceiling the JSON
     *                               strategy asks for
     */
    public function __construct(
        public bool $enabled = true,
        public int $jsonDepth = 32,
    ) {
    }

    /**
     * Reads the merged `http.body_parser` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading or its bound
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The http.body_parser configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        BodyParserKnobs::rejectUnknown($map);

        $jsonDepth = StrictIntegers::read(
            $map['json_depth'] ?? 32,
            'http.body_parser json_depth',
        );
        if ($jsonDepth < 1 || $jsonDepth > 512) {
            throw new HttpException(
                'The http.body_parser json_depth knob must be between 1 and 512, '
                . $jsonDepth . self::GIVEN_SUFFIX,
            );
        }

        return new self(
            enabled: StrictToggles::read($map['enabled'] ?? true, 'http.body_parser enabled'),
            jsonDepth: $jsonDepth,
        );
    }
}
