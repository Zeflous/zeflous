<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The compression family's validated knob set: the `http.compression`
 * configuration subtree as one immutable value object -- following the
 * timing family's own {@see TimingPolicy} lineage, which followed the
 * auth family's, which followed the CSRF family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. The arm and the
 * disclosure toggle read through the shared toggle reader
 * ({@see StrictToggles}), the two numeric knobs through the integer
 * seam ({@see StrictIntegers}), and the unknown-key census is
 * {@see CompressionKnobs}: a wrong type, an out-of-corridor bound or
 * an unknown key halts the boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The family arms ON by default: a deployment that composes the
 * middleware into its stack is asking for compression, exactly as a
 * deployment that composes the timing middleware is asking for
 * measurement. The threshold default is the roadmap's own example
 * (1 KB -- compressing a 300-byte JSON reply spends more CPU than the
 * bytes save), and the level default is zlib's own middle (6), where
 * the ratio curve has already flattened and the cost curve has not
 * steepened.
 */
final readonly class CompressionPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled      the family's arm: a disabled family is a
     *                           pass-through that measures nothing
     * @param int  $minimumBytes the threshold: only bodies at or above it
     *                           compress, in bytes
     * @param int  $level        the zlib compression level the engine
     *                           asks for, 1..9
     */
    public function __construct(
        public bool $enabled = true,
        public int $minimumBytes = 1024,
        public int $level = 6,
    ) {
    }

    /**
     * Reads the merged `http.compression` map into the policy.
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
                'The http.compression configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        CompressionKnobs::rejectUnknown($map);

        $minimumBytes = StrictIntegers::read(
            $map['minimum_bytes'] ?? 1024,
            'http.compression minimum_bytes',
        );
        if ($minimumBytes < 1 || $minimumBytes > 1048576) {
            throw new HttpException(
                'The http.compression minimum_bytes knob must be between 1 and 1048576, '
                . $minimumBytes . self::GIVEN_SUFFIX,
            );
        }

        $level = StrictIntegers::read($map['level'] ?? 6, 'http.compression level');
        if ($level < 1 || $level > 9) {
            throw new HttpException(
                'The http.compression level knob must be between 1 and 9, '
                . $level . self::GIVEN_SUFFIX,
            );
        }

        return new self(
            enabled: StrictToggles::read($map['enabled'] ?? true, 'http.compression enabled'),
            minimumBytes: $minimumBytes,
            level: $level,
        );
    }
}
