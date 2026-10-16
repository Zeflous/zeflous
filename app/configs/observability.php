<?php

declare(strict_types=1);

/*
 * The observability subsystem's configuration file: everything under
 * this key is the `observability` map the framework's observability
 * families read their validated knobs from at boot.
 *
 * The framework's own defaults live in code (`FrameworkDefaults`, the
 * Default layer under this file and the environment), so a host that
 * deletes or empties this file still boots on the full default
 * posture -- this file is the override surface, not the source of the
 * values. Every knob is read strictly: a wrong type or an unknown key
 * halts the boot with the knob named, never a silent fallback, and an
 * unknown family name under this key is refused the same way.
 *
 * The `timing` family -- the response-time boundary middleware
 * (`observability.timing`, the service the framework registers and
 * the stack composes):
 *
 *   'enabled'                   (bool, default true)
 *       The family's arm. A disabled family is a pass-through: the
 *       middleware stays in the stack (its membership is the host's
 *       declaration) but measures and stamps nothing. Toggling the
 *       arm per environment -- on in staging, off in a
 *       debug-frozen production -- is a knob write, not a stack
 *       surgery.
 *
 *   'emit_header'               (bool, default false)
 *       The disclosure: stamp the `X-Response-Time` header the
 *       response carries, the elapsed milliseconds between the
 *       middleware's two monotonic clock readings, rendered as the
 *       two-decimal `%.2fms` shape. OFF by default and by design --
 *       a server-side execution timing is an attacker-visible side
 *       channel, a full-precision oracle against blind attacks (the
 *       reference's ZEF-DX-08 finding: the header is a legitimate
 *       development signal in debug mode, and a deployment that
 *       wants it in production forces it on explicitly, here). An
 *       armed family that will not emit never reads the clock --
 *       the inert posture performs no work at all.
 *
 * Environment overlay spelling (prefix + `__` as the dot, values
 * strictly typed): APP_OBSERVABILITY__TIMING__ENABLED=1,
 * APP_OBSERVABILITY__TIMING__EMIT_HEADER=on.
 *
 * The shipped profile declares no overrides -- the defaults are the
 * posture -- and an application fills this file with the knobs it
 * wants adjusted.
 */

return [];
