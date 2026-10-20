# Container — The debug inventory (ROADMAP area 12)

KDD for the introspection half of area 12's developer-experience
item: the roadmap's own words ("debug mode kaya informasi: menyimpan
jejak bagaimana tiap service dibangun" -- a rich debug mode storing
the trace of how each service was built; "perintah CLI analisis:
melihat daftar service, dependensi, dan status tag"), answered as the
container's declarative recipe read side. Campaign line: the same
branch as the locator (`feat/container-lazy-locator-debug`), the
area-12 third.

## Attribution

- **ROADMAP area 12**: the requirement. The area's other items were
  already live: descriptive dependency-chain errors
  (`ServiceDefinitionGraph` reports the walked chain), circular
  detection (`CycleGuard` + the graph's boot proof), and availability
  introspection (`has()`).
- **github v2.9** (`reference/zef-framework-src`,
  `ServiceRegistryView`): studied and *diverged from* — the
  reference exposes the registry's raw maps (definitions, factories,
  aliases, deps, module-of, lifetime-of) as array-returning getters.
  zeflous's answer is a typed, boot-recorded inventory with a
  per-identifier entry value object, because the zeflous container's
  runtime does not retain the definitions (they compile to closures)
  and a raw-map view over the registry's Closures would answer
  nothing a developer can read. Kept: the read-only-view posture.
- **zeflous's own reserved-service pattern** (`framework.tags`,
  `framework.namespaces`, `framework.aot`): the shape of the surface —
  a read side registered ahead of the definitions so a host's
  same-identifier rebind wins, reached through the container's own
  public `get()`.
- **New design (no reference)**: the two-clock snapshot/live split,
  the `@`-machinery exclusion in the lazy view, and the entry's shape
  vocabulary.

## D.1 — What "how it was built" honestly means

The runtime container does not retain the declarative definitions —
the compile pipeline plans them, compiles them into `set()` closures,
and lets them go. Three designs were considered for the recipe read
side: retaining the definitions in a new runtime registry (a
`LazyIdentities`-pattern store, with a `set()`-forget rule and store
vocabulary to match); baking the recipe into the AOT artifact (an
envelope growth, a generation bump, for data the boot already holds);
or answering from the one place the declarations still exist on both
lanes — the boot's own parsed set (`ContainerKnobs::$definitions`),
captured by the reserved service's registration closure.

The third is the one that landed: `ContainerRegistrations::
debugLocator($container, $containerKnobs->definitions)` registers
`framework.inventory` on both lanes, the closure capturing the parsed
declarations as the inventory's own snapshot. The capture is free
(the array is alive for the whole boot either way), the AOT lane
holds the same parsed set the digest just re-proved equal (the
artifact accelerates the *install*, not the parse), and no envelope,
no generation, no new store surface.

**Divergence 1 — the recipe is the parsed declaration, not the
planned one.** The compile lane's *planned* set is post-pass
(autowire planning, contextual rewrites, decorator re-homing); the
inventory answers what the host *spelled*. A decorated target's entry
shows the original declaration — the wrapper chain the boot built
around it is machinery, and machinery stays out of host-facing
surfaces (the namespace tree's own exemption posture).

## D.2 — Two clocks, both honest

The entry value object (`InventoryEntry`) is built per read from the
snapshot's definition plus the live lazy mark:

- **The declaration half is frozen at boot** — a later `set()`
  re-registration changes the runtime's answer, not the declaration
  the boot recorded. The inventory documents the boot's own recipe;
  the store's live surface (`has()`/`get()`) answers the present.
- **The lazy half is live** — `ContainerInventory` holds the
  `LazyIdentities` reference the resolution lanes consult, read per
  entry, so a programmatic `markLazy()` and a `set()`'s forgetting
  are both reflected in the next read.

**Divergence 2 — no memo state.** Which singletons the memo currently
holds is deliberately not answered: the store carries no memo
accessor, adding one costs the store's hard-won vocabulary budget, and
the memo is an implementation detail whose debug value is already
covered by "did `get()` construct or answer" observable at the call
site. The recipe is the declarative truth; the runtime's caches stay
internal.

**Divergence 3 — the `@`-machinery filter lives in the view.**
`LazyIdentities::marked()` answers every mark, machinery identifiers
included (the registry stores; the read side chooses what to present):
`ContainerInventory::lazy()` filters `@`-prefixed keys, the re-homed
decorator bases staying invisible the way they stay invisible in every
host-facing surface.

## D.3 — The entry's shape

One entry per declared identifier (`InventoryEntries::of()`): the
shape name (`class` / `factory` / `alias` / `value`) and the shape's
own members (the class, the `'Target::method'` spelling, the alias
target, the held value), the lifetime key, the tags, the method-call
injections' names, the lazy mark, and the declared dependencies. The
dependencies ride the graph's own walk
(`DefinitionReferences::of()`) — the same `['service' => id]`
references the boot's acyclicity proof walked, single-sourced: the
inventory never re-derives what the graph already answers.

The CLI command the roadmap's DX item names is app-layer territory;
the container's contribution is this read side — the command, when it
comes, consumes `framework.inventory` through the same `get()` any
host uses.

## D.4 — Lessons

- **The snapshot was free because the boot already paid.** Both lanes
  hold the parsed set (`ContainerKnobs::$definitions`) for the digest
  regardless of which lane answers — capturing it in the reserved
  service's closure costs nothing but the closure's capture, and the
  AOT artifact never grows for data the boot keeps anyway. When a
  read side can answer from what the boot already holds, it should:
  the alternative (baking) is a boundary change, and boundary changes
  travel with generation bumps.
- **The live registry read kills two birds.** Holding the
  `LazyIdentities` reference (instead of snapshotting the marks at
  materialisation) is what makes the lazy half honest — and it is
  also what gave the facade's `lazyIdentities()` accessor its
  first in-src caller, closing the psalm `PossiblyUnusedMethod`
  finding the lazy lane had carried pending exactly this surface.
- **The shapes' members are not uniform.** `calls` exists only on the
  constructing shapes — the first inventory run warned on
  `AliasDefinition::$calls`; the builder now answers the shapes'
  own grammar (empty list for the shapes that build nothing) instead
  of reaching for a member the shape never had.
