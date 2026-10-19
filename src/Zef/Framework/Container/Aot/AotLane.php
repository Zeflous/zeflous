<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * Which lane answered one boot, the introspectable half of
 * {@see AotOutcome}.
 *
 * Three lanes, one honesty rule each:
 *
 * - `FullCompile` -- the reflection-based pipeline ran. The knob may be
 *   off, or on with no artifact present yet: both are the honest
 *   full-lane boot, the note spells which.
 * - `ArtifactLoaded` -- the artifact answered the boot: its closures
 *   installed, zero reflection, zero planning.
 * - `ArtifactBypassed` -- an artifact was present but its digest does not
 *   match the live configuration, so it was never run: the full pipeline
 *   compiled afresh and laid a fresh artifact down (the automatic
 *   invalidation). The artifact is an acceleration, never a source of
 *   truth, and a proven-stale acceleration is set aside, not trusted.
 */
enum AotLane: string
{
    case FullCompile = 'full';
    case ArtifactLoaded = 'artifact';
    case ArtifactBypassed = 'bypassed';
}
