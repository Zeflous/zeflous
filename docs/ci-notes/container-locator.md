# Container — The subset locator (ROADMAP area 10)

KDD for the service-locator half of area 10's standardisation item:
the roadmap's own words ("service locator: sub-container ringan
berisi subset service untuk konsumsi on-demand" -- a light
sub-container holding a subset of services for on-demand
consumption), answered as a read-only PSR-11 view over the booted
container. Campaign line: the lazy lane's own branch
(`feat/container-lazy-locator-debug`), the area-10 half of the three
areas the branch carries.

## Attribution

- **ROADMAP area 10** ("Standarisasi & Integrasi"): the requirement
  itself. The PSR-11 half of the area was already live
  (`src/Contracts/Psr/Container/ContainerInterface.php`,
  `Container implements ContainerInterface`); this lane is the
  locator half.
- **github v2.9** (`reference/zef-framework-src`,
  `TaggedServiceLocator`): studied and *diverged from* — the
  reference's locator resolves by TAG (an index over the registry's
  tag-bearing definitions), which zeflous already carries as its own
  `framework.tags` read side from the tags/autoconfig campaigns. The
  subset-by-identifier view the roadmap words actually ask for is a
  different thing: a *fixed membership* hand-off, not a query. Kept:
  the reference's fail-fast posture (resolution failures propagate
  unchanged) and its "resolve through the container" delegation shape.
- **New design (no reference)**: the fail-closed construction, the
  machinery exclusion, the PSR-11 boundary refusal vocabulary, and the
  `Container::locator(string ...$ids)` facade spelling.

## S.1 — A view, not a copy

The locator (its validating constructor, reached through
`Container::locator()`) holds the container and the subset's
identifier set -- nothing else. Resolution *delegates*: the same
lanes, the same memoisation (the singleton the locator answers is the
singleton the container keeps -- no second instances, no second
memo), the same lazy deferral (a marked identifier answers the native
proxy through the view exactly as it would straight from the
container), the same refusals an unrestricted `get()` would take.

What the view adds is the boundary, and the boundary is enforced, not
advisory: `get()` on an identifier outside the subset refuses through
the PSR-11 miss family (`NotFoundException`) with the subset's own
spelling, so a consumer holding the view cannot reach for services it
was not given. `has()` answers PSR-11's letter exactly -- whether
*this locator's* `get()` will refuse -- which is membership AND
carriage.

## S.2 — The fail-closed construction

Every ask is validated at construction, the grammar's own posture:

- an empty identifier is the declaration defect it is;
- an identifier the container cannot answer (`has()` false -- no
  registration, no covering fallback) refuses with the identifier
  named: a subset names what the container can already resolve, and a
  latent miss surfaces at the earliest moment the host can fix it;
- the boot's `@`-prefixed machinery identifiers refuse with the ask:
  the locator hands over services, not the machinery that builds them
  (the namespace tree's own exemption, the same rule at a different
  door).

A repeated identifier collapses into the one subset member it already
is: the subset has no multiplicity, and the first ask's position is
the order `ids()` answers. The subset is fixed at construction -- no
`with()` rebuilds, no growth: the minimal surface is the point (a
wider hand-off is a new locator, spelled where the host can see it).

## S.3 — The PSR-11 conformance

The locator implements the vendored `ContainerInterface` directly:
`get(): mixed` throws the container's own `NotFoundException`
(`NotFoundExceptionInterface` via `ContainerException`) on every
refusal -- the subset boundary rides the same family the miss path
rides, so a PSR-11 consumer's catch clauses work identically through
the view. `has(): bool` never throws.

## S.4 — The economics (LocatorBench)

Not a victory claim. The honest cost structure the two subjects pin:
a memoised `get()` through the view pays one membership check and one
delegated call over the container's own answer -- the same band by
construction, and the numbers hold it (0.58µs-class answers on both
subjects; the boundary's refused miss never reaches a resolution lane
at all).

## S.5 — Divergences

1. **View, not sub-container instance** — the roadmap's "sub-container
   ringan" is answered as a delegating view: a copy would fork the
   memoisation and answer *different* singletons -- the opposite of
   what a restricted hand-off promises.
2. **Fixed membership, no query surface** — the reference's
   tag-resolution locator shape (resolveAll/resolveOne/requireOne) is
   deliberately not carried: zeflous's `framework.tags` read side
   already answers tag queries; the subset locator answers membership
   restriction, which is a different question with a different
   surface.
3. **Machinery exclusion at the door** — `@`-prefixed identifiers
   refuse at construction rather than resolving: the boot's machinery
   is not the host's to hand around, and the namespace tree's
   exemption posture is applied at the locator's own boundary.
4. **The lazy interplay rides delegation** — no lazy-aware code in
   the locator at all: the container's resolution lanes already
   consult the mark registry, and a view that duplicated that
   consultation would drift from the lanes it delegates to.
