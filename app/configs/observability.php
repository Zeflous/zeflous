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
 * The `correlation` family -- the propagation boundary
 * (`observability.correlation`, the service the framework registers
 * over the tracing family's own tracer; the PSR-15 middleware the
 * stack composes to open the request span under the inbound W3C
 * trace-context):
 *
 *   'enabled'                   (bool, default false)
 *       The family's arm. OFF by default and by design: an armed
 *       boundary over a disarmed tracer is work nobody can observe
 *       -- the request span would be the void singleton, every
 *       recording call ignored, the echo refused -- and the
 *       boundary cannot check the tracer's arm without breaking
 *       the interchangeability the tracing core is built on (the
 *       void span is the same answer either way). A deployment
 *       that wants request spans writes this arm AND the tracing
 *       family's own arm explicitly -- two decisions, two knobs,
 *       neither taken on the host's behalf.
 *
 *   'echo_traceparent'          (bool, default false)
 *       The disclosure: stamp the response's `traceparent` header
 *       with the request span's own identity, so the client can
 *       correlate its request with the server's span. OFF by
 *       default -- a response traceparent tells the client the
 *       server traces and hands it the server span's identity, and
 *       a disclosure knob's safe default is the one that discloses
 *       nothing. The echo also refuses the void identity outright:
 *       a disarmed tracer never stamps the all-zero context on a
 *       response.
 *
 * The `tracing` family -- the tracing core (`observability.tracing`,
 * the service the framework registers):
 *
 *   'enabled'                   (bool, default false)
 *       The family's arm. OFF by default and by design -- the one
 *       posture where this family inverts the timing family's
 *       example: an armed tracer is never free (every span is an
 *       allocation, two clock reads, an identity mint and a queue
 *       append), and the collection it feeds has no delivery
 *       consumer until the export lane lands. A collection with no
 *       consumer is not a default a framework decides on the host's
 *       behalf -- the collection is the deployment's decision, and
 *       a deployment that wants it writes the arm explicitly. A
 *       disabled family answers the void span for every call, the
 *       same code path either way.
 *
 *   'max_queue'                 (int, default 2048)
 *       The processor's queue bound: completed spans beyond it are
 *       dropped, never queued. An unbounded queue under load is a
 *       memory leak the observability subsystem would inflict on
 *       the very application it instruments -- a dropped span is a
 *       lost measurement, never a lost request.
 *
 *   'batch_size'                (int, default 256)
 *       The largest batch one delivery call carries: the queue
 *       drains in slices of at most this many completed spans.
 *
 * Environment overlay spelling (prefix + `__` as the dot, values
 * strictly typed): APP_OBSERVABILITY__TIMING__ENABLED=1,
 * APP_OBSERVABILITY__TIMING__EMIT_HEADER=on,
 * APP_OBSERVABILITY__TRACING__ENABLED=true,
 * APP_OBSERVABILITY__TRACING__MAX_QUEUE=512,
 * APP_OBSERVABILITY__TRACING__BATCH_SIZE=64,
 * APP_OBSERVABILITY__CORRELATION__ENABLED=on,
 * APP_OBSERVABILITY__CORRELATION__ECHO_TRACEPARENT=1.
 *
 * The shipped profile declares no overrides -- the defaults are the
 * posture -- and an application fills this file with the knobs it
 * wants adjusted.
 */

return [];
