# CI Note — Observability Runtime Families (Config-Driven)

Lane: the observability port campaign -- the reference repositories'
telemetry strengths, re-landed here as fully config-driven families
over the application profile's own machinery (`app/configs/`, the
layered Default < App < Env merge, the declarative `services` grammar
and the one-call boot), exactly as the security runtime campaign
before it. The campaign's order follows the same comparison recorded
in the work log (session `REF-GITLAB`): the GitLab snapshot
(`zeflous/zef` `zef-main`, the `Domain/Application/Infrastructure/
Observability` hexagon plus `Middleware/TimingMiddleware.php`) is the
*what* -- the widest telemetry surface (tracing, metrics, OTLP export,
correlation, health) -- and this repository's own discipline is the
*how*.

The work log of this lane's PRs:

| PR | Scope |
|----|-------|
| R1 | The timing family: `TimingMiddleware` (the HTTP layer's ninth PSR-15 middleware -- the response-time boundary), `TimingPolicy`/`TimingKnobs`/`ObservabilityFamilies` (the family's own strict reading over the shared census, widened to the full dotted path), `ResponseTime` (the `%.2fms` shape value object), `TimingClock` (the monotonic seam), the `TimingFactory` construction seam, the framework profile's fifth registration and first `observability.*` knob block, the shipped `app/configs/observability.php` override surface, the `HttpHotPathBench` timed-request subject |
| R2 | The tracing core: the `Telemetry` layer's own thirteen classes (`SpanContext`/`SpanData` the W3C value objects, `SpanInterface`/`TracerInterface`/`SpanExporterInterface`/`TelemetryClockInterface` the contracts, `Span`/`Tracer` the live engine, `NoopSpan`/`NoopTracer` the void path, `BatchSpanProcessor` the bounded queue, `InMemorySpanExporter` the in-process sink, `TelemetryClock` the system clock), `TracingPolicy`/`TracingKnobs` (the family's strict reading, the campaign's second family in the census), the `TracingFactory` construction seam, the framework profile's sixth registration, the `TelemetrySpanBench` span-lifecycle subject |
| R3 | The correlation family: the propagation boundary -- `CorrelationMiddleware` (the PSR-15 middleware that opens the request span under the inbound W3C trace-context and answers the echo), the `Telemetry` layer's own four correlation classes (`TraceContextPropagator` the W3C span-identity pair, `CorrelationContext` the bounded causal context, `CorrelationHeaders` the transport pair, `CorrelationPropagator` the operation-level pair, over `CorrelationAttributes` the bounded redacting table), `CorrelationPolicy`/`CorrelationKnobs` (the family's strict reading, the campaign's third family in the census), the `CorrelationFactory` construction seam (the seventh registration, the first to spell a second service argument -- the tracing family's own tracer), the framework profile's `observability.correlation` knob block, the shipped `app/configs/observability.php` documentation |

The remaining families, in the order the reference's hexagon surfaces
them: the telemetry hygiene and OTLP export lane (the sanitizer, the
JSON payload encoder, the HTTP exporter with its retry policy -- the
lane that will widen the tracing core's recording points and delivery
seam), and the metrics and health lane (the `MeterInterface`/
`CounterMeter` pair, the Prometheus renderer, the grouped health
indicators over this repository's own already-live `Health` seam).

## The decisions (KDD-OB)

**KDD-OB1 -- The timing header ships OFF: the safe default is the one
that discloses nothing.** The reference's own ZEF-DX-08 finding
(legacy `zef-main` issue #248) names the posture this port pins: a
server-side execution timing is an attacker-visible side channel --
a full-precision oracle against blind attacks (the timing-attack
literature's canonical example) -- and the header is a legitimate
signal only in debug mode, where the operator already accepts
diagnostic disclosure. The reference's demo constructor therefore
defaulted the emission ON (the demo runs in debug mode) with a
per-deployment env-var override; the file-first port inverts the
default to the production posture: `observability.timing
emit_header = false`, and a deployment that wants the disclosure in
production writes it explicitly. The inversion is the whole point of
the knob: the framework never decides a disclosure on the host's
behalf, and the safe side of a disclosure decision is silence.

**KDD-OB2 -- The measurement is conditional on the disclosure, not
the other way round.** The inert posture (armed family, header off)
never reads the clock -- `readCount()` pinned at zero by test. The
reference measured unconditionally and discarded; the port treats "a
measurement nobody can observe" and "no measurement" as the same
thing, because they are: there is no attribute, no log line, no
side channel the discarded span feeds in this family. This keeps the
default posture honest (the shipped stack's timing middleware costs
a guard evaluation, nothing else) and keeps the mutation semantics
honest (an unconditional read would be an unkillable value-equivalent
zone).

**KDD-OB3 -- The `%.2fms` shape is a contract, spelled by sprintf and
pinned as a value object.** The reference's platform-smoke flake
(run 109715900195, pinned in its own comment) is the lesson: a
fast-runner span rounds to zero, and string-concatenating the `0.0`
float yields `0ms` -- a header shape the contract never declared.
`sprintf('%.2fms', ...)` keeps the two decimals even at zero. The
port moves the render out of the middleware into `ResponseTime`
(the family's value-object discipline: the arithmetic lives in one
named place the suite reads directly, because an elapsed-time render
that divides by the wrong constant still answers a header -- only a
test that reads the shape itself can catch it), and adds the negative
clamp the reference's span arithmetic already carried (`max(0, ns)`):
a negative delta is a clock artifact, never a negative duration, and
the header answers `0.00ms` rather than the nonsense `-0.25ms` a raw
render would print. The reference's `round(..., 2)` before its
sprintf was dead weight -- two rounding policies on one value, the
first invisible to every observable -- and the port drops it: one
rounding policy point, no immortal mutant.

**KDD-OB4 -- The clock is a frozen seam, two readings per measured
span.** The reference read `hrtime(true)` inline twice; the port
injects `Closure():int` (`TimingClock::monotonicNs(...)` the default)
so the suite freezes the span exactly (`SteppingClock`, the
two-reading double whose step IS the span) and the broken-clock
artifact (a second reading before the first) becomes a deterministic
test input rather than an untestable engine behaviour. `hrtime` over
`microtime` is the reference's own choice kept: the monotonic counter
is immune to the wall-clock adjustments (NTP steps, suspend/resume)
that would corrupt a span mid-measurement.

**KDD-OB5 -- The shared knob census widens to the full dotted path,
and the message stays byte-identical.** The security campaign's
`StrictKnobs::rejectUnknown($map, $family, $known)` hard-coded the
`security.` prefix in its rejection; the observability campaign's
first family arrives over a different root key. The widening --
`$family` now carries the full dotted path (`security.auth`,
`observability.timing`) -- is the auth family's own extraction
discipline replayed: the shape became shared property the moment a
second campaign needed it, and `AuthKnobs`' call site spells
`security.auth` so the security families' rejection messages are
byte-identical before and after (the existing message tests pin
this). `ListKnobs` already carried its label as one full string
(`security.auth tokens`) -- no widening needed there.

**KDD-OB6 -- The observability map carries its own family census.**
`ObservabilityFamilies` mirrors `SecurityFamilies` (the unknown-child
rejection with the known families spelled out): a `timming` typo in a
host's override file is a named halt at boot, not a silently absent
family whose knobs silently read as defaults. The census starts with
`timing` alone and grows with the campaign's later families exactly
as the security census grew -- per family, at landing.

**KDD-OB7 -- There is no fail-loud halt beyond the reading, and that
is the honest shape.** The security families each carry a posture the
boot must refuse (the CSRF secret, the auth token table -- armed
without material is a protection the operator believes is running
while it is not). The timing family has no runtime material to be
without: its one safety decision is the disclosure itself, and that
decision is the shipped-off default (KDD-OB1) plus the strict reading
-- there is no posture left for a halt to guard. The factory is
therefore the reading alone, and inventing a halt would be ceremony,
not protection.

**KDD-OB8 -- The tracing core is its own layer, and it is
config-blind.** The timing family lives in the HTTP layer because its
face is a PSR-15 middleware; the tracing core has no HTTP face at
all -- it is a runtime subsystem the propagation middleware (a later
family) will consume. The port gives it the `Telemetry` layer the
`Health` layer already models: value objects, contracts, live and
void implementations, depending only on the exception root. The
family's *config face* stays where every family's config face lives
(`Http`: `TracingPolicy`/`TracingKnobs`, over the shared strict
readers), and the *composition seam* stays where every family's
composition seam lives (`Middleware`: `TracingFactory`, which is what
widens the lane to the new layer). The direction is one-way and it
is the whole point: the engine never knows the config face exists,
so the tracing core is constructible, testable and reusable with no
configuration system in the process at all -- and the deptrac layer
comment records the widening as composition, not as the engine
reaching out.

**KDD-OB9 -- The collection ships OFF: a collection with no consumer
is the deployment's decision, not the framework's.** The timing
family arms by default because its inert posture costs nothing
(KDD-OB2's zero-read guard); the tracing family's armed posture is
never free -- every span is an allocation, two clock reads, an
identity mint and a queue append -- and the collection it feeds has
no delivery consumer until the export lane lands. The reference's
own environment posture agrees (`ZEF_OTEL_ENABLED` defaulted false);
the port keeps the fail-closed side in the file-first profile:
`observability.tracing enabled = false`, and a disabled family
answers the void span (`NoopSpan`, one singleton read, the caller's
code path identical either way -- the same armed/inert
interchangeability the security families' middlewares carry). The
inversion of the timing example is deliberate and recorded in the
knob's own documentation, because a reader who knows why timing is
armed deserves to know why tracing is not.

**KDD-OB10 -- One clock seam owns both time domains, and the span
owns its whole timeline.** The reference read the clock statically
three times across two classes (the tracer took the start pair, the
span took the end and every event's timestamp); the port injects one
`TelemetryClockInterface` whose two faces are the two domains
(`monotonicNs()` the duration domain, `unixNano()` the timestamp
domain) and hands it to the span, which takes its own start, its
events' timestamps and its own end through it. The seam is an
interface rather than the timing family's closure because the two
domains are one clock's two faces -- a closure per reading would
scatter the seam across the tracer and the span, and the mutation
run cannot reach what it cannot name. The suite freezes the entire
record with one stepping double whose two counters pin the exact
read sequence (`start-monotonic, start-wall, [event-wall...],
end-monotonic`), which is what makes the derived-wall discipline of
KDD-OB11 assertable at all.

**KDD-OB11 -- The wall end is derived, not read; the clamp is
observable through it.** A span reads the wall clock exactly once
(its start); the wall end is `startUnixNano + (endNs - startNs)`,
the monotonic delta doing the measuring -- so a wall-clock
adjustment between the two monotonic reads cannot bend a span's
apparent duration, and one wall reading per span is all the timeline
alignment ever needs. The end clamp (`max(startNs, endNs)`) keeps
the derived wall end at or after the wall start even when an
explicit end reading lands before the start (a broken clock, never a
negative duration): the clamp's own removal is killed through the
*wall* record, not the duration (the duration render clamps
independently at `SpanData::durationSeconds()`, so only the derived
wall end can distinguish a clamped span from a negative one) -- two
clamps, two observables, no immortal mutant.

**KDD-OB12 -- The queue is bounded and the delivery is one honest
attempt.** An unbounded queue under load is a memory leak the
observability subsystem would inflict on the application it
instruments; the processor ships a named bound (`max_queue`, spans
beyond it dropped -- a dropped span is a lost measurement, never a
lost request) and a batch slice (`batch_size`). The delivery is
single-attempt and swallows the exporter's failures: the tracing
core must never take the request path down with it, because every
consumer of a span sits inside the application's own work. The
reference's retry-with-backoff (and its permanent-rejection
distinction) is the campaign's hygiene-and-export lane and lands
there with its own knobs; this core ships the one-attempt posture
the bounded queue already implies -- a queue that cannot hold the
backlog cannot honestly promise to redeliver it either. The flush is
inert after shutdown *by construction* -- the shutdown drains fully
and the closed flag refuses every later enqueue, so there is nothing
left for a guard to guard (the reference guarded explicitly because
its deadline-bounded drain could leave a residue; a full drain
cannot).

**KDD-OB13 -- The lifecycle and the record are different types, and
the recording is verbatim until the hygiene lane lands.** The
mutable `Span` and the frozen `SpanData` are separate classes on
purpose: the end hands the record over exactly once (idempotent
close, every later write ignored), and nothing downstream can reach
back into a span that has finished and quietly rewrite what was
measured. The port records attribute and event values verbatim --
the reference filtered sensitive keys and clamped value shapes
through its telemetry sanitizer, which is this campaign's
hygiene-and-export lane (a later family), and the sequencing is
deliberate: the sanitizer will arrive at this core's recording
points (`Span::setAttribute()`/`addEvent()`/`setStatus()`), widening
the behaviour in the lane that owns it, rather than landing here as
a half-forgotten helper with no consumer of its policy. Until then
the core's contract is honest about what it does: it records what
it is given.


**KDD-OB14 -- The two propagation levels are two pairs, and the
distinction is the port's own.** The reference carries both a
`TraceContextPropagator` (the `traceparent` line extracted into a
`SpanContext` -- the tracer's parenting question: which trace,
which parent span) and a `CorrelationPropagator` (the operation's
causal context: which operation, which idempotency key, which
bounded diagnostics). The port keeps both and records why they are
not one: a hop that forwards work answers both, a hop that only
observes answers the first, and conflating them is how tracing
headers grow application cargo they were never scoped to carry.
The span-identity pair landed over the tracing core's own
`SpanContext` (R2's identities, consumed exactly as promised); the
operation-level pair landed as its own four classes with the
bounded `CorrelationAttributes` table split out as the one unit
whose two faces -- validation and redaction -- the mutation run
reaches as one target.

**KDD-OB15 -- Both of the family's knobs ship OFF: the double
fail-closed posture.** The timing family arms by default because
its inert posture never reads the clock (KDD-OB2); the tracing
family arms off because its armed posture is never free (KDD-OB9);
the correlation family arms off for the third reason, the one only
this family has: an armed boundary over a disarmed tracer is work
nobody can observe, and the boundary cannot check the tracer's arm
without breaking the interchangeability the tracing core is built
on -- the void span is the same answer either way, and that is the
point. The echo is off for the disclosure's own sake (KDD-OB1's
posture replayed): a response `traceparent` tells the client the
server traces and hands it the server span's identity, and a
disclosure knob's safe default is the one that discloses nothing.
A deployment that wants request spans writes both arms explicitly
-- the boundary's and the engine's -- two decisions, two knobs,
neither taken on the host's behalf.

**KDD-OB16 -- The echo refuses the void context.** The reference
gated its echo on `isEnabled()`; the port's boundary does not know
the tracer's arm (KDD-OB15), so the gate is the identity's own
validity: `SpanContext::isValid()` was written into R2 for exactly
this consumer ("every consumer that would propagate it must refuse
it here first"), and the echo is that consumer. A disarmed tracer's
void span must never stamp the all-zero `traceparent` on a response
-- the W3C grammar would carry it, and a client would correlate
against an identity that identifies nothing. The refusal is
observable and pinned: the echo armed, the tracer disarmed, the
response carries no header.

**KDD-OB17 -- Inbound is untrusted, and the refusal is null --
across both pairs.** The extraction answers null for a wrong
version, a short or long line, a non-hex field, a flag byte outside
the sampled bit, the all-zero pair, an oversized companion, and a
context the constructor's bounds would refuse (the one catch in the
lane, commented by name -- the empty catch is a refusal, not an
oversight). Null is the answer because "no parent present" and
"parent present but malformed" lead to the same honest behaviour
(a fresh trace is minted either way), and a client's malformed
header must never become the callee's exception.

**KDD-OB18 -- The duration attribute is dropped: the span's own
timeline owns the measurement.** The reference's epilogue computed
`hrtime() - start` inline and recorded it as an attribute -- a
second clock pair and a division duplicating what the span's
`SpanTimeline` already measures (KDD-OB11's whole discipline). The
port records the deviation instead of the duplication: the frozen
`SpanData` answers the duration through its own timeline, one
measurement in one named place, and the request span's record is
not stamped with a copy of its own measurement.

**KDD-OB19 -- The request span's lifecycle belongs to the
middleware, and the middleware belongs to the composition lane.**
The reference's telemetry prologue and epilogue lived inline in
`Application::handle`; the port moves them into the PSR-15 family
boundary the stack composes (the same restructure the timing family
gave the inline measurement). The one structural consequence: this
middleware lives in the Middleware layer, not the Http layer every
other family's middleware lives in, because its every request
answers through the tracing engine and the Http layer stays
engine-free exactly as the tracing core stays config-blind
(KDD-OB8's one-way direction, kept symmetric). The factory is the
campaign's first to spell a second service argument -- the tracing
family's own tracer, not a copy minted here, so the boundary's
spans drain through the one processor the deployment configured.

## Legacy attribution

The reference implementation is the GitLab snapshot `zeflous/zef`
branch `zef-main` (`ZEF Framework v2.5.0-beta1`, the hexagonal
refactor of the monolith `zef_framework_v2.7.0.php`):
`Middleware/TimingMiddleware.php` (the ZEF-DX-08 posture and the
sprintf shape, move-verbatim in the port's own classes). The port
deviations -- the inverted emission default (KDD-OB1), the
conditional measurement (KDD-OB2), the value-object render with the
clamp and the dropped redundant round (KDD-OB3), the injectable clock
seam (KDD-OB4) -- follow this repository's own family discipline
(the CSRF/auth lineages), not the reference's demo shape.

The correlation family's reference is the same snapshot's
`Framework/Observability` correlation lane (`CorrelationContext`,
`CorrelationHeaders`, `CorrelationPropagator`,
`CorrelationContextCarrierInterface`/`ExplicitCorrelationContextCarrier`,
`TraceContext`, `TraceContextPropagator`) and `Framework/Application::handle`'s
telemetry prologue and epilogue (the header reads, the parent
extraction, the request-span attributes, the response recording,
the failure recording, the finally-end, the gated echo): the twelve
bounds, the W3C grammars, the tolerant extraction order, the digest
redaction and the lifecycle shape all move verbatim in the family's
own classes. The port deviations -- the attribute table as its own
redacting unit, the dropped duration attribute (KDD-OB18), the
void-context echo refusal (KDD-OB16), the double fail-closed knobs
(KDD-OB15), the middleware's home in the composition lane
(KDD-OB19), the carrier interface deferred until a consumer lane
lands (the same sequencing the in-memory exporter's facade consumer
took) -- follow this repository's own family discipline, not the
reference's monolithic arrival order.

The tracing core's reference is the same snapshot's
`Domain/Observability` hexagon (`SpanContext`, `SpanData`,
`SpanInterface`, `TracerInterface`, `SpanExporterInterface`) and
`Application/Observability` lane (`Span`, `Tracer`, `NoopSpan`,
`NoopTracer`, `BatchSpanProcessor`, `InMemorySpanExporter`,
`TelemetryClock`): the W3C grammar and the `traceparent` render, the
record's shape and the duration render, the status vocabulary, the
identity minting and the parent-TraceId inheritance, the bounded
queue and the batch splice all move verbatim. The port deviations
-- the config-blind `Telemetry` layer behind the family's own
config face (KDD-OB8), the inverted collection default (KDD-OB9),
the one clock seam owning both domains with the span owning its
whole timeline (KDD-OB10), the observable clamp discipline
(KDD-OB11), the one-attempt delivery posture without the
environment-shaped retry policy (KDD-OB12), the verbatim recording
until the hygiene lane lands (KDD-OB13) -- follow this repository's
own campaign sequencing, not the reference's monolithic arrival
order.
