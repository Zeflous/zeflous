<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

use Zef\Framework\Container\ContainerCompilePlan;

/**
 * The AOT lane's answer for one boot: which lane answered it, why, and
 * what the compile saw -- the value the reserved `framework.aot`
 * container service carries ({@see \Zef\Framework\FrameworkDefaults::AOT_SERVICE},
 * the `framework.config` precedent: introspection through the container's
 * own public surface, registered ahead of the definitions so a host's
 * same-identifier rebind wins).
 *
 * The unreferenced report rides along: the definitions the planned set's
 * own reference graph never reaches (no argument, alias, factory or
 * method-call edge names them). Every zeflous service stays public and
 * addressable, so the report names rather than drops them -- a
 * declaration nothing references is the host's to judge, not the lane's
 * to silently remove (the honest form the roadmap's unused-service
 * removal takes under an all-public model).
 *
 * @internal the bootstrapper threads the outcome; the service makes it
 *           observable. Pure data, no behaviour beyond the accessors.
 */
final readonly class AotOutcome
{
    /**
     * The note a proven-stale artifact leaves behind: the bypass the
     * probe answered false to, named in the boot answer.
     */
    private const string BYPASSED_NOTE
        = 'the artifact digest did not match the configuration -- recompiled and re-dumped';

    /**
     * The note a development boot leaves when the knob is on: the mode
     * held the artifact lane to the fresh compile, on both sides.
     */
    private const string DEVELOPMENT_NOTE
        = 'development mode holds the artifact lane to the fresh compile -- '
        . 'the artifact is neither probed nor dumped in development';

    /**
     * @param list<string> $unreferenced the unreferenced identifiers, in
     *                                   declaration order
     */
    private function __construct(
        public AotLane $lane,
        public string $note,
        public array $unreferenced,
        public int $definitions,
    ) {
    }

    /**
     * @param list<string> $unreferenced the report baked into the artifact
     *                                   at dump time
     */
    public static function artifactLoaded(array $unreferenced, int $definitions): self
    {
        return new self(
            AotLane::ArtifactLoaded,
            'the artifact answered the boot',
            $unreferenced,
            $definitions,
        );
    }

    /**
     * The full lane's answer for the plan it just compiled: which note
     * the compile ran under -- a proven-stale artifact set aside and
     * replaced, the lane on with no artifact yet, the lane held to
     * the fresh compile by development mode, or the lane off.
     */
    public static function forTheLane(
        ContainerCompilePlan $containerCompilePlan,
        bool $laneEnabled,
        bool $bypassed,
        bool $developmentMode,
    ): self {
        if ($bypassed) {
            return self::artifactBypassed(
                self::BYPASSED_NOTE,
                $containerCompilePlan->unreferenced,
                \count($containerCompilePlan->definitions),
            );
        }

        return self::forFullLane(
            $laneEnabled,
            $developmentMode,
            $containerCompilePlan->unreferenced,
            \count($containerCompilePlan->definitions),
        );
    }

    /**
     * @param non-empty-string $note         why the full pipeline ran (the knob
     *                                       off, or on with no artifact present)
     * @param list<string>     $unreferenced
     */
    private static function fullCompiled(string $note, array $unreferenced, int $definitions): self
    {
        return new self(AotLane::FullCompile, $note, $unreferenced, $definitions);
    }

    /**
     * @param non-empty-string $reason       why the artifact was set aside
     * @param list<string>     $unreferenced the full pipeline's own report
     */
    private static function artifactBypassed(string $reason, array $unreferenced, int $definitions): self
    {
        return new self(AotLane::ArtifactBypassed, $reason, $unreferenced, $definitions);
    }

    /**
     * The full lane's answer under the knob it ran with: the lane on
     * with no artifact yet (the compile below lays one down for the
     * next boot), the lane on but held to the fresh compile by
     * development mode, or the lane off -- the note the host's own
     * configuration chose.
     *
     * @param list<string> $unreferenced the compile's own report
     */
    private static function forFullLane(
        bool $laneEnabled,
        bool $developmentMode,
        array $unreferenced,
        int $definitions,
    ): self {
        $note = 'the aot knob is off';

        if ($laneEnabled) {
            $note = $developmentMode
                ? self::DEVELOPMENT_NOTE
                : 'the artifact was absent -- compiled and dumped for the next boot';
        }

        return self::fullCompiled($note, $unreferenced, $definitions);
    }
}
