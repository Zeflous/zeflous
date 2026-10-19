# Container — Decorators (Kampanye F continuation)

KDD for the decoration lane: the `decorators` knob's grammar, the
compile-time surgery that wraps a declared service in a chain of
wrappers, the tags that travel with the original identifier, and the
lane's boot and AOT behaviour. Roadmap container area 7's decorated
services half (the lazy-proxy half stays area 7's own next campaign).
Campaign line: the port manifest's group F, continued — the
compiler-passes core landed as #458; the group's decorator item is this
lane.

## Attribution

- **github v2.9** (`reference/zef-framework-src`, `DecoratorApplier`):
  the mechanism kept whole — decoration is *definition surgery at
  freeze time*: the target re-homed under a synthetic id, the wrappers
  registered as ordinary definitions referencing the next layer inward,
  first-registered outermost, a hard budget (128) on declared
  decorators. Adapted, not copied: the reference registers programmatic
  callables through `decorate()`; zeflous's grammar is declarative, so
  a decorator entry is a builder shape ('class' or 'factory') whose
  arguments spell the reserved `['service' => '@inner']` reference —
  the wrapper construction is the entry's shape instantiated with the
  inner reference rewritten, nothing else.
- **ZEF-DEEP-08** (the reference's own regression, `ContainerDecorateTagsTest`):
  the tags travel with the *original identifier*, never the re-homed
  base. Pre-fix, the reference re-homed the base *with* its tags, the
  tag index pointed consumers at the synthetic id, and `resolveAll()`
  silently answered the UNDECORATED inner instance. zeflous ports the
  fix as the surgery's construction rule: the outermost wrapper under
  the original id carries the declaration's tags; every inner layer
  carries none. The reference's PoC is ported as
  `DecoratorsTest::testTheTagLocatorAnswersTheDecoratedInstance` and
  the boot-level twin in `DecoratedBootTest`.
- **New design (no reference)**: the declarative entry grammar itself
  (the shape dispatch, the `@inner` reserved reference, the
  missing-inner refusal — a decorator that never sees the service it
  decorates is a rebinding, and the boot says so), the knob's parse
  under the `decorators.` label, the sixth digest source map, and the
  `@decor:` namespace reservation.

## D.1 — The knob and the entry grammar

The `decorators` configuration key maps targets to chains, first entry
outermost:

```php
'decorators' => ['logger' => [
    ['class' => CachedLogger::class, 'arguments' => [['service' => '@inner'], 300]],
    ['factory' => 'LoggerDecorators::timing', 'arguments' => [['service' => '@inner']]],
]]
```

The entry grammar is the definition grammar narrowed to what a wrapper
is: one shape key, an argument list, nothing else — a wrapper carries
no lifetime or tags of its own (every layer takes the decorated
declaration's lifetime; the tags travel with the identifier). The
arguments are validated by the shared reference-form grammar under the
honest label `decorators.<target>` (the knob path the entry arrived
by), with one reserved spelling added: `['service' => '@inner']` names
the layer the entry wraps, and at least one argument must spell it.

The grammar's units mirror the definition grammar's own split —
`DecoratorEntryParser` (the `ServiceDefinition::fromArray` role),
`DecoratorEntryShapes` (the `DefinitionShapes` dispatch role),
`DecoratorClassShape` (the `InstanceShape` class-proof role),
`DecoratorEntryKeys` (the `DefinitionKeys` vocabulary role),
`DecoratorEntryArguments` — because the archeology gate demands it and
because one split exists that a naive single class cannot survive (see
D.5).

## D.2 — The surgery

`Decorators::apply` runs fourth in the compile pipeline, between the
contextual apply and the graph validation — the ordering is load-bearing:
a contextual binding on a decorated *consumer* rewrites the consumer's
declaration before the decoration re-homes it (the base carries the
rewritten reference; the boot-level proof is
`DecoratedBootTest::testAContextuallyRewrittenDeclarationDecoratesThroughTheRewrite`),
and a decorator referencing another decorated target resolves that
target's outermost wrapper (the cross-target proof rides
`DualLogger`).

The surgery per target: the effective (last) declaration is located
(`DecoratorTarget`, the `ContextualConsumer` posture — a same-identifier
rebind wins, so the decoration applies to the declaration the container
would actually run), re-homed under `@decor:<id>:base` with its tags
stripped (`DecoratorRehoming`), and replaced in place by the outermost
wrapper; the between-layer wrappers are appended under
`@decor:<id>:<k>`, each an ordinary class or factory definition
referencing the next layer inward. The rewritten set's reference graph
contains real edges from the original identifier down to the base, so
the graph validation, the lifetime captures and the tag index all hold
unchanged — a walked self-cycle through a decorator that references its
own target is refused exactly as a direct self-reference would be.

All four shapes decorate: a class, a factory, an alias or a value —
decoration wraps whatever the identifier resolves. The original's
method calls ride along on the re-homed base.

## D.3 — The namespace, the budget, the digest

The lane's own spellings are reserved while the lane runs: a declared
identifier in the `@decor:` namespace is a boot refusal (the
`SyntheticValueGuard` posture — the namespace guard runs once, over the
set *before* any surgery, so the lane's own synthetic identifiers from
an earlier target are never mistaken for a squatted declaration; an
empty decoration map is the honest no-op that leaves a squatted
namespace unchallenged, pinned by test). The collision guard inside the
surgery (`DecoratorSet`, over `DecoratorSpellings`' vocabulary) protects
the per-application spellings against direct misuse.

The budget — 128 declared decorators across every target, the
reference's own overflow posture — fails the boot with a named overflow
instead of an unbounded compile.

The AOT digest folds the knob as its **sixth source map**: an artifact
dumped before the knob existed cannot serve a configuration that
declares decorators — the digest mismatch is *proven* staleness, the
bypass and re-dump the stale artifact never runs
(`DecoratedBootTest::testAChangedDecorationIsAProvenStaleArtifact`).
The artifact itself needs nothing new: the wrappers are ordinary
definitions, so the dump renders them and the artifact lane installs
the chain like any other set.

## D.4 — The economics (DecoratedBootBench)

The lane's price is paid once, at the boot: the entry parse, the
surgery, and the wrapper definitions the install loop compiles like any
other declaration — growing with the chain, so on the bench's
two-service fixture (where a two-decorator chain adds two definitions
to a set of two) the decorated boot lands well above the undecorated
band (75.2µs vs 40.1µs); on a real host's set the same absolute delta
drowns in the boot's own walk. The hot path pays nothing by
construction: a memoised `get()` on the wrapped chain is an ordinary
service reference resolving ordinary definitions — 0.510µs against a
plain service's 0.521µs, parity inside the noise band.

## D.5 — Lessons (the saga rounds)

- **The mutant hunt found a real bug the tests missed**: the
  spellings walk's first draft used `range(1, $count - 1)` — and PHP's
  `range()` *descends* when the start exceeds the end, so a chain of
  length one produced the layer ids `[1, 0]` instead of none. The
  surgery never observed it (it reads `layer()`/`innerOf()` directly,
  not the list), the collision guard merely checked two spellings too
  many — every behavioural test passed. The exact-list mutant kill
  exposed it; the fix is a `for` loop the boundary tests pin (lengths
  one, two, three).
- **The dependency-cycle detector counts const references**: the knob's
  walk referenced the budget const on the knob's own class while the
  knob referenced the walk — a two-class cycle the archeology gate
  refused. The const moved to the walk that enforces it. The C5
  parameters campaign met the same detector over `ParameterKeys`; the
  rule generalises: a vocabulary class may not reference the class that
  parses it, in either direction, words included.
- **The grammar's parser cannot live on the value object it answers**:
  the entry's shape parsers construct `DecoratorEntry` values, so an
  `DecoratorEntry::parse` entry point closes a three-class cycle
  (entry → class-shape → entry). The parse pipeline moved to its own
  `DecoratorEntryParser` — the value object stays a value object. The
  definition grammar avoids the same cycle only because its shapes
  construct *subclasses* whose parent owns the parse.
- **Archeology whack-a-mole is the unit-allocation discipline**: five
  rounds took the lane from three over-difficulty classes (43.03 /
  39.00 / 32.81) to zero, every round a split the LCOM score had
  already asked for ("LCOM=7 suggests 7+ responsibilities"). The
  winning allocations: the id arithmetic (`DecoratorLayers`), the
  reserved reference's whole life (`DecoratorInner`, the
  `ContextualReference` posture), the re-homing, the chain fold, the
  collision vocabulary — each its own class because the class-level
  metric aggregates every private with it.
- **phpcs and anonymous readonly classes do not agree**: the sandbox's
  PSR12 sniff miscounts the space after the `class` keyword in
  `new readonly class(...)`. A named fixture (`ShapelessDefinition`)
  reads better anyway and keeps the misuse guard testable.
