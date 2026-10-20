# Container — Lazy proxies (Kampanye F continuation)

KDD for the lazy lane: the `lazy` key's declaration grammar, the native
proxy the resolution lane answers, the guarded construction that runs at
the proxy's first use, and the lane's boot and AOT behaviour. Roadmap
container area 7's proxy half (the decorated-services half landed as
#469; this lane closes the area). Campaign line: the port manifest's
group F, continued — the decoration lane (#469), the interception lane
(#475) and the namespace radix lane (#481) landed before it.

## Attribution

- **github v2.9** (`reference/zef-framework-src`, `ServiceDefinition`):
  the declaration key's own spelling — a `lazy` bool on the definition,
  carried unchanged through the decoration surgery's re-homed base
  (`DecoratorApplier` carries `$definition->lazy` into every layer it
  rebuilds). zeflous keeps the key's name and the surgery's carry rule;
  the flag itself is *not* kept (see divergence 1).
- **PHP 8.4** (`ReflectionClass::newLazyProxy()`): the mechanism,
  taken whole — the native lazy proxy is an instance of the declared
  class itself, created without its constructor, materialised by a
  factory closure at the first member access. This is the
  zero-dependency answer to the roadmap's "proxy lazy": no generated
  subclass, no in-repo codegen, no reflection-cache machinery. `final`
  and `readonly` classes defer as honestly as plain ones do, because
  the proxy *is* the class.
- **New design (no reference)**: the derivation grammar (the class
  shape's own class, the factory shape's declared return class), every
  refusal spelling, the runtime registry (`LazyIdentities`), the
  materialiser's non-object guard, the programmatic `markLazy()`
  surface, the baked AOT map and the artifact generation bump.

## L.1 — The key and the two shapes that carry it

The `lazy` key is the declaration grammar's deferral request, accepted
by exactly the two constructing shapes and refused by the two that
build nothing:

```php
'services' => [
    'app.engine' => ['class' => HeavyEngine::class, 'arguments' => [...], 'lazy' => true],
    'app.logger' => ['factory' => 'LoggerFactory::make', 'arguments' => [...], 'lazy' => true],
]
```

An alias refuses the key (an alias is transparent: it rides the
laziness of the definition it points to); a value refuses it (a value
is held, never built). The key itself is a strict boolean — an absent
key answers "not lazy", `false` spells the same answer explicitly, and
anything else is the declaration defect it is (`LazyKey`, refused with
the accepted spellings, never coerced).

**The class derivation** is the grammar's own load-bearing decision.
A native lazy proxy is an instance of exactly one class, so the
declaration must *name* that class at parse time:

- the class shape names its own class;
- the factory shape names the factory method's declared return class
  (`LazyReturns`) — the only honest source. No return type, a union,
  an intersection, a nullable, a builtin, or the unresolved
  `self`/`static` spellings all refuse with the way out in the
  message; a declared class that does not exist refuses the same way
  the class shape's own class would.

Whichever class the derivation answers is proven instantiable
(`LazyClasses`): an abstract class, an interface or an enum cannot
carry a proxy — there is no instance of it to forward to. A
request-lifetime declaration cannot defer at all: its materialisation
would run inside a scope the declaration cannot name
(`LazyRefusals::requestLifetime()`).

**Divergence 1 — the class, not the flag.** The reference carries a
plain `lazy` bool and never names the proxy class; the naming would
have to happen at materialisation, where PHP's own `newLazyProxy`
needs it anyway. zeflous moves the naming to parse time and stores the
class on the definition (`ServiceDefinition::$lazyClass`). The gain is
honesty at boot: every unnameable spelling is a *declaration* refusal
with the way out spelled, not a runtime surprise — the same
fail-closed posture the rest of the grammar keeps.

## L.2 — The registry and the resolution seam

The registration surface stays lazy-ignorant: the compiled factories
are the same eager closures they always were. The marks live in their
own runtime registry (`LazyIdentities`), written by the boot's
post-install step both lanes share (`ContainerRegistrations::
lazyMarks()`), consulted by the resolution lane on its miss path
(`ServiceResolutions`):

- an identifier the registry carries resolves to a native proxy whose
  materialiser runs **the guarded call itself** — the cycle guard, the
  interception onion and the listeners all fire at materialisation,
  exactly as they would have at resolution, just later;
- a transient answers a fresh proxy per resolution, each materialising
  its own instance; a singleton's memo holds the proxy (one
  materialisation behind it — the singleton's own economics preserved
  by the deferral);
- an identifier the registry does not carry resolves exactly as it
  always did: the empty registry leaves the fast path byte-identical
  (the `Interceptions` empty-registry posture, and the bench's
  memoised-parity subject pins it).

**Divergence 2 — one seatbelt.** The materialiser embeds the same
`guarded()` call the eager lane runs: the `CycleGuard`'s same-id
re-entry refusal and depth budget are the lazy construction's own
seatbelt. No separate deferred-depth budget, no second guard — a lazy
construction is a construction.

**The non-object refusal** is the materialiser's own (`materialised()`):
a factory that answers a non-object behind a lazy mark refuses with the
service named — a native lazy proxy stands in for an object. PHP itself
refuses an answer that is not an instance of the declared class at
materialisation; this guard refuses the cruder spelling earlier, with
the service's own identifier in the message.

**The re-registration rule**: a `set()` call forgets the mark together
with the memo (`ServiceStore::set()`). The eager surface re-registers
eagerly; the marking step is the boot's to repeat, not the store's to
guess at. The composition root's own deferral request —
`Container::markLazy($id, $class)` — is the programmatic spelling
beside the grammar's: the identifier must already carry a registration,
a request lifetime refuses, and a re-mark behind a different class is
the disagreement the registry refuses (`LazyRefusals`).

## L.3 — The surgery's carry, the baked map, the generation

**The decoration interplay** rides the reference's own carry rule: a
decorated target's deferral travels with the re-homed base
(`DecoratorRehoming` carries `$lazyClass` into the base it rebuilds).
The wrapper chain constructs around the base's proxy — the chain
answers while the base stays unconstructed, and the base materialises
at the chain's first use of it. The lazy map the compile lane marks
from is the *planned* set, post-surgery, so a decorated target's mark
lands on the `@decor:<id>:base` identifier.

**Divergence 3 — the AOT map bakes, it does not rebuild.** The
namespace tree rebuilds from the artifact's baked identifiers because
the walk is re-derivable; the lazy map is not: rebuilding it from the
*parsed* declarations would mark the original identifier instead,
proxying the outermost wrapper behind the base's class. The map bakes
into the artifact's envelope (`AotArtifact::lazy()`, written by
`AotLazies::all()` over the planned set) and the load lane marks from
the baked map after its install loop (`AotBootLane`).

**Divergence 4 — the generation bump.** The envelope grew a fifth key,
so every artifact written by an older zeflous is now shape-invalid on
load. Refusing a torn artifact is correct; crashing on a *well-formed
older-generation* artifact is not. The boundary's format version —
`AotArtifactEnvelope::GENERATION`, folded into every digest
(`AotDigest::ARTIFACT_GENERATION = 2`) — answers it: an older
artifact's digest differs *before* the envelope is read, the boot
recompiles and re-dumps under the new shape, and the strict five-key
contract never meets a four-key artifact. The strictness and the
generation travel together: a future shape change bumps both in the
same breath.

The digest already folds the declarations' `lazy` keys themselves (the
services source map carries the whole declaration), so a changed
deferral request is a proven-stale artifact the next boot — the
`LazyBootTest` digest subject pins it end to end.

## L.4 — The economics (LazyBootBench)

Not a victory claim. The honest cost structure the numbers pin:

| subject | plain | deferring |
| --- | --- | --- |
| boot (100 revs) | 109.5µs | 111.4µs |
| memoised `get()` | 0.580µs | 0.583µs |
| transient `get()` | 3.173µs | 1.334µs |

- the deferring boot pays the key's parse and the marking walk — once,
  at the boot (~2µs on this set);
- the memoised hot paths land in the same band (0.580 vs 0.583µs): a
  memoised `get()` never reaches the registry consult, so the
  registered answer pays nothing by construction;
- the transient pair is the lane's own trade made visible: the eager
  spelling constructs per resolution (3.17µs), the deferring one
  creates the native proxy per resolution (1.33µs) — construction now
  against proxy-creation now plus construction at the first use. The
  proxy is cheaper than the construction it defers, which is the whole
  point of deferring.

## L.5 — The ghost stub posture

The ghost-return fixture (`LazyReturnShapes::ghosted(): GhostEngine`)
declares a class no runtime file ever defines: the class-existence
refusal needs a real declaration to refuse, and psalm would flag the
unknown class before the test ever ran. The stub
(`stubs/lazy-ghost-engine.php`, registered in phpstan's `scanFiles`)
keeps the analysers honest while the runtime never carries the class —
the ext-redis stub's own posture, mirrored: a class the analysers see
and the runtime may not carry. Psalm needs no registration: its
project files are `src/` alone, and the fixture lives under `tests/`.

The absent-return fixture (`none()`) is the toolchain's own corner:
phpstan demands a declared return type, rector converts any `@return`
annotation into the native type, and phpcs demands the native type the
annotation implies — three demands whose satisfaction would destroy
the fixture's subject (reflection must answer *no* return type). The
resolution is the stream-wrapper fixtures' own posture, extended: the
signature is load-bearing, so each toolchain keeps a narrow,
justified skip for that one file (phpstan's `ignoreErrors` entry with
the prose-only docblock, phpcs' exclude-pattern on the
MissingNativeTypeHint sniff) and the docblock stays prose so rector
has nothing to convert.

## L.6 — Lessons (the saga rounds)

- **The attribution gate, third recurrence.** Every lazy lane line
  reported zero coverage while its message assertions passed green:
  `requireCoverageMetadata` restricts per-test collection to the
  `CoversClass` list, so a class exercised by a test that does not
  name it collects nothing (the interception lane's lane-arming and
  the DirectoryLoader escape classes were the first two recurrences).
  The honest fix is attribution, not new tests — and the boot-lane
  family (ServiceResolutions, ServiceStore, Container, the AOT
  install half) belongs in the boot test's list as much as the
  grammar family belongs in the definitions test's.
- **The analyser found the crash hole.** psalm's `class-string`
  narrowing on `ReflectionMethod`'s constructor was not pedantry: a
  lazy factory spelling whose target class does not exist (or whose
  method is undeclared) reached reflection *at parse time* and crashed
  with a raw `ReflectionException` — the eager spelling takes those
  refusals at the first resolution through `is_callable`
  (`FactoryInvocation`). The parse-time mirrors
  (`LazyTargets`) close the hole with the grammar's own refusal
  vocabulary; the service-identifier target form (the instance
  method) refuses with the target, because the service's class is not
  nameable at the parse.
- **The difficulty saga: extract, don't game.** Sixteen error-level
  findings greeted the first integrated shape (file difficulty up to
  40 against a 20 threshold). The Halstead difficulty the gate reads —
  (distinct operators / 2) × (total operands / distinct operands) —
  rewards vocabulary diversity and punishes repetition, so identifier
  tricks move the number without moving the design. What moved it
  honestly was redistribution: eight cohesive units (the deferral
  shell, the target mirrors, the return-type grammar, the marking
  step, the resolution rider, the materialiser, the class spelling,
  the artifact's lazy-map validation) each carry one concern, and
  every file lands under the threshold with the vocabulary that
  belongs to it. PHP's own edges surfaced too: readonly property
  defaults cannot take `new` in initializers (promoted constructor
  parameters can), so the store's registry keeps its constructor
  assignment.
- **The escape classes of the round.** (a) A redundant OR arm cannot
  be killed through its own spelling: `=== ''` is subsumed by
  `!class_exists('')`, so the flipped mutant still refuses — the kill
  rides the *valid* entry round-trip (the mutant refuses what the
  honest code accepts). (b) `ArrayOneItem` needs two entries in the
  map under assertion — one-entry maps are slice-stable by
  construction. (c) `SpreadOneItem` needs two items on *both* sides of
  the merge — a single original tag is indistinguishable from the
  slice. The tag-merge kill landed in the rebuild test, where the
  grammar's own value objects were already under assertion.
- **Infection 0.35's filter semantics.** The deprecated `--filter`
  accepts no alternation (it answers "no source file found"), and
  positional path arguments refuse globs ("multiple paths must be
  passed as separate arguments") — the changed-set run passes each
  file as its own positional argument
  (`scripts/../scripts/infection-changed.sh` keeps the spelling).
- **The environment's own flake, again.** `StreamTest::
  testTellThrowsWhenThePositionCannotBeDetermined` fails under
  instrumented full-suite runs (pcov + assertions) on this sandbox and
  passes in isolation — reproduced on `main` at 9308ee0 under the
  same flags, so the branch carries no blame; the CI runner does not
  reproduce it (every recent merge ran the coverage lane green). The
  same environment class as the DirectoryLoader readdir flake:
  recorded, not fixed here — the fix belongs to the stream's own
  campaign.
