# Container — Parameters (Registration Completion, C5)

KDD for the parameters family: the `parameters` knob, the `#[Value]`
attribute, parameter providers, and the environment closure. Roadmap
container area 1 completed. Campaign line: C5 registration
completion/env/provider (the campaign order locked in the port
manifest: C1 autowiring, C2 scope/lifetime, C3 autoconfig/tags, C4
injector/contextual, **C5 registration completion**, C6 compiler
passes/AOT).

## Attribution

- **github v2.9** (`reference/zef-framework-src`): the `#[Value]`
  attribute (grammar + TARGET_PARAMETER) and the
  `AutowireValueServices` model — the synthetic `@value:<key>` value
  service, memoised per address, graph-validated like any dependency.
  Adapted, not copied: github's AOT literal rendering
  (`renderLiteral`/`evalFactory`) belongs to the compile/dump area
  (roadmap area 8, C6); zeflous materialises plain
  `ValueDefinition`s through the existing grammar instead.
- **New design (no reference)**: the `parameters` knob itself
  (github reads the whole configuration tree; zeflous gives the
  parameters their own fail-closed namespace with a shared address
  grammar), the `parameters > providers` compile-phase computation
  lane, and the env closure (below).
- **Deferred honestly**: the service-provider machinery
  (`ProviderBroker`, deferrable/bootable providers) — the mining
  manifest had pencilled it into C5, but the roadmap's area 1 bullets
  (env values, parameter providers) name the *parameter* family, the
  zeflous `Contracts\ServiceProviderInterface` is already reserved for
  the Module System campaign, and the deferrable semantics need a
  mutable post-boot window the one-shot validated boot does not have.
  It lands with the module-integration campaign; the deferral is
  recorded here so the manifest's intent is not silently lost.

## C5.1 — The knob and the flat seam

`parameters` is a top-level configuration key beside `services`,
`tags` and `contextual` (declared empty in `FrameworkDefaults`, so the
framework ships no parameters of its own and a host's empty file boots
valid). `ParameterMap::parse()` walks the nested map into the flat
dot-addressed `ParameterValues` the compile pass reads — plain data
threaded through `AutowireCompilerPass::process()`, so the container
layer never learns the configuration layer exists (the deptrac build
order; the same reason-free seam the four-shape grammar uses).

The walk's honesty rules, each with a pinned test:

- a node is addressable iff it is non-empty and every key is a string;
  anything else is the parameter's whole value — a list is never a set
  of positions to address (`db.replicas.0` does not exist);
- an empty array is a value (the empty-list parameter), not a node
  that would silently vanish from the walk;
- every value is injectable (scalar, null, or an array of such,
  recursively) — the offender is *named* in the refusal
  (`ParameterInjectability::offender()` answers the first
  non-injectable value under the tree, so the message quotes
  `stdClass given`, not a bare `array given`);
- one address, one source: a literal and a provider on the same
  address is the boot refusal (the autoconfiguration lane's
  single-source discipline).

## C5.2 — The address grammar (one vocabulary, two spellings)

`ParameterKeys` owns the grammar: dot-separated segments of
`[A-Za-z0-9_-]{1,64}`, whole address at most 190 characters. The
underscores are deliberate — the environment fold keeps single
underscores inside their segment (`APP_SESSION_DRIVER` folds to
`session_driver`), so the environment-addressable parameters are
exactly the ones the grammar admits. The colon is deliberately absent:
the synthetic id is `@value:` + the address, and a colon in the
address would blur the prefix boundary.

The grammar serves both spellings — the `#[Value]` attribute's key and
the map's own keys — so the two can never disagree about what an
address is. **The grammar owns its own refusals** (the `ServiceTags`
precedent: `ParameterKeys::assert()` throws directly, quoting the
speller's context) — this breaks the `ParameterKeys ↔
ParameterRefusals` dependency cycle the first archeology round found
(C5.5 below).

## C5.3 — The value arm

A planned parameter carrying `#[Value('db.host')]` routes through the
new planner tier (between the variadic collection and the binding
arms): the address's value is materialised as the synthetic
`@value:db.host` `ValueDefinition`, memoised so every consumer of the
address shares one definition, and the plan entry is the ordinary
`['service' => id]` reference — zero new runtime machinery, the boot
graph validates the materialised value exactly like a hand-declared
dependency, and the hot path pays nothing (the value service resolves
as any value shape does).

The `@value:` prefix is the lane's own namespace (`SyntheticValueGuard`):
a declared definition or a container service already spelling such an
identifier is a collision refused with both spellings named (the
contextual lane's synthetic-namespace discipline), never a silent
shadowing of the attribute's intent. A missing address is the honest
refusal too — the attribute is an explicit binding intent, so the
constructor's default is *not* consulted while it is present; the
message names the way out instead ("declare it, or drop the attribute
so the parameter's own default or explicit spelling applies").

Spec-level guards (all pinned): `#[Value]` cannot combine with
`#[Inject]`/`#[Target]` (one parameter, one binding intent), cannot
ride a variadic (a collection is not one value), cannot ride a
class-typed parameter (a value is no instance) and cannot ride a
union/intersection parameter (a value names no single type).

## C5.4 — Parameter providers and the env closure

`parameters > providers` maps addresses to
`ParameterProviderInterface` class names — the compile-phase
computation lane (roadmap area 1, "nilai dihitung/di-resolve saat
fase kompilasi"). The reservation is total (the key always reads as
the machinery's own, like `tags > interfaces`); the declaration half
validates shape *before* any host code runs (class exists, implements
the interface, constructs with no arguments); the invocation half runs
each validated provider exactly once and checks the answer injectable
with the offender quoted.

The environment closure is **by construction**: the knob lives in the
layered configuration tree, and the environment source the kernel
already folds maps `APP_PARAMETERS__DB__HOST` onto
`parameters.db.host` — so a parameter takes its value from the running
process's environment without any container machinery of its own. The
e2e test proves the whole loop: EnvSource → LayeredConfigBuilder →
`#[Value]` sees the env-folded value beating the app layer.

## C5.5 — Archeology saga (five rounds, 13 → 0 error-level findings)

| Round | Findings | Move |
| --- | --- | --- |
| 1 | 13 → 7 | `ParameterKeys` self-throwing (breaks the Refusals↔Keys cycle, `ServiceTags` precedent); shared `ParameterInjectability::offender()` predicate (kills the duplicated element-walks in literals + providers); generic attribute reader in `AutowireAttributes` (three near-identical methods → one private `attribute()` with `@template T`); literals walk slims |
| 2 | 7 → 4 | `SyntheticValueGuard` extracted (prefix + occupancy); `ParameterProviderAnswers` extracted (invocation half); nullsafe ctor check |
| 3 | 4 → 0 | prefix const moves into the guard (breaks the AVS↔Guard cycle the round-2 extraction created); `AutowireValueMaterialisations` extracted (memo + definitions + report) |
| 4 | 0 → 2 (regression) | rector + the `@return class-string` re-shape pushed `ParameterProviderCheck` over (D 21.08 → 23.10 — removing an operator can *raise* Halstead difficulty when the operand-vocabulary ratio moves the wrong way) |
| 5 | 2 → 0 | `ParameterProviderConstruction` extracted (one question, one answer) |

Standing lessons reinforced: (a) the dependency-cycle findings catch
*const references* between vocabulary classes, not just method calls —
a grammar class owning its own messages is the cycle-free shape;
(b) small-class extraction is the difficulty lever that always works,
operator micro-tuning can backfire; (c) a `@template T of object`
generic on a reflection-reader keeps the three public readers typed
without instanceof narrowing.

## C5.6 — Covers-filter discipline (the C4 lesson, applied up front)

`requireCoverageMetadata` filters coverage to each test's
`#[CoversClass]` list — every deep-call collaborator of the new lanes
is on the list of a test that exercises it: `ParameterMapTest` and
`ContainerBootstrapperTest` carry the whole parameters chain
(map/values/literals/keys/providers/check/construction/answers/injectability),
the autowiring tests carry the value lane
(services/guard/materialisations/refusals/`Value`). Two session-local
traps worth recording: a bareword inside `#[CoversClass(...)]` is a
*constant* reference, not a class name — the attribute argument must
be `ClassName::class` (the failure mode is "Undefined constant" at
suite load); and an *interface* is not a valid coverage target (it has
no executable lines) — `ParameterProviderInterface` came off the
lists and the interface earns its coverage through its implementations.

## C5.7 — Report surface

`AutowireResult` gains the `values` column (the materialised
`@value:` identifiers, in materialisation order) beside `generated`
and `reused` — nothing is birthed or materialised off the record. The
column is additive with a constructor default, so existing consumers
are untouched.

## Battery

phpunit 3416 tests / 7027 assertions (+76/+139 over the C4 baseline);
stan max 0-error + ratchet (zero baseline); psalm L1 over the frozen
baseline (+ the family's reflection-invisible and mixed-walk entries,
same shape as the C1-era additions); PHP-CS-Fixer + PHPCS + Slevomat;
Rector; deptrac unchanged (Container stays Config-free — the seam is
the bootstrapper threading plain data); archeology 0 error-level;
coverage 99.25% (gate ≥90%); infection MSI 100 (changed-set re-run
after the covers lists settled — the C4.17 lesson); bench A/B below.

## C5.8 — Bench A/B (and the phantom-container lesson)

| Subject | main | C5 | verdict |
| --- | --- | --- | --- |
| Cold boot | 255.9μs | 262.8μs | +2.7%, once per process |
| Cached boot | 309.1μs | 329.0μs | +6.4%, once per process |
| Compile pass per boot | 46.0μs | 53.4μs | +16.1%, the lane's own surface |
| Memoised resolution | 0.515μs | 0.522μs | noise — the hot path pays nothing |
| Singleton memo hit | 0.520μs | 0.523μs | noise |

The compile-pass delta decomposes (micro-probes, 50k iterations):
the value lane's `open()` (guard scan + resets) ≈ 1μs, the per-spec
attribute read + tier guards ≈ 1μs, the result merge and the
`ParameterValues` default ≈ 0.3μs — every one a boot-once cost on the
lane's own surface, the same character as C4's accepted +5.8%/+6.2%.
**The lesson worth keeping**: the first cut paid a *phantom container*
— `AutowireValueServices`' constructor default initialised its guard
with `new Container()` (1.2μs of object graph allocated per pass
construction only to be discarded at the first `open()`), an invisible
+2.4% on the compile pass from one default argument. The guard's
container is now nullable (`SyntheticValueGuard` answers nothing
occupied until opened); the rule: **never default-construct a heavy
collaborator graph to satisfy an analyser — make the unopened state
cheap instead.**

The hot path is untouched by construction: a `#[Value]` plan entry is
the ordinary `['service' => id]` reference and the materialised value
service resolves as any value shape does — the memoised-resolution
bench (which resolves a planned dependency per request) confirms the
noise-level delta.

