# Container — Interception (Kampanye F continuation, area 9)

KDD for the interception lane: the `interceptors` and `listeners`
knobs' grammar, the boot-time materialisation that arms the registry,
the onion and the construction events over the resolution seam, and
the lane's boot, warmup and AOT behaviour. Roadmap container area 9's
first lane (the port manifest's group F middleware and listener items;
the namespace radix family stays area 9's own next campaign).
Campaign line: the port manifest's group F, continued — the
decoration lane landed as #469; the group's middleware and listener
items are this lane.

## Attribution

- **github v2.9** (`reference/zef-framework-src`,
  `ServiceMiddlewarePipeline`): the mechanism kept whole — a
  priority-ordered onion around service *construction* (the cache-miss
  instantiation path alone, consistent with the events), higher
  priority outermost, ties in registration order, the terminal the
  guarded factory call, sealed at the freeze so the pipeline is
  deterministic, the container's own machinery ids (`@...`) bypassing
  the onion. Adapted, not copied: the reference registers programmatic
  callables through `addServiceMiddleware()` before its freeze;
  zeflous's grammar is declarative, so an interceptor entry is a
  builder shape ('class' or 'factory') whose arguments follow the
  ordinary reference grammar — the materialised object is boot
  machinery, never a container service, because resolving an
  interceptor through the container would run it through the very seam
  it arms.
- **github v2.9** (`ContainerEventListenerRegistry`, the v2.10
  resolving/resolved hooks): the two events kept whole — resolving
  fires when a construction actually starts (cache hits return early),
  resolved fires over the constructed instance with a non-null answer
  replacing it *before* the per-lifetime caching (the runtime
  decorator-style hook), each kind bounded at 32 listeners. Adapted,
  not copied: the reference registers programmatic callables through
  `onResolving()`/`onResolved()`; zeflous's listeners arrive through
  the `listeners` knob's two kinds, fired in declaration order (an
  entry spelling `priority` is refused — the firing order is the
  declaration order, the honest spelling a priority would blur).
- **New design (no reference)**: the declarative entry grammars (the
  strict integer priority gate, the listener kinds' two-key
  vocabulary), the materialisation lane (the entries' construction
  once per boot, their service-reference arguments resolved through
  the plain lane before the onion exists, the constructor-fit proof,
  the interface proofs), the arming order (after the install loop,
  before the warmup — so an eagerly warmed singleton rides the
  interception like any other construction), and the digest's seventh
  and eighth source maps.

## I.1 — The knobs and the entry grammars

Two configuration keys, one entry grammar each:

```php
'interceptors' => [
    ['class' => TimingInterceptor::class, 'arguments' => [...], 'priority' => 100],
    ['factory' => 'Hooks::interceptor', 'arguments' => [...]],
],
'listeners' => [
    'resolving' => [ ['class' => SlowQueryLog::class, 'arguments' => [...]] ],
    'resolved'  => [ ['factory' => 'Metrics::wrap', 'arguments' => [...]] ],
],
```

The entry grammar is the definition grammar narrowed to what an
interceptor or a listener is: one shape key, an argument list, and
(for the interceptor alone) an integer priority — absent is 0, the
declaration-order band. A boolean is not a priority and a numeric
string is not a priority; the gate is `is_int`, deliberately stricter
than the HTTP middleware stack's own `is_numeric` gate (a newer knob
under the container knobs' own discipline — the divergence is the
stricter spelling, never a weakened one).

The knob name is deliberate: `middleware` is the HTTP pipeline's own
root key (`middleware.stack`), and a container knob under that name
would collide with it in the merged configuration tree — the lane
takes the reference's own alternate name, *interceptors*.

The entry itself is never a `['service' => id]` reference: an
interceptor or listener resolved through the container would fire the
seam it arms (a resolving listener answering for its own construction,
an onion wrapping its own link), so the entries are builder shapes the
boot materialises directly, and their arguments' service references
resolve once, at the materialisation, through the plain lane — before
the interception exists.

## I.2 — The materialisation and the arming

`InterceptionMaterialisations` builds each entry through its own
shape: the class form through `ReflectionClass::newInstanceArgs()` over
the resolved arguments (the definition compiler's own instantiation
idiom, calls-free), the factory form through the factory lane's own
precedence — a target the container has registered resolves and runs
as an instance method, a class-named target runs statically — with
`FactoryInvocation` keeping the callable contract proof. The class
form carries its own constructor-fit proof
(`InterceptionClassFit`, the autowiring lane's `PositionalFit` rule
narrowed to the positional form): too many declared arguments are a
boot refusal (the engine's own `newInstanceArgs` would silently
swallow the surplus — a fail-open hole the proof closes), and stopping
before a required parameter names the parameter. The built object is
proven to implement the contract its knob demands — the middleware
stack's own discipline: a non-conforming entry fails the boot closed,
the entry and the contract named.

`Interceptions::arm()` runs once per boot, in both lanes, after the
install loop and before the warmup: the materialisation answers
through the plain lane (the registry does not exist yet — its lists
are assigned only after every entry materialised, so a failed
materialisation leaves the honest not-armed state on a boot that is
already dying), the sealed onion is built from the sorted chain, and
the once-per-boot seal refuses a second arming. What the boot armed is
what the process serves — the reference's freeze-time seal, by
construction: there is no public add path at all.

The arming precedes the warmup deliberately: an eagerly warmed
singleton is a construction, and a resolved listener's replacement
must land in the memo before the first `get()` reads it — a warmup
that bypassed the interception would freeze the un-replaced instance
into the memo and the listener would never fire for it.

## I.3 — The seam and the run order

The seam is `ServiceResolutions::guarded()` — the choke point all
three lifetime lanes ride (the singleton's answer-or-build seam, the
transient's every-resolution call, the request scope's answer). The
registry is the lanes' fifth collaborator, defaulting to the unarmed
registry: the fast path answers the guarded call the way it always
did, byte-identical mechanics, and the memo-hit path never reaches the
seam at all — the hot answer pays nothing by construction (the bench
pins the parity at noise level). The registry's own `run()` is the
armed set's run, nothing less: a direct run on an unarmed registry is
the honest refusal (`InterceptionRefusals::notArmed()`), not a silent
terminal passthrough — the fast path belongs to the caller's own
`armed()` check, and a registry without a set does not duplicate that
check one layer down, silently forgiving the caller that skipped it.

The run order keeps the reference's own: the resolving listeners fire
when the construction starts (before the onion), the onion wraps the
guarded factory call (an interceptor's frame sits *outside* the
cycle-guard frame, so re-entry detection is untouched), and the
resolved listeners fire over the answer — a non-null answer replacing
the instance before the per-lifetime memo records it, inside every
lane's build step. The onion alone reserves its bypass: the container's
own `@`-prefixed machinery identifiers (`@decor:`, `@contextual:`,
`@value:`) skip the onion — an interceptor wraps the host's services,
not the container's plumbing, so a decorated identifier's whole chain
is one construction rather than once per layer — while the listeners
observe every construction, machinery included: an observer sees what
happens.

## I.4 — The AOT behaviour and the digest

The artifact carries definitions (data); the interception carries
objects, materialised fresh from the live knobs every boot on both
lanes. The digest folds the two knobs as its seventh and eighth source
maps, so a changed interception set is a proven-stale artifact the boot
never runs — the same invalidation contract the decoration knob's
sixth map taught, and every artifact dumped before this lane is
proven stale by construction (the digest changed shape).

## I.5 — The economics (InterceptedBootBench)

| Subject | Mode |
| --- | --- |
| Plain boot (2 services) | 47.0μs |
| Intercepted boot (+1 link, +2 listeners) | 72.9μs |
| Memoised resolution, armed | 0.510μs |
| Memoised resolution, plain | 0.510μs |

Not a victory claim. The honest cost structure: an interception's
price is paid once, at the boot (the entry parse, the materialisation,
the seal — ~26μs on this two-service fixture, drowning in a real
host's own boot), the construction path pays the listeners and the
onion only on a cache miss, and the memoised hot answer pays nothing —
the parity is by construction, not by measurement.

## I.6 — Divergences from the reference (honest record)

- **The null-culprit diagnostics do not port.** The reference's
  construction throws when its middleware short-circuits with null or
  its factory answers null, and distinguishes the culprit. Zeflous's
  value shape legally carries null, so an interceptor's null
  short-circuit is a legal null instance, on the host's own record.
- **Interceptor and listener failures propagate raw.** The reference
  wraps them into its resolution-exception type; zeflous's resolution
  lane has never wrapped a factory's own failure (the host's exception
  propagates with the host's stack), and the interception lane keeps
  that discipline. A throwing resolved listener leaves the memo empty
  (the build step died before the record), so a later resolution
  honestly re-runs the construction and the listeners.
- **The resolving listener carries no dependency list.** The
  reference's listeners receive the declared dependency ids; zeflous's
  factories are opaque closures whose dependency edges live in the
  boot graph, not the resolution seam — the listener receives the
  identifier alone.
- **A replacing listener answers for every construction, the
  framework's own reserved services and the `@decor:` machinery
  included** — the reference's own semantics, kept whole. The typed
  seams a replacement breaks (a decorator wrapper's `LineLogger`
  parameter meeting a `ReplacedService`) fail loudly at the engine's
  own type check — the host's own record, the same class of risk the
  reference carries.
- **The priority gate is stricter than the sibling HTTP lane's.**
  `is_int`, not `is_numeric`: the container knobs' own discipline for
  a newer knob.

## I.7 — Lessons (the lane's own rounds)

- **The observation channel is the reference grammar itself.** The
  registry's arm() accepts only knob maps, so a test cannot inject a
  materialised instance — and that is the point: every test observes
  through a log *service* the entries reference (`['service' =>
  'test.log']`), the same channel a host's own interceptor would use.
  The public surface stays the only surface.
- **A replacing listener reshapes the assertions that read instances.**
  `get('framework.aot')` through an armed replacer answers the
  replacement, not the outcome — pinned in the artifact round-trip
  test as semantics (the framework's own reserved services ride the
  interception), with the observation form used where the assertions
  read the instances the replacement would re-shape.
- **The eager materialisation is observable at the boot level.** The
  interceptor's reference argument constructs its service during the
  arm — the plain lane, no listener fired for it — so the boot tape's
  silence over the log service's own construction is the proof, not a
  gap.
- **The armed()/seal distinction is load-bearing.** The registry's
  `armed()` answers the resolution lane's fast path (is anything
  registered?), while the internal seal flag owns the once-per-boot
  refusal — a registry armed with nothing is unarmed in every
  observable way but the second arming's refusal, and an empty-knob
  boot keeps byte-identical construction mechanics.
- **The unarmed run is a refusal, not a duplicated fast path.** The
  first draft answered the terminal itself when the registry carried
  no set — the caller's fast path copied one layer down, silently
  forgiving a caller that skipped its own `armed()` check. The crisper
  contract won: `run()` is the armed set's run, and a registry without
  a set refuses loudly — the same fail-closed posture as the request
  scope's no-open-scope refusal and the registry's own second-arming
  refusal. The unarmed state a failed arming honestly leaves behind
  is never a state to silently paper over.
- **A killing test that covers nothing kills nothing.** The first
  mutation round escaped exactly the two lane-arming call removals
  even though the warmup tests fail loudly with the calls gone — the
  `#[CoversClass]` metadata limits coverage attribution to the listed
  classes, and the lanes were missing from the boot test's list, so
  Infection never ran the killing tests for the lanes' mutants. The
  warmup semantics were pinned from the first draft; what was missing
  was the attribution, not the assertion. When a mutant escapes that
  "obviously" dies under a test, check whether the test covers the
  mutated file before writing a new one.
- **A `continue` over a deprecation-shaped witness guards inline
  instead.** The required-scan's `continue`-over-optional spelling
  carried an unkillable mutant: its divergent input is a constructor
  declaring an optional parameter before a required one — a shape the
  engine itself deprecates, and this suite fails on deprecations. The
  honest restructure throws at the required parameter directly
  (`if (!optional) throw`), the same verdict with no skip to mutate.
- **The value object's own defaults need their own witness.** The
  entry's `priority = 0` constructor default escaped both integer
  mutators: the parsed knobs always carry an explicit validated
  priority, so the default is only observable where a hand-built
  entry is materialised — the answer the materialisation unit gives
  for a hand-built entry's priority is the default's honest witness.
- **Two escapes were the environment's, not the branch's.** The full
  local run escaped two `DirectoryLoader` mutants (the dotfile skip's
  `continue`, the `ksort` removal) in files this lane never touched:
  both are filesystem-order-dependent (the fixture directory's
  readdir order puts the dotfile last locally, and the natural order
  happens to be sorted), so the local escape is determinism of this
  sandbox's filesystem, while the CI runner's order kills both — the
  full-set MSI 100 remains the CI lane's verdict, and the local
  changed-set run is the branch's own.
