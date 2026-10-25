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
 * The `export` family -- the OTLP delivery lane (`observability.export`,
 * no service of its own: the family swaps the tracing service's sink
 * and arms its retry posture, and the drain cadence rides the
 * correlation boundary that owns the request span):
 *
 *   'enabled'                   (bool, default false)
 *       The family's arm. OFF by default: the in-memory sink is the
 *       honest default a deployment without a collector declaration
 *       gets, and the second decision in the campaign's fail-closed
 *       chain -- the collection arms (tracing), then the lane arms
 *       (export), then the cadence arms (flush_per_request): three
 *       decisions, three knobs, none taken on the host's behalf.
 *       An ARMED family requires a usable endpoint and a non-empty
 *       service name -- the resolution halts with the remediation
 *       spelled out otherwise (the CSRF family's fail-loud
 *       lineage).
 *
 *   'endpoint'                  (string, default '')
 *       The collector's absolute HTTP/HTTPS URL (the `/v1/traces`
 *       signal path is appended unless the endpoint already names
 *       it). Validated when the family is armed: relative URLs,
 *       wrong schemes and embedded credentials
 *       (https://user:pass@collector) are each a named halt -- the
 *       last because the transport's own failure diagnostics would
 *       echo the credentials into exception text.
 *
 *   'timeout_ms'                (int, default 500, 1..10000)
 *       One delivery exchange's own wait.
 *
 *   'service_name'              (string, default 'zef-application')
 *       The resource block's service identity -- the one attribute
 *       every collector groups by. Non-empty when the family is
 *       armed.
 *
 *   'max_retries'               (int, default 0, 0..10)
 *       The retry lane's additional attempts per failed batch --
 *       a transient failure (a refused connection, a timed-out
 *       exchange, a collector's 5xx) earns them, a permanent
 *       rejection (a 4xx) earns none. ZERO by default: the
 *       one-attempt posture the bounded queue already implied, the
 *       shipped posture a deployment that wants a retrying lane
 *       writes explicitly -- a blocking backoff in the drain is a
 *       latency posture the deployment decides on.
 *
 *   'retry_delay_ms'            (int, default 100, 0..10000)
 *       The first retry's wait, in milliseconds.
 *
 *   'retry_delay_cap_ms'        (int, default 1000, 0..60000)
 *       The wait's cap -- the exponential doubling clamps here.
 *       Never below the initial delay (the halt is knob-named).
 *
 *   'flush_per_request'         (bool, default false)
 *       The drain cadence: when armed, the correlation boundary
 *       flushes the completed-span queue after the request span
 *       ends -- the span's record joins the queue first, the
 *       delivery fires second. OFF by default because a per-request
 *       flush puts the delivery's own latency inside the request
 *       path.
 *
 * The `metrics` family -- the exposition boundary
 * (`observability.metrics`, the service the framework registers
 * over the two engine services the same registration block
 * installs: `observability.meter` the shared counter, and
 * `observability.health` the health-seam registry a host's own
 * rebind joins their probes into):
 *
 *   'enabled'                   (bool, default false)
 *       The family's arm. OFF by default and by design: the two
 *       endpoints are PUBLICATIONS -- an `/metrics` answers the
 *       internal series names and their dimensions, a `/health`
 *       answers the dependency topology the probes reveal -- and a
 *       publication knob's safe default is the one that publishes
 *       nothing. This is the fourth and closing decision in the
 *       campaign's fail-closed chain: the collection armed
 *       (tracing), then the lane (export), then the cadence
 *       (flush_per_request), and now the surfaces themselves. An
 *       ARMED family requires a usable path pair -- each path
 *       absolute and free of the bytes a request target grammar
 *       refuses, and the metrics path not colliding with the
 *       health surface's three endpoints -- or the resolution
 *       halts with the remediation spelled out.
 *
 *   'metrics_path'              (string, default '/metrics')
 *       The exposition endpoint's path: the scrape target the
 *       collector polls, answered with the text exposition format
 *       (version 0.0.4) over the shared meter's snapshot. Judged
 *       exact-path, GET-only.
 *
 *   'health_path'               (string, default '/health')
 *       The health surface's base path, with the `/live` and
 *       `/ready` views deriving from it: the aggregate JSON over
 *       the health-seam registry, 200 when every probe passed and
 *       503 when any probe failed, crashed or was skipped -- the
 *       status the load balancer gates on.
 *
 *   'timeout_ms'                (int, default 500, 1..10000)
 *       The health scrape's overall deadline: once the budget has
 *       passed, the remaining probes are marked down with the skip
 *       marker instead of extending the response -- a hanging sync
 *       probe can hold its own slot, but the response as a whole
 *       can no longer block forever.
 *
 * The `slow_request` family -- the total-duration boundary
 * (`observability.slow_request`, the roadmap HTTP Phase 4.1
 * request-lifecycle tracing's closing item):
 *
 *   'enabled'                   (bool, default true)
 *       The family's arm. A disabled family is a pass-through that
 *       never reads the clock -- the timing family's own inert
 *       posture -- while a deployment that composes the middleware
 *       into its stack is asking to be told about pathology, which
 *       is why the arm ships on.
 *
 *   'threshold_ms'              (int, default 1000, 1..3600000)
 *       The bar a request must STRICTLY exceed to be recorded: one
 *       full second, the weight where a request stops being an
 *       answer and starts being a question. The corridor holds at
 *       both construction paths and refuses the nonsense spellings
 *       (the zero bar that would record every request, the
 *       hour-deep bar and beyond); a legal-but-mistyped unit value
 *       stays legal -- the record's own threshold_ms fact carries
 *       the bar it was judged against, the number to correct. The record
 *       carries method, path, request id and the two duration
 *       facts -- privacy-by-default, exactly the error lane's own
 *       request facts -- and travels to the error-handling lane's
 *       own `http.error_handling.log_path` sink when one is
 *       configured, so slow-request records and error records join
 *       on the same identifier; an unconfigured path leaves the
 *       record on the SAPI error log. The record fires on the
 *       request that answered slowly AND the request that threw
 *       slowly: a total duration exists either way.
 *
 * Environment overlay spelling (prefix + `__` as the dot, values
 * strictly typed): APP_OBSERVABILITY__TIMING__ENABLED=1,
 * APP_OBSERVABILITY__TIMING__EMIT_HEADER=on,
 * APP_OBSERVABILITY__TRACING__ENABLED=true,
 * APP_OBSERVABILITY__TRACING__MAX_QUEUE=512,
 * APP_OBSERVABILITY__TRACING__BATCH_SIZE=64,
 * APP_OBSERVABILITY__CORRELATION__ENABLED=on,
 * APP_OBSERVABILITY__CORRELATION__ECHO_TRACEPARENT=1,
 * APP_OBSERVABILITY__EXPORT__ENABLED=1,
 * APP_OBSERVABILITY__EXPORT__ENDPOINT=http://collector:4318,
 * APP_OBSERVABILITY__EXPORT__TIMEOUT_MS=250,
 * APP_OBSERVABILITY__EXPORT__SERVICE_NAME=my-service,
 * APP_OBSERVABILITY__EXPORT__MAX_RETRIES=3,
 * APP_OBSERVABILITY__EXPORT__RETRY_DELAY_MS=50,
 * APP_OBSERVABILITY__EXPORT__RETRY_DELAY_CAP_MS=2000,
 * APP_OBSERVABILITY__EXPORT__FLUSH_PER_REQUEST=on,
 * APP_OBSERVABILITY__METRICS__ENABLED=1,
 * APP_OBSERVABILITY__METRICS__METRICS_PATH=/metrics,
 * APP_OBSERVABILITY__METRICS__HEALTH_PATH=/health,
 * APP_OBSERVABILITY__METRICS__TIMEOUT_MS=250,
 * APP_OBSERVABILITY__SLOW_REQUEST__ENABLED=1,
 * APP_OBSERVABILITY__SLOW_REQUEST__THRESHOLD_MS=2500.
 *
 * The shipped profile declares no overrides -- the defaults are the
 * posture -- and an application fills this file with the knobs it
 * wants adjusted.
 */

return [];
