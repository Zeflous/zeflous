<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The timing family's validated knob set: the `observability.timing`
 * configuration subtree as one immutable value object -- following
 * the auth family's own {@see AuthPolicy} lineage, which followed
 * the CSRF family's, which followed the rate-limit family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven
 * path -- the merged map the layered builder hands over, where a
 * knob's value can be the native type a file declared or the raw
 * string an environment overlay wrote over it. Every knob is read
 * strictly through the shared toggle reader ({@see StrictToggles}),
 * the unknown-key census is {@see TimingKnobs}: a wrong type or an
 * unknown key halts the boot with the knob named.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The knobs and their posture follow the reference implementation's
 * ZEF-DX-08 finding (legacy `zef-main` `Middleware/
 * TimingMiddleware.php`): the family is armed by default (a
 * deployment that adds the middleware to its stack is measuring),
 * but the header ships OFF -- the execution timing is an
 * attacker-visible side channel in production, a legitimate
 * development signal in debug, and the disclosure is the deployment's
 * decision, never the framework's. One deliberate inversion over the
 * reference: its demo constructor defaulted the emission on (the
 * demo runs in debug mode), and this port's file-first profile
 * defaults to the production posture -- the safe default is the one
 * that discloses nothing.
 */
final readonly class TimingPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /**
     * @param bool $enabled    the family's arm
     * @param bool $emitHeader the header's disclosure
     */
    public function __construct(
        public bool $enabled = true,
        public bool $emitHeader = false,
    ) {
    }

    /**
     * Reads the merged `observability.timing` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The observability.timing configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        TimingKnobs::rejectUnknown($map);

        return new self(...self::knobs($map));
    }

    /**
     * The family's knob map as constructor arguments: the arm and the
     * disclosure -- read strictly, both.
     *
     * @param array<array-key, mixed> $map
     *
     * @return array{enabled: bool, emitHeader: bool}
     */
    private static function knobs(array $map): array
    {
        return [
            'enabled' => StrictToggles::read($map['enabled'] ?? true, 'observability.timing enabled'),
            'emitHeader' => StrictToggles::read(
                $map['emit_header'] ?? false,
                'observability.timing emit_header',
            ),
        ];
    }
}
