# Container Roadmap Audit

**Scope:** the container audit's minor-findings round (issue #800) against
`main` @ `091e5c8` (2026-11-11), completing the #768–#789 audit wave's
container lane: the argument reference grammar's two-lane disagreement,
the `aot.warm` × lazy interaction, the autowiring probe's phantom reuse
bookkeeping, and the env-backed value parameter's missing compatibility
judgement. This is a findings round, not a claim that the Container
roadmap is complete.

## KDD-CA1 — the reference grammar's two lanes agree, and the literal escape owns its own spelling (issue #800, finding 1)

The validator and the resolver disagreed on the reference form.
`ArgumentReference` (the parse-time half) demanded the single-key
`['service' => identifier]` map and refused every multi-key carrier —
but `ArgumentResolution` (the runtime half) resolved *any* map whose
`service` key held a string, extra keys silently ignored, so a
hand-built `InstanceDefinition` that never met the validator resolved a
multi-key map the declarative lane refuses: two different answers for
one spelling, depending on which lane the definition travelled. The
loose runtime reading also left the grammar un-expressive: an array
literal that happens to be `['service' => 'x']` could never arrive as
DATA — every single-key map resolved as a reference.

Both halves now read one grammar. The reference is the single-key form
alone (`count === 1`, the identifier a non-empty string, re-narrowed at
the seam so a hand-built definition cannot smuggle a mistyped
identifier into `get()`); both reserved keys — `service` and the new
`literal` — own the whole map, so a carrier of either key beside other
keys is refused at the instantiation seam with the escape named in the
message instead of silently resolved. The literal escape
`['literal' => $value]` is the grammar's own answer to the
expressiveness half: the one way to hand the resolution a value whose
own shape is the reference form — `['literal' => ['service' => 'x']]`
answers the array `['service' => 'x']` itself, never the container's
answer for `x`. The AOT rendering lane (`AotArguments`) mirrors the
same grammar with its own refusal (`argumentGrammarCarrier`), so the
closure the artifact carries resolves its arguments identically to the
full lane's runtime walk — the mirror's own docblock contract, now
spelled by a shared refusal instead of a shared guess. The `calls`
argument walk rides the same resolution half, so the method-call lane
inherits the alignment without its own seam. The battery pins the
multi-key carrier refused in both lanes with the identical message
shape, the malformed single-key carriers (empty string, mistyped
identifier) refused at both the instantiation and rendering seams, the
literal escape answering its own value verbatim through a real
container resolution and through an eval'd artifact closure, and the
declaration-side validator's refusal for both reserved keys.

## KDD-CA2 — the warm × lazy interaction is recorded, not re-decided (issue #800, finding 2)

The audit's finding: `SingletonWarmer`'s own docblock promised "a
definition that cannot construct at all halts the boot it belongs to"
— but the compile lane marks lazy *before* it warms, so the warm step's
`get()` answered the native proxy alone, the factory never ran, and a
lazy singleton whose factory throws escaped the boot to explode at the
first request's first use. The promise did not read the lazy half of
the singleton lane.

The round's decision is the audit's own second arm — document the
interaction — and the reason is already in the tree: the
never-at-the-boot posture the silent-knob families planted
(`FrameworkDefaults`: `database.connection`, `database.schema` and the
migrator are deliberately lazy *so the boot's singleton warmer answers
the proxy without running the factory*; the events family's store is
transient for the same posture). Force-materialising lazy singletons
at warm would flip every silent knob's first-use refusal into a
boot-time crash for hosts that declared no database at all — a
recorded, pinned decision (`DatabaseWiringTest`, `EventStorageWiringTest`)
the audit round refuses to overturn from under two family lanes. A
first draft of this round did implement the force-materialisation
spelling (`Container::materialise()` over the resolution core), and the
full suite answered with exactly that regression — the draft was
reverted, the lesson recorded here.

What landed instead: the honest scope. `SingletonWarmer`'s class
docblock now spells that the halt-the-boot promise reads the EAGER
singleton lane alone, and that a lazy-marked singleton keeps the
deferral its declaration owns across the warm step — the memo holds the
proxy, the factory never runs, the never-at-the-boot families' silent
knobs stay silent across the warmest boot a host can ask for; a host
who wants the boot's own proof leaves the `lazy` key off the
declaration the warm knob then proves. `ContainerBootstrapper`'s boot
summary carries the same sentence. The battery pins the interaction
the docblock now spells: the warm step constructs the eager singleton
and keeps every lazy singleton deferred (marks intact, proxy answered,
first use materialising once); a lazy singleton whose factory fails
surfaces at the first use — never at the warm boot — with the
constructor's own message; and the loaded (artifact) lane's warm step
keeps the same deferral, the interaction riding both boots
identically. One engine subtlety the fixture design had to respect,
recorded for the next round: PHP 8.4's native lazy proxy materialises
only on members that touch instance state — a stateless method call
answers from the proxy shell without materialising (verified against
the engine), so the failing-factory fixture's entered member reads
the instance's own state.

## KDD-CA3 — the consultative probe is pure, the recording arm alone records (issue #800, finding 3)

`AutowireReuse::known()` answered a verdict AND wrote bookkeeping —
`recordReuse` on every consultation. The resolution arm
(`AutowireReferences::resolve()`) legitimately records, but the
consultative probe (`resolvable()`, the defaulted nullable's yield
rule) read the same method, so a defaulted parameter whose binding
never yields counted a reuse that never happened: the report's
`reused` column overcounted references the run never planned.

The three arms every verdict reads (a declared definition, a class
this run already birthed, a service the container itself carries) are
now their own private `answered()`. `known()` keeps the recording
spelling — the resolution arm alone, per its docblock contract;
`peek()` is the pure verdict with no bookkeeping written; and
`resolvable()` reads through `peek()`, the probe's own spelling, so
the two spellings cannot drift apart again. The battery pins the pure
probe answering the same verdict without recording, the defaulted
consultation counting no phantom reuse, and the bound defaulted
nullable counting exactly the one real reuse — the audit's own repro,
inverted into the column's honesty.

## KDD-CA4 — the env-shaped value refuses at the plan seam, naming the address (issue #800, finding 4)

`EnvSource` hands the parameters knob raw strings — its own docblock:
casting is the schema layer's job — but no layer ever judged the
`parameters` side, so `#[Value('db.port')]` into an int-typed
constructor parameter handed PHP the string at the injection seam,
and `strict_types` answered a `TypeError` naming the call site and
never the value's own source. The DX failure pointed at the wrong
line.

The parameters lane's posture stays what it always was — it coerces
nothing, ever — so the missing layer is a compatibility *judgement*,
not a cast: the plan now knows the parameter's declared builtin type
(`AutowireParameterSpec::builtinType` via
`AutowireParameterTypes::builtinTypeOf()`, null for untyped,
class-typed, union and intersection spellings) and the value
materialisation seam (`AutowireValueMaterialisations::ensure()`)
refuses a scalar whose runtime type disagrees with the declared
closed scalar set (int, float, bool, string), naming the owner, the
parameter, the address, the value's own type, and the way out (a
parameter provider that computes the typed value, or typing the
parameter as the string the source actually carries). Array,
iterable, mixed and untyped declarations keep their anything-goes
verdict — the value lane has typed nothing for them to disagree
with — and a null value stays the nullable family's own spelling.
The battery pins the audit's own repro (the env-shaped `'3306'`
string refused for the int-typed `db.port` with the full message),
the typed materialisation twin, the bool-typed shared flag, and the
array-valued parameter walking through untouched.

## The round's own gate lessons

The local static round before the push: rector demanded the
`$this->typeMismatched` spelling over `self::` (the instance-method
conversion), php-cs-fixer folded eight files' line widths, and phpstan
caught the test battery's unused `enter()` result (the
`method.resultUnused` rule — the call's result now feeds the failure
message, the suite's own idiom). Psalm's baseline ratchet ran twice:
`AutowireReuse::peek()` was born unused (the first spelling pointed
`resolvable()` straight at the private arms) — the probe's own
spelling now routes through it, one entry retired before it was ever
needed; the value lane's `MixedAssignment` (`ParameterValues::value()`
is mixed by design, the `TypeMapDecode` precedent) rides the baseline
as its own entry. The mutation round runs in CI over the round's
seams; any escaped mutant the gate reports answers in the branch
before the pin rides the head.

## The second gate round: the CI lanes' own answers

The push met five archeology difficulty findings and seven escaped
mutants, and the roadmap document's own count gate answered first --
the landing row's bookkeeping had counted the round in the Container
area but left the Summary total behind (275 against 276 summed), the
off-by-one the count rule exists to catch. The five difficulty
answers follow the family's own doctrine, each seam its own unit: the
AOT grammar wall became the archeology-sized pair the rendering
consults (`AotArgumentReference::target()` and
`AotArgumentEscape::value()`, the runtime twin's own naming -- one
grammar, two lanes, three units), the value lane's type judgment took
its own unit (`AutowireValueTypes::mismatched()`, the finding-4 seam
the materialisation consults), and the reuse query's arms read
through the declared index its own unit builds
(`AutowireDeclaredIndex::byId()`), the loop the other lanes spell
inline -- the query shed it, `known()`'s early shape spelled without
the negation, and the container arm keeps the comparison the boolean
chain demands (`=== true`, the SyntheticValueGuard precedent).
The escaped seams met their kills: the closed scalar set's three
untested arms (float, bool, string) each refused by their own
typed-parameter repro, the `is_scalar` guard pinned by the
non-scalar twin (an array-valued address materialises for a typed
scalar parameter -- the guard answers false before the set is ever
consulted), and the validation lane's literal arm -- every carrier the
runtime twin refuses, the parse half had never pinned -- by its own
multi-key carrier refusal and the verbatim escape pass-through at
`assertValid()`. The local battery re-verified the reshaped seams
hand-applied (seventeen kills, zero escapes) before the branch moved.

## The second gate round's own lesson: the covers-restriction invisibility, replayed

The SonarCloud coverage-on-new-code condition answered 72.2% against
the second round's push -- the four new units' lines measured
uncovered by a battery that demonstrably executes them. The cause was
the family's own oldest recorded lesson (#396's
covers-restriction invisibility, `requireCoverageMetadata="true"`):
coverage attributes only to the classes a test's `#[CoversClass]`
list names, and the round's four new units rode their owners' tests
unnamed -- `AotArgumentReference`/`AotArgumentEscape` under
`AotClosuresTest`'s list, `AutowireValueTypes`/`AutowireDeclaredIndex`
under `AutowireCompilerPassTest`'s. The units' lines were exercised
by every pin the round wrote; the report simply could not see them.
The four classes now ride their owners' lists, and the lesson is
re-recorded here because it was re-learned: a new unit's coverage
visibility is a fold of the extraction itself, not an afterthought
of the gate that reports it.
