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

The remaining families, in the order the reference's hexagon surfaces
them: the tracing core (the `TracerInterface`/`SpanInterface` family
with the W3C trace-context value objects), the correlation lane (the
`CorrelationContext`/`CorrelationHeaders` propagation pair), the
telemetry hygiene and OTLP export lane (the sanitizer, the JSON payload
encoder, the HTTP exporter with its retry policy), and the metrics and
health lane (the `MeterInterface`/`CounterMeter` pair, the Prometheus
renderer, the grouped health indicators over this repository's own
already-live `Health` seam).

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
