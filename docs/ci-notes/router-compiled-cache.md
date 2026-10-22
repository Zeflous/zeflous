# Router Compiled Tree Cache

**Scope:** the Router roadmap's "Performance & Production" phase, "Compiled
Cache File: Ekspor struktur pohon sebagai file PHP statis untuk booting
instan di produksi" — the engine-level cache slice following the runtime
facade (#512) and its roadmap pin (#519). Decision record for
`src/Zef/Framework/Config/Routing/RouteTreeCache.php`, its
`RoutingRuntime::fromTree()` / `Router::fromCache()` seams and
`benchmarks/RouteTreeCacheBench.php`.

## The artifact

A plain PHP file — `var_export()` output behind a `return` with a
generated-file header, the `ConfigCache` design language (#202, KDD-6) —
carrying three fields:

- `signature` — the sha256 of the route table the artifact was dumped
  from (the revision identity, below);
- `routes` — the raw Standard Route Schema entries in declaration order;
- `root` — the node graph: `['prefix' => string, 'static' => list<node>,
  'parameter' => node|null, 'routes' => list<int>]`, the integers indexing
  into `routes`.

## KDD-RC1 — the artifact carries raw entries, not normalised routes

`dump()` exports the raw schema entries and the built node graph together,
and `load()` replays every entry through `CompiledRoute::fromArray()` —
the engine's single front door — before hydrating the graph. The
alternative (exporting `CompiledRoute`'s normalised fields and anchored
constraints, hydrating without validation) would boot faster but needs a
second construction path on every engine value object, and a corrupted
artifact would then need its own error catalogue instead of the engine's
existing messages. The raw-entry replay keeps one validated construction
path, reuses the fail-closed net (`RouteEntryShape`, `RoutePath`,
`RouteConstraints`, `RouteHostBinding`), and the round-trip tests pin the
loaded tree against `RadixTree::compile()` so behaviour cannot drift.

## KDD-RC2 — the graph is trusted, the table is not

`load()` re-runs `RouteTableRegistry::register()` per entry, so a
hand-edited artifact that introduces a method claim collision, a host
ambiguity or a duplicate name is refused with the engine's own
registration messages. The node graph itself — prefixes, edges, route
references — is narrowed to the cache's format (exactly the four node
fields, string prefixes, list children, in-range integer route
references, non-empty static-child prefixes, no two static children under
one first character) and then replayed through `RadixNode`'s own builder
mutators; it is not re-derived from the entries. Re-deriving it would be
re-compiling, and the compile is exactly the cost the cache removes.
`RouteHostClaims`' host-precedence machinery is compile-time-only by
design and is not re-run: the graph it produced is the artifact.

## KDD-RC3 — mtime-free, revision-aware by tooling only

Aligned with the configuration cache's KDD-6: the runtime never stats
sources and never compares the signature (`load()` does not even read it
— editing it does not corrupt the artifact, pinned by test). The
signature is the roadmap's "Revision-Aware Compile" as an explicit
tooling act: the rebuild side computes `signatureOf()` over the freshly
loaded table, compares it with `signature()` (the artifact's own record),
and re-dumps on a mismatch. Comparing at runtime would read the sources
the cache exists to skip, which would make the fast path slower than the
path it replaces.

## KDD-RC4 — static children are exported as a list

`RadixNode` keys its static children by first character; the artifact
stores them as a plain list because the keys are derived on rehydration
(`appendStaticRun()` re-keys under the child's own first character).
Storing the keys would duplicate a derivable truth and invite a
corruption shape (key and prefix disagreeing) that no reader could
detect. The derivation is guarded: an empty static-child prefix or two
children sharing a first character fails closed — shapes the compiled
format never contains.

## KDD-RC5 — the facade seam

`RoutingRuntime::fromTree()` adopts an already-compiled tree as the
instance's one memoised compilation (the `tree()` identity is pinned by
test), and `Router::fromCache(RouteTreeCache)` is the facade's production
boot entry point — the audit's architecture decision ("expose missing
stable integration seams from the Router facade") applied to the cache.
The engine's only other touch is `RadixNode::staticChildren()`, a pure
read accessor for the export walk.

## Maintenance pass: the gate-settling redistribution

The first cut landed the whole cache as one 533-line `RouteTreeCache`
class; the architecture gate (`PhpCodeArcheology`, difficulty threshold
20) refused it at difficulty 42.27 / cyclomatic 36 — the operand
repetition of fourteen corruption messages sharing one `$file` vocabulary
is exactly what Halstead difficulty measures. The settle follows the
repo's established fold discipline (the #512 redistribution): the class
became the boot pipeline's public facade over four single-question folds,
with the public API byte-identical and every message unchanged —

- `RouteTreeCacheExport` (difficulty 4) — the dump side: the artifact's
  content and the node export, and the signature computation (the fold
  writes the signature into the artifact, so the revision identity is
  its vocabulary; the facade's `signatureOf()` delegates here, which
  also keeps the dependency one-directional — the first cut's
  facade↔export cycle was the gate's second refusal);
- `RouteTreeCacheArtifact` (difficulty 13) — the file-level read: is the
  artifact the cache's format, and what does it carry;
- `RouteTreeCacheNodeFields` (difficulty 14) — the node-format
  narrowing: is this exported value one of the cache's compiled nodes;
- `RouteTreeCacheHydration` (difficulty 12) — the replay: the registry
  re-run and the recursive graph rebuild through `RadixNode`'s own
  builder mutators.

Each fold carries its own `$file` for its own messages, so the operand
repetition that sank the monolith never accumulates in one class again.

## Measurements

`RouteTreeCacheBench` (15/300 numbered-section routes, the
`RoutingRuntimeBench` table convention): compile 193.7μs / 4,365.7μs,
cache load 177.3μs / 3,465.5μs, match through the loaded 300-route tree
7.1μs (flat, the memoised-runtime claim observed from the cache's boot
path). The load's saving is the tree build's share of compile (the node
splits, merges and insertions are never re-run); the entry validation is
deliberately replayed (KDD-RC1), which is why the saving is a fraction of
compile rather than all of it. A validation-free normalised export is a
possible future slice if boot economics ever demand it; the correctness
posture of this one is worth more than the remaining microseconds.

## Verification

`tests/Config/Routing/RouteTreeCacheTest.php`: the behaviour round trip
against `RadixTree::compile()` over a mixed table (root route, sibling
static children sharing a second character, compression chains,
parameter edges, exact/parameterised/fallback hosts, versioned variants,
`produces` negotiation, constraints, middleware, meta, multi-byte text),
sibling static text whose UTF-8 first bytes collide but whose first
characters differ (rehydration keys by character, the engine's own
vocabulary, not by byte), the name-index and URL-generator round trip,
dump determinism across compilations, the signature contract, and every
corruption shape failing closed (missing artifact, torn write, non-array
return, unknown/missing fields, non-string and empty signature, non-list
table, invalid entries, method and name collisions re-run through the
registry, non-array nodes, unknown/missing node fields, non-string
prefixes, non-list children and references, empty static prefixes,
first-character collisions, non-integer and out-of-range route
references, missing dump directory, non-list dump input, invalid dump
entry). `RoutingRuntimeTest` and `RouterTest` pin the two seams.
