# Container — Namespaces (Kampanye F continuation, area 9)

KDD for the namespace radix lane: the `namespaces` knob's grammar, the
sealed radix tree over the booted identifiers, the scope policy's
enforcement walk, the `framework.namespaces` read side, and the
fallback lane over the `get()` miss. Roadmap container area 9's second
lane (the port manifest's group F namespace item; the interception
lane landed as #475).
Campaign line: the port manifest's group F, continued — the
interception lane landed as #475; the namespace radix family is this
lane, and with it area 9's two lanes are complete.

## Attribution

- **github v2.9/v2.11** (`reference/zef-framework-src`,
  `NamespaceRadixTree`): the data structure kept whole — a
  path-compressed, sealed, query-only radix tree over the service
  identifiers' namespace segments; nodes carry `ids` (collapsed
  spelling => own spelling), children keyed by the first segment of
  the (possibly compound) edge label; single-child chains with no ids
  compress into compound edges at the seal; the builder owns the
  mutable phase, the product is born sealed. Adapted, not copied: the
  reference indexes class-name identifiers under one separator;
  zeflous's identifiers spell both conventions (`framework.config` and
  `App\Widget`), so the tree's first act is the collapse
  ({@see NamespaceSpellings}) — every comparison the layer makes runs
  on the collapsed form, and both spellings address one position.
- **github v2.9** (`RadixTreeNavigator`): the two query flavours kept
  whole — exact-identifier lookup and prefix enumeration over the one
  shared hop, disagreeing only on the mid-edge query. Adapted: the
  reference's hop answers carry constants the navigators never read;
  zeflous's hop spells the disagreement in the kind itself
  ('mid-match'/'mid-mismatch'), and the prefix descent answers the
  node or null — a mid-edge mismatch owns nothing beneath it, the same
  null a missing edge answers.
- **github v2.9** (`NamespaceScopePolicy`, `RadixTreeCompilerPass`):
  the enforcement kept whole — public/internal/module annotations with
  the subtree root included, internal hard-denying outside consumers,
  module targets spending a per-pair budget of distinct targets
  (`CrossScopeEdgeLedger`), the `@`-machinery consumers exempt, the
  walk running over the boot graph after the dependency graph is
  proven. Adapted: the reference tags modules manually; zeflous's
  annotations are the subtree's own position (namespace governance
  needing no module tagging).
- **github v2.9** (`NamespaceLayer`): the read side kept whole — the
  four questions (ids under, services under, scope of, stats)
  registered as the container's own reserved service the way the tags
  lane registers its locator.
- **github v2.9** (`NamespaceFallbackResolver`): the fallback lane
  kept whole — the longest covering prefix's factory consulted at the
  `get()` miss, `has()` consulting the coverage (the PSR-11 letter),
  a budget of 64 prefixes with re-registration replacing, and the
  construction riding the guarded seam (cycle guard and interception
  both wrap a fallback build — the reference's own audit finding,
  carried deliberately).

## N.1 — The knob and the grammars

One configuration key, two members:

```php
'namespaces' => [
    'scopes' => ['App\Internal\\' => 'internal', 'store' => 'module'],
    'max_cross_scope_refs' => 4,
],
```

The empty knob — absent, null, `[]`, or an empty `scopes` map — is the
ungoverned boot at every layer of the parse (the map, the member's
parser, the member's source unit), the answer no caller sees a
refusal for. The source unit folds the shape checks the reference
spells as separate guards (`!is_array || array_is_list` after the
empty-map early answer is folded into one guard with `!== []`), and
owns the key spellings: each `scopes` key is a string (a
canonical-numeric key arrives as an int, and the entries loop reads
keys as the prefixes they spell — the parameters lane's own map-walk
posture); the prefix's own grammar (empty, root, empty-segment
refusals) is {@see NamespacePrefixGrammar}'s; the kind's vocabulary is
{@see NamespaceScopeKinds}'s.

## N.2 — The tree family

The build pass ({@see NamespaceTree}, the reference's
`RadixTreeCompilerPass` adapted to the pipeline stage) walks the
planned set minus the `@`-machinery (`@decor:`, `@contextual:`,
`@value:` — internal rewiring, never the public namespace), inserts
each identifier through the immutable walk, and carries the parsed
annotations to the seal. The exemption is the skip of one entry, never
the end of the walk — a machinery identifier first in the plan and a
declared identifier after it indexes the declared one (pinned by its
own boot test).

The seal compresses bottom-up ({@see NamespaceTreeCompression}):
a non-root node with no ids and exactly one child is absorbed into it,
the edge label compounding (`Zef` + `Framework` into one edge). The
iteration order the build left is itself deterministic, and every
reader either walks the keys (order-free) or sorts what it collects —
the compression carries no ordering pass of its own.

Canonical-numeric segments (`app.carrier.0`, the tagged lane's own
bench shape) are stored by PHP as int array keys: the compression walk
reads the key back in its own spelling at its one key-to-label
boundary (`(string) $key`), and every other walk reads children by
string offset (PHP's array access coerces `'0'` to the int key, so the
lookups ride the same entry). The tagged-services bench found this the
hard way — a TypeError at the seal — and the tree's own tests pin the
shape: the segment boundaries keep `'1'` and `'10'` apart even though
both spellings coerce to int keys.

The measurements ({@see NamespaceRadixTree::stats()}) are pinned by an
exact-value test whose ratio carries a repeating decimal (eight raw
segments over three edges, 2.6667): the counters' defaults, the
accumulation arithmetic, the compression's merge condition, and the
rounding family are all dead the moment any of them drifts.

## N.3 — The scope enforcement

The walk ({@see NamespaceScopes}, over {@see DefinitionEdges}) runs in
the pipeline after the dependency graph is proven — the reference's
own order. Per edge ({@see NamespaceScopeEdges}): ungoverned and
public targets pass; the guarded subtree's own members pass; internal
targets refuse outside consumers; module targets spend the budget.

The pair grouping is the consumer's own guarded prefix => the target's
guarded prefix, and the budget counts DISTINCT targets per pair
({@see CrossScopeEdgeLedger}). The ledger's spend marker is the value
itself — `isNewEdge` reads the recorded value back, not the key's
existence — so the marker is live data, never a write-only boolean a
mutant could flip unnoticed.

The nearest-annotation question ({@see NamespaceTreeScopes}) rides the
identifier's own governing-prefix chain
({@see NamespaceSpellings::prefixesOf()}): every segment prefix of
the collapsed identifier, shortest first, each looked up in the
annotations map — the last registered entry the chain finds is the
longest one. The chain is strictly lengthening by construction, so the
nearest answer needs no length bookkeeping at all; the fallback
coverage question ({@see NamespaceCoverings}) rides the same chain for
the same reason.

## N.4 — The read side and the fallback lane

The locator is the reserved service `framework.namespaces`
({@see NamespacedServices}), registered ahead of the definitions —
the `framework.tags` precedent — with four answers: `idsUnder()`,
`servicesUnder()`, `scopeOf()`, `stats()`. A closure-registered
identifier never enters the tree (the declarative surface is what the
namespace layer governs), and a resolution failure under
`servicesUnder()` propagates unchanged — a namespaced service that
cannot build is a defect to surface, not a collection entry to skip.

The fallback lane ({@see NamespaceFallbacks}) is the composition
root's programmatic act — `Container::namespaceFallbacks()->fallback(
$prefix, $factory, $lifetime)` — never a declarative knob: a fallback
factory receives the identifier it is resolving, data the declarative
grammar cannot spell. `get()` consults the covering fallback at the
miss, `has()` consults the coverage, and an empty registry answers
nothing (the miss path's behaviour is byte-identical to the
no-registry boot). Singleton fallbacks memoise per identifier; a
re-registration replaces the factory and forgets the memo; the request
scope is the honest refusal (a fallback identifier is not a scope
member).

## N.5 — The AOT behaviour and the digest

The tree is never baked into the artifact: the artifact lane rebuilds
it from the baked entries' identifiers under the live knob the digest
proved equal ({@see ContainerRegistrations::artifactReadSides()}) —
O(segments) at the boot, the same tree by construction. The `namespaces`
knob rides the digest's ninth source map, so a changed policy is a
proven-stale artifact before the lane ever reads it; the default
profile's own knob (`['scopes' => [], 'max_cross_scope_refs' => 0]`)
is pinned by its own slice test the way the tags knob's is.

## N.6 — The economics (NamespacedBootBench)

The governed boot pays the knob's parse, the tree's build and seal,
and the scope walk over the graph's edges — once, at the boot (plain
100.4µs against governed 105.0µs on the lane's set, the honest cost
structure of governance). The registered hot path pays nothing by
construction: a memoised `get()` never reaches the miss path the
fallback lane consults, and the two resolution subjects land in the
same band (0.599µs against 0.600µs). The covered miss is the only
lane that pays the coverage walk (1.7µs), and a miss was never the hot
answer either.

## N.7 — Divergences from the reference (honest record)

1. **Dual separators, one collapse.** The reference indexes
   class-name identifiers under one separator; zeflous's identifiers
   spell both `.` and `\`, so the collapse is the layer's first act and
   both spellings address one position (a spelling collision at one
   position is the insert's own refusal).
2. **The tree is rebuilt, never baked.** The reference exports its
   frozen tree; zeflous's AOT lane rebuilds from the baked identifiers
   under the live knob — the digest guarantees the knob the artifact
   was dumped under is the knob the boot parsed, so the two trees are
   the same tree by construction, and the tree carries no export
   machinery of its own (no baker, no dead code).
3. **The fallback lane is programmatic.** The reference's resolver is
   constructible through configuration in some hosts; zeflous's
   registration is the composition root's own act, because a fallback
   factory needs the requested identifier — data the declarative
   grammar cannot spell.
4. **No subtree-capture guard, no separate depth budget on the
   fallback lane.** The reference wraps fallback resolution in its own
   subtree guard; zeflous's fallback construction rides the same
   CycleGuard frame (same-id re-entry and the depth budget) and the
   same interception set the registered lanes ride — one seatbelt, the
   posture the whole container keeps.
5. **The hop spells its disagreement in the kind.** The reference's
   hop arms carry constants the descents never read; zeflous's hop
   answers 'mid-match'/'mid-mismatch'/'miss'/'descend', and the prefix
   descent folds the mid-edge mismatch into the null a missing edge
   answers — no arm carries data no reader reads.
6. **The chain walks instead of length bookkeeping.** The reference's
   nearest-annotation and covering walks track the best length;
   zeflous's ride the identifier's own prefix chain (strictly
   lengthening by construction), which needs no bookkeeping — and
   cannot disagree with itself about what a prefix owns.
7. **The ledger's spend marker is data.** The reference's ledger is a
   set-idiom map; zeflous's `isNewEdge` reads the recorded value back,
   so the marker is live data rather than a write-only boolean.
8. **Numeric segments are the tree's own grammar.** The reference's
   identifiers never spell canonical-numeric segments; zeflous's may
   (`app.carrier.0`), and the compression's key-to-label boundary is
   the one place the int key's spelling is restored.

## N.8 — Lessons (the lane's own rounds)

- **The bench is a witness the suite cannot replace.** The
  numeric-segment TypeError never touched a test — every existing
  fixture spelled alphabetic segments — but three benches booted
  `app.carrier.0`…`9` on their first constructor. The shape is now the
  tree's own pinned grammar, and the discovery is the lesson: a bench
  that fails where the suite is green is telling you which edge the
  fixtures never spelled.
- **A guard whose removal answers the same is dead weight.** The two
  early guards (`containsExact('')`, `idsUnderPrefix('')`) were
  equivalent to their fall-through — the walk's miss path answers the
  same — and every mutant on a dead guard is an escape by definition.
  The honest fix is removal, not a killer test.
- **Dead constants feed mutants.** The hop's fourth element and the
  navigator's `descended` flag were write-only data — no reader, no
  observable difference, no kill. The restructure that deletes the
  surface beats the test that pins it.
- **The equivalence wall: when every observable coerces.** A
  `(string)` cast whose operand flows only into interpolation and
  array reads cannot be killed — PHP coerces identically. The way
  through is to move the check to where the type is the contract: the
  key-stringing refusal belongs to the source unit (the parameters
  lane's posture), and the prefix grammar narrows to string by that
  check's own truth.
- **Exact-value stats tests are cheap armor.** One test with a
  repeating-decimal ratio (8/3) killed fourteen mutants across the
  counters, the compression's merge condition, the recursion, and the
  rounding family at once — loose bounds (`assertLessThan`,
  `assertGreaterThan`) had left the whole measurement story mutable.
- **The pair collision is a string story.** The ledger's pair
  concat mutant dies only where two distinct pairs' flattened
  spellings collide ('a'.'bc' against 'ab'.'c'); the honest fixture
  spells the collision, and the budget's own refusal carries the
  witness.
- **The parser's ksort is the map's public order, the seal's ksort is
  the tree's.** Two sorts, two contracts: the map answers its callers
  sorted (the parse's own document), and the tree's annotations ride
  the seal's sort — neither replaces the other, and each needs its own
  attribution to stay pinned.
