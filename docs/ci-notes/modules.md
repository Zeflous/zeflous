# The Module Integration System — KDD

**Scope:** the roadmap's "Module Integration System (Hexagonal
Plug-n-Play)" Fase 1 -- the core module engine the priority matrix calls
High (the `ModuleInterface` contract, the two-phase boot, the manifest
declaration) and Medium (the dependency resolution, the configuration
namespacing): the contract under `src/Zef/Framework/Modules/`, the
composition machinery at the Kernel lane (`ModuleBootstrapper`,
`ModuleRoster`, `ModuleResolver`, `ModuleManifestLoader`,
`ModuleContextFactory`, `ModuleClaims`, `ContainerModuleRegistrar`), the
`modules` knob declared in the shipped default profile, deptrac gaining
the `Modules` layer and `Kernel += Modules`, branch `feat/modules-core`.

**Attribution:** the ROADMAP's own sketch (the self-registering module,
the `module.php` manifest, the registry that reads the module list from
config, validates dependencies with a topological sort and drives
register-all-then-boot-all) and the reference repository's provider
posture (`Zef\Module\*` ConfigProviders composed by the bootstrap's
`addProvider` calls) -- the sketch's shape is kept, the reference's
composition root is kept as the host's declarative definitions, and both
are adapted to this tree's disciplines below; the Fase 2 extension
points, the Fase 3 governance and the Fase 4 DX are deliberately not
mixed into this one.

**Verified locally before the PR:** `ci:static` EXIT-0 (6460 tests /
15387 assertions, +131 over the base; smoke 2760B/20000 iterations;
workflow concurrency 23; roadmap:gate pass; audit 0 advisories) · psalm
EXIT-0 (the baseline grew only the four module-authored-payload
MixedAssignment entries the lane's grammars read by design; the
`ModuleContext`/`ModuleRegistrar` surfaces carry the `@api` mark the
Kernel's own config accessor precedent carries) · deptrac 0 violations
over the two widenings · PHPStan max + ratchet clean with no baseline
file · the full battery re-run to EXIT-0 after the settle rounds the
entries below record.

## The map

| Unit | Question it owns |
|---|---|
| `ModuleInterface` | the ONE contract a module author implements: `metadata()`, `register(ModuleRegistrar)`, `boot(ModuleContext)` |
| `ModuleRegistrar` | the PSR-11 read side narrowed by the one declaration verb -- `set()` -- the lifetime deliberately absent (the container's own singleton default) |
| `ModuleMetadata` | the validated identity: name, version, requires |
| `ModuleName` / `ModuleVersion` / `ModuleRequires` | the grammars: two `/`-joined lowercase segments with the 32-byte ceiling, the constraint spellings, the once-only non-self list |
| `ModuleException` + `ModuleExceptionSpelling` | the lane's typed family over the framework root and the shared refusal sentences |
| `ModuleDeclaration` | the normalized unit: class spelling, identity, the schema path the discovery fold resolved |
| `ModuleManifest` | the `module.php` payload through the configuration layer's own `ConfigSchema` -- five fields, unknown keys refused with the known list |
| `ModuleEntries` | the knob's whitelist grammar and the two entry spellings' classification (namespace separator = class, rooted `.php` = manifest) |
| `ModuleOrder` | the Kahn walk with the declaration-order tiebreak; duplicate, unknown-requirement and cycle refusals, the cycle spelled as the chain the walk closed |
| `ModuleContext` | the module's readonly window: identity plus its own namespaced, schema-validated block -- the extension-point carrier the later slices grow |
| `ModuleBootstrapper` | the registration phase: knob -> discovery -> order -> register, the roster answered |
| `ModuleRoster` | the carry between the phases; the boot phase; the boots-once guard |
| `ModuleResolver` | the discovery fold: both spellings to one answer, the identity agreement check, the schema path's resolution |
| `ModuleManifestLoader` | the module side's file loader: exists, loads, returns an array -- named in every refusal |
| `ModuleContextFactory` | the context assembler: the block derived, rebuilt cell-by-cell, asserted, wrapped |
| `ContainerModuleRegistrar` | the port's container-backed answer with the three claim refusals |
| `ModuleClaims` | the registration phase's shared claim ledger |

## KDD-MO1: the port-first split the tree's lanes already spell

The engine's vocabulary lives in the lane (`src/Zef/Framework/Modules/`),
its composition at the Kernel lane -- the same hexagonal direction the
database core planted. The container stays behind the `ModuleRegistrar`
port: the lane never imports `Container`, the composition answers the
port over it. The one widening is `Config` -- the manifest and the
module-configuration schemas reuse the configuration layer's own schema
engine exactly as `ConfigSchema`'s docblock anticipated ("a future
module-configuration schema reuses the engine without growing new
validation code"), and the context assembler wraps the configuration
verdicts with the module named first. The failures are the lane's whole
story, so the family is the `ConfigException` posture (one typed root
over the framework's), not the Event lane's pure-SPL one.

## KDD-MO2: the sketch's override sentence, honestly refused

The sketch's two-phase reading says "this guarantees any binding can be
overridden before an instance is created". The tree's answer is a
refusal: an override's winner would depend on boot order -- exactly the
race the two-phase boot exists to close -- so `ContainerModuleRegistrar`
refuses a second claimant with the first named (the decorators knob is
the varying seam the tree already owns). The one direction an identifier
may flow is the host's: the registration phase runs BEFORE the
declarative definitions install, riding the reserved-services precedent
(`ContainerRegistrations` registers ahead "so a host's same-identifier
rebind wins") -- the host is the composition root, and a module's
service a host re-declares is the host's word final.

## KDD-MO3: the manifest through the schema engine

The `module.php` payload rides `ConfigSchema`'s field-rule map --
name/version/requires/class/config_schema, required and optional kinds
spelled, an unknown key refused with the known list -- and the kinds are
then refined by the engine's own grammars (the name and version through
`ModuleMetadata`, the class through the qualified-spelling refusal). The
engine grew no validation code of its own for the shape; the one
divergence from the sketch is deliberate: the sketch's `routes` and
`events` manifest keys are the Fase 2 extension points' consumption, and
a fail-closed grammar cannot accept-and-ignore -- they join when their
slices do.

## KDD-MO4: the identity agreement check

The manifest is the declarative spelling, the class's `metadata()` the
live one; when both exist the discovery fold demands they agree --
name, version and the requires LIST verbatim -- and refuses with both
identities spelled. Whichever the boot believed, half the record would
be lying. The class-string spelling carries no manifest, so its
identity is the live one by construction; the schema path is a
manifest-only concern, which is what makes KDD-MO6's first refusal
enforceable.

## KDD-MO5: the boot order is the feature

`register()` runs after the `framework.config` service exists and before
`ContainerBootstrapper` installs the definitions -- a configuration
definition may already depend on a module's registration (the
integration test pins a host `class` definition resolving through
`test.alpha.greeting`), and the definition graph's unknown-target
refusal would otherwise false-positive on every module identifier.
`boot()` runs after the definitions settle, before the process boundary
installs: every module's context is built then, each in the roster's
order. The AOT lanes are untouched by design: a module's registrations
are runtime closures called every boot (the module class is live code),
so both the artifact lane and the full lane see the same registrations,
and the artifact's digest stays a configuration digest. The empty knob
is the honest no-op: no entries, no discovery, no instances, an empty
roster.

## KDD-MO6: the namespacing rule as refusals, and the rebuild

The block lives at the dotted module name (`zef/payment` reads
`zef.payment` -- the grammar's single `/` maps injectively, so two
modules' blocks cannot collide) and nowhere else. Three refusals make
the roadmap's "every module provides a schema" enforceable: a block
without a declared schema is refused naming the module and the key; a
declared schema without a block asserts the empty block (a required
field refuses with the field named, the module named with it); a
list-shaped block is refused as the integer field it is. The block is
rebuilt cell-by-cell as it validates -- KDD-DB9's own doctrine ("the
declared return type is not a promise the analyser must trust but a
record it can check") -- so the context hands over a map the analyser
can check, and the integer-field refusal is a reachable arm with its own
exact message, not a silent shape assumption.

## KDD-MO7: the claim ledger is the boot's shared state

A module's claim flows into the container the moment it is made, so no
single registrar can tell "another module claimed this" from "the boot
already carries this" -- the first spelling of the policing carried a
per-registrar map and the second claimant's refusal lied (it named the
boot, not the first claimant). `ModuleClaims` is the one map every
registrar of the phase writes through; the refusal order -- reserved,
claimed, carried -- names the most specific truth each state holds.

## KDD-MO8: what this slice deliberately does not carry

The Fase 2 extension points (the route and event manifest keys and
their kernel consumption, the CLI registry, migrations per module), the
Fase 3 governance (sandboxing, health aggregation, per-module feature
flags, the audit trail), and the Fase 4 DX (the generator, the
`module:list`/`module:doctor` inspectors, hot reload) are each their own
slice, exactly the way the event lane phased its own. The
`ServiceProviderInterface` contract stays untouched -- the module
contract supersedes it as the registration unit, and its removal is a
separate decision; the `events` knob's reader lives with the event lane
as recorded (#662), and a module's own knobs arrive with their readers
in their own slices.
