# Container — Compiler Passes & AOT (C6)

KDD for the compile/dump lane: the multi-pass pipeline, the AOT knob,
the dumped PHP artifact, the digest invalidation, the warmup
orchestration and the lane's introspection service. Roadmap container
area 8 completed. Campaign line: C6 compiler passes/AOT (the campaign
order locked in the port manifest: C1 autowiring, C2 scope/lifetime, C3
autoconfig/tags, C4 injector/contextual, C5 registration completion,
**C6 compiler passes/AOT**).

## Attribution

- **github v2.9** (`reference/zef-framework-src`): the
  `AutowireAotCompiler` idiom — the factory code embedded *as code*
  inside the artifact's array literal, so the single `require` at load
  evaluates it into a live `Closure` with no `eval`, no reflection and
  no machinery between the file and the store. Adapted, not copied:
  github compiles per-definition factories through `evalFactory` at
  compile time and embeds the result; zeflous renders the instance,
  alias and value shapes directly as `static fn` expressions
  (`AotClosures`), keeps the factory shape as *data* rebuilt once at
  load through the existing `FactoryClosures` builder, and refuses
  non-exportable literals instead of serialising them.
- **KDD-6** (`docs/ci-notes/config-system.md`, the configuration cache's
  envelope contract), mirrored and narrowed to what an *acceleration*
  may honestly do: atomic temporary-file-plus-rename writes, a torn
  write surfacing as a `ParseError` the load catches fail-closed, a
  digest deciding between load / first-dump / proven-stale, and
  "never rebuilt at runtime" as the staleness rule (a mismatch bypasses
  to the full compile and lays a fresh artifact down — it never runs
  stale code and never rebuilds in the request path).
- **New design (no reference)**: the pipeline formalisation
  (`ContainerCompilePipeline` + `ContainerCompilePlan` — the boot's
  own existing pass order made a named unit with one product), the
  unreferenced report (the honest form of unused-service removal under
  an all-public container), the type-tagged digest walk (`AotDigest`),
  the lane introspection service (`framework.aot` + `AotOutcome` +
  `AotLane`), the `SingletonWarmer` orchestration, and the thin-lane
  split below.
- **Deferred honestly, recorded so the manifest's intent is not
  silently lost**: (1) *unused-service removal* — the roadmap bullet
  assumes a private/public distinction zeflous's all-public `get()`
  contract does not have; every service stays addressable, so the lane
  *names* the unreferenced set in the outcome instead of dropping it;
  opt-in removal needs a roots grammar and is a decision that belongs
  to the host (and to area 9's extension surface), not to this lane.
  (2) *custom compiler-pass registration* — the roadmap's area 9
  bullet; the pipeline is the sequence artefact area 9 opens against,
  not the surface it guesses at. (3) *decorator/proxy* — area 7's own
  campaign.

## C6.1 — The pipeline as an artefact (multi-pass, the first bullet)

`ContainerCompilePipeline::compile()` is the order the boot has run
since the lanes that own it landed, formalised as one unit with one
product: the autowire compiler pass (reflection boot-only, the
name-keyed and `autowire` declarations rewritten to the positional
form, the `#[Value]` addresses materialised as synthetic value
services, the planned births appended), the autoconfiguration apply
(earned tags merged in), the contextual-binding apply (consumer
references rewritten through synthetic aliases), the reference graph
validation, and the tag-index walk as the named *collecting* stage.
Each pass stays its own tested unit — the pipeline adds the sequence
and the product, not a god-interface.

`ContainerCompilePlan` is that product: the planned set, the tag index
and the unreferenced report in one hand-off point with three consumers
(the install loop, the AOT dump, the tags locator's read side) — the
boundary that keeps both lanes honest: nothing reaches the container
without passing the validation, nothing gets dumped that did not pass
it.

## C6.2 — The knob, the seam, and the thin orchestrator

`aot` is a top-level configuration key (`['enabled' => bool, 'warm' =>
bool]`, both false in `FrameworkDefaults`, env-foldable like every
other knob). The seam is `?ContainerAotCache` on `ApplicationBootstrapper`'s
constructor — the `?ConfigCache` precedent: the artifact's *location*
is the environment's concern, the lane's *behaviour* is the
framework's. `aot.enabled` on with no seam wired is the boot refusal
naming both halves of the fix.

`ContainerBootstrapper` is deliberately thin (the `ConfigBootstrapper`
posture): it parses the five knobs into one `ContainerKnobs` hand-off
object, guards the seam, and chooses the lane. Each lane's real work
is its own unit:

- **`AotBootLane`** — the artifact lane's whole life: probe (load +
  digest compare), install (the baked tag index, the lane's own boot
  answer, the entries), warm, and the singleton-lifetime walk over the
  baked entries.
- **`ContainerCompileLane`** — the full lane: the pipeline, the two
  reserved registrations, the install loop, the warmup, and the fresh
  dump left for the next boot.
- **`ContainerRegistrations`** — the two reserved read-side services
  both lanes register ahead of the definitions (the tags locator under
  `ContainerServices::TAGS_SERVICE`, the boot answer under
  `ContainerServices::AOT_SERVICE` — the container layer's own id
  vocabulary, with `FrameworkDefaults` delegating its constants so
  both spellings answer the same identifier).
- **`ContainerKnobs`** — the parsed knob set plus the *lazy*
  `fingerprint()`: the digest folds only when a lane actually asks (the
  full lane runs for every boot; a configuration carrying live objects
  is legitimate there and must never pay the AOT lane's data-only
  demand unless the knob is on — the regression an eager digest
  introduced and the middleware integration tests caught, fixed by
  making the fingerprint a method instead of a parse-time value).
- **`ContainerSingletons`** — the warmup's input collector.

## C6.3 — The three honest answers

`bootstrap()` answers one of three lanes, each introspectable through
the reserved `framework.aot` service (`AotOutcome`: the lane, the note,
the definition count, the unreferenced report — the `framework.config`
precedent):

- **FullCompile** — the knob off, or on with no artifact yet. The
  reflection pipeline runs; with the knob on, the boot *also* lays the
  artifact down for the next boot.
- **ArtifactLoaded** — an artifact present whose digest matches the
  live configuration: its closures install through `Container::set()`
  (the same store, the same memoisation — not a parallel runtime), the
  tags locator answers from the baked index, and the parsed grammar of
  the five source maps still runs — the fail-closed validation holds
  whichever lane answers.
- **ArtifactBypassed** — an artifact present whose digest does *not*
  match: proven stale, never run. The full pipeline compiles afresh and
  lays a fresh artifact down. The artifact is an acceleration, never a
  source of truth.

## C6.4 — The artifact (PHP-dumper + inlining, bullets 4–6)

`AotEntries` renders the planned set one entry per definition, two
kinds at the boundary: `closure` entries (instance, alias, value)
carry rendered code — `AotClosures::instance()` spells the literal
`new \Class($c->get('dep'), 42, ['k' => 'v'])` (the arguments inlined
at their call positions, the runtime `ReflectionClass::newInstanceArgs()`
walk replaced by one opcode-level construction; a declaration carrying
`calls` delegates to `MethodCallInvocations::apply()` so the calls'
semantics stay single-sourced) — and `build` entries (factory) cross
as data, rebuilt once at load through `FactoryClosures::build()`. The
dump-side entry shapes are aliased (`@phpstan-type`/`@psalm-type`
`ArtifactDumpEntry` and friends, defined in `AotEntries` and imported
by the cache) — the shapes are too long for a single docblock line,
and the alias is also the one spelling both analysers share.

`ContainerAotCache::dump()` writes the plain PHP file — the array
literal with the closure code embedded verbatim, the baked tag index,
the baked unreferenced report, the digest — through the
temporary-file-plus-rename atomic write (random suffix, not PID alone).
`load()` is a plain `require` (not `require_once` — the artifact is a
side-effect-free data file a second boot in one process must keep
reading), and every load failure is fail-closed with the file named:
missing, non-map, torn (`ParseError` caught and re-refused), or
structurally malformed.

## C6.5 — The validation family (load side)

The load-side validation is a family of one-concern units, the shape
the definition grammar's own family taught the campaign:

- `AotArtifactEnvelope` — the four envelope keys exactly, the digest a
  non-empty string.
- `AotArtifactIds` — the identifier-list walk the unreferenced report
  and every tag's list share (the `$what` parameter carries the
  member's own description into the refusal).
- `AotArtifactEntries` — the per-entry walk, keyed by guarded
  identifiers.
- `AotArtifactEntry` — the kind dispatch (match on `kind`), the
  unknown-kind refusal before any member is read.
- `AotClosureEntry` / `AotBuildEntry` — the per-kind value objects,
  each its own grammar (`validated()` factory, the members checked
  into the typed form the moment they are read).
- `AotMembers` — the member readers (`string`, `list`) the build-kind
  entries read through, with the failing member named in the refusal.
- `AotArtifactCalls` — the build entry's calls walk.
- `AotLifetimes` — the lifetime member every kind shares, converted
  once.

The per-entry refusals are id-centric ("The artifact entry for service
'a' is malformed."); the envelope refusals are file-centric ("The
container AOT artifact '…' is malformed: …"). The split is deliberate:
the file names the artifact where the artifact *is* the defect, the
identifier names the entry where the entry is — and the de-filing is
what carried the per-entry units under the Halstead gate (C6.9).

## C6.6 — The digest (automatic invalidation, bullet 7)

`AotDigest::over()` folds the five source maps plus the framework
version into a SHA-256 over a canonical type-tagged walk: `1`, `'1'`,
`1.0` and `true` fold differently (types are named), key order is part
of the fingerprint, and an object, closure or resource anywhere
refuses the digest — object identity is not stable across the two
processes that must agree. The digest does not cover a class file
edited without a version bump: that is the build pipeline's concern,
exactly as KDD-6 frames it for the configuration cache. The
fingerprint is folded lazily through `ContainerKnobs::fingerprint()`
(C6.2's regression lesson).

## C6.7 — Warmup (the scope area's deferred orchestration)

`SingletonWarmer::warm()` eagerly constructs every singleton-lifetime
definition once, right after the install loop closes, on both lanes
(`aot.warm`, opt-in): the first request never pays the construction
costs, and a definition that cannot construct at all halts the boot it
belongs to instead of the first request it would have failed. The
scope is exactly the singleton lane — a request-lifetime definition
belongs to a scope the boot does not own, and a transient one is
defined by its fresh-per-resolution answer. A construction failure
propagates unwrapped: the failure the host must see is the one the
constructor already raised.

## Battery

- `AotMapTest` (11) — knob grammar: native-bool strict, unknown-key
  refusal, empty-map clearing, both toggles.
- `AotDigestTest` (10) — determinism, per-map sensitivity, type
  distinction, key-order sensitivity, non-data refusal.
- `AotClosuresTest` (15) — render per shape (structure, not
  whitespace), the calls wrapper, literal export family, non-exportable
  refusals with the way out.
- `ContainerAotCacheTest` (9) — round-trip, torn write, missing,
  directory, atomic-rename surface, the malformed-payload family.
- `ContainerAotBootTest` (13) — the lane battery over the rich set:
  default full-compile outcome and its unreferenced report; the
  enabled-without-seam refusal; first-boot-dumps / second-boot-loads;
  digest mismatch bypasses and re-dumps; the *equivalence* pin (both
  lanes answer identical objects for every shape: tags locator,
  `#[Value]` consumer, contextual rewrite, calls state, alias, value,
  factory, transient per-resolution); warm-on/off; the outcome service
  on all three lanes; the torn-artifact boot refusal.
- `TaggedServicesTest` — the `fromIdsByTag()` seam answers the walked
  index's own answers.
- `AotBootBench` — the boot economics and the memo-parity clause (below).

## C6.8 — Bench A/B (and the two first-draft bugs the numbers exposed)

Full-lane boot against artifact-lane boot over a twenty-six entry
fixture whose arguments are all explicit (no `autowire` declarations):

- full compile: **380.366 µs** (±0.71%)
- artifact load: **343.288 µs** (±0.77%) — about a tenth under
- memoised resolution: **0.510 µs** on both lanes (±0.78% / ±0.59%) —
  the parity clause: after either lane installs, a `get()` answer is
  the container's own memoisation lane, not a second runtime.

The honest reading: both lanes pay the same knob-grammar parse of the
five source maps (the fail-closed validation that must hold whichever
lane answers), and on an explicit-arguments fixture the reflection
surface the artifact skips is near zero — so the shared parse
dominates and the delta is the pass work plus the per-definition
closure compilation. The delta widens with every `autowire` declaration
and every entry the host declares.

Two first-draft bugs the bench itself exposed, both recorded as
lessons: (1) the artifact subject's configuration carried no `aot`
knob, so *both* subjects ran the full lane and landed in the same band
— an A/B bench must prove its fast lane is actually the fast lane
(assert the lane through the outcome service) before it pins numbers;
identical bands are a smell, not a result. (2) An early refactor made
the digest eager; the middleware integration tests caught it (a live
object in a service declaration is legitimate on the full lane) — the
fingerprint became lazy and the bench re-verified.

## C6.9 — Archeology saga (seven rounds, 12 → 0 error-level findings)

The lane landed with 12 error-level `Difficulty is too high` /
`Complexity is too high` findings across the validation family and the
orchestrator. What the seven rounds taught, each lesson now part of
the campaign's playbook:

1. **Halstead difficulty is `(unique operators / 2) × (operand
   instances / unique operands)`** — two levers, and the operand
   *ratio* is the one that bites: repeating the same half-dozen names
   (`$file`, `$id`, `$entry`) through every guard and refusal call
   drives the ratio to 5-8 while sibling classes sit at 2-3.
2. **Method extraction does not shrink the class aggregate** — the
   union of operator kinds stays, the operand sum stays; only *moving
   code into another class* (a real unit) or *removing repetition*
   cuts it. The C4 lesson ("helpers with many params keep the operand
   mass") generalises: extraction redistributes, delegation removes.
3. **The message context is operand mass** — carrying `($file, $id)`
   into every per-entry refusal doubled the vocabulary every unit
   repeated. De-filing the entry-level messages (the id names the
   entry; the file names the artifact in envelope-level refusals)
   halved the mass where it mattered most. Better messages and better
   metrics aligned.
4. **One-method units pass by construction** — `AotLifetimes`,
   `AotArtifactIds`, `ContainerSingletons`: a single concern with its
   own vocabulary cannot build a ratio. The multi-method family
   members that still failed had *methods sharing their entire operand
   vocabulary* (`AotMembers::string`/`list`): distinct operand names
   per method (or splitting the class) is what raises the union.
5. **`n1` (unique operators) is the multiplier nobody watches** —
   `ConfigBootstrapper` passes at operand ratio 5.1 because it uses six
   operator kinds; a validation walk uses twelve. Fewer *kinds* of
   guard idiom per class is worth real money.
6. **The thin orchestrator is not optional** — `bootstrap()` at
   difficulty 37 became a knob-parse + lane-choice of difficulty ~12
   only when the lanes became their own classes (`AotBootLane`,
   `ContainerCompileLane`). ConfigBootstrapper's shape was the
   reference to copy, not an aesthetic.
7. **Rector and the analysers disagree on spelling, and the gate
   decides** — rector converts FQ-string constants to `::class`
   (dropping the leading backslash the generated code spelled), renames
   parameters to match their types (breaking named-argument callers),
   and rewrites data providers to `provide*Cases` generators with
   `yield 'case name' =>`. The house accepts rector's conversions and
   the call sites follow — but the `@return Iterator<int<0, max>, …>`
   docblock it writes for generators is *not* the house form
   (`iterable<string, array{…}>` with named cases is), and an inline
   `/** @var */` it strips must be re-asserted as a standalone
   docblock. Verify every analyser after every rector pass.
8. **Long phpdoc shapes: alias, wrap, or die** — a `array{…}` union
   too long for one line can be wrapped as a multi-line annotation
   (phpstan parses it) *except* psalm chokes on multi-line `@return`
   unions — the shape that satisfies all three (phpstan, psalm,
   phpcs's 120-column line limit) is the `@phpstan-type`/`@psalm-type`
   alias defined once (multiline alias definitions parse fine in both)
   and imported where needed. `psalm-baseline.xml` regenerates with
   the code (the C5 precedent); its explanatory comment must be
   re-pasted after every `--set-baseline` because the regeneration
   drops it.

The final validation family's per-class difficulty band: 8-19, every
method individually under 14, the gate at zero.
