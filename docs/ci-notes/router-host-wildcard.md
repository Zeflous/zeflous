# Router Host Wildcard

**Scope:** the Router roadmap's host-grammar completion — the "`*` wildcard
syntax and full authority parsing" item the roadmap audit and
`ROADMAP-STATUS.md` carried as open after the runtime facade (#512/#519) and
the compiled tree cache (#522/#523). Decision record for
`src/Zef/Framework/Config/Routing/RouteHostLabelKinds.php` and the host folds
it serves.

## The grammar

A declared host pattern's labels are now exactly three kinds:

- a **literal** label — `api`, matching only itself;
- a **named wildcard** label — `{tenant}`, matching any single label and
  capturing it;
- an **anonymous wildcard** label — `*`, matching any single label and
  capturing nothing.

`*.example.com` therefore answers `api.example.com` and `shop.example.com`,
never `a.b.example.com` (one `*` claims exactly one label — the RFC 6125
wildcard reading, not a multi-level glob) and never `example.com`. A bare
`*` host answers any single-label hostname (`localhost`). Wildcards compose
with named parameters: `*.{tenant}.example.com` answers
`api.acme.example.com` capturing only `tenant`.

## KDD-HW1 — one label, one claim

The wildcard claims exactly one whole label, so the label-count guard in
`RouteHostParameters::parameters()` is untouched: a pattern and a request
host must still agree on label count before any label is judged. This keeps
the anonymous wildcard inside the machinery the named parameters already
run — no depth variable, no backtracking, no new match state — and it is why
`*.example.com` cannot answer a three-label-deep subdomain by construction
rather than by refusal.

## KDD-HW2 — one vocabulary owner

Four folds need a label-kind judgement (matching, precedence, selection,
rendering). `RouteHostLabelKinds` answers it — `isAnonymousWildcard()` and
`isWildcard()` — so no fold re-spells the `{`/`*` test itself and the kinds
cannot drift apart. The grammar produces the vocabulary; the folds consume
it.

## KDD-HW3 — one bucket, ordered by specificity

The first cut kept `RouteHostSelection`'s two buckets and added a shape
question — is any label of the declared host a wildcard — to route a
capture-less wildcard match away from the exact bucket. The mutation
adversary then proved that question observably redundant: a matching exact
host has specificity equal to its label count, and no co-matching pattern
can reach that (a pattern of the same label count spends at least one label
on a wildcard), so the specificity-ordered bucket already reproduces the
exact > pattern > fallback ordering on its own. Selection therefore keeps
ONE bucket ({@see RouteHostPatternBucket}) — every matched host claim
competes by specificity, an exact host arriving as the most specific
claim possible — and the host-agnostic routes stay the fallback. The
capture-count classification and its `isPattern` helper were deleted
rather than left as unobservable machinery. The regression that motivated
the look is still pinned: a capture-less `*.*.example.com` route loses to
`api.{tenant}.example.com` on `api.acme.example.com`, and still serves
`mail.acme.example.com` itself.

## KDD-HW4 — the ambiguity contract extends unchanged

`RouteHostPrecedence` counts a `*` label as non-literal for specificity and
as compatible for overlap, exactly like a `{...}` label. The consequences
follow the established contract: `*.example.com` beside `{tenant}.example.com`
overlaps at equal specificity and is refused at compile time (neither
outranks the other — an honest refusal, not a registration-order
resolution); `*.example.com` beside the exact `api.example.com` overlaps at
lower specificity and is admitted, with the exact host winning the match.

## KDD-HW5 — reverse routing refuses what it cannot name

`RouteHostRendering::render()` fails closed when the pattern carries a `*`
label: an anonymous wildcard has no name to bind a value to, so
`Router::absoluteUrl()` for a wildcard-host route is refused with the
pattern in the message rather than emitting a pattern-shaped
pseudo-hostname (`*.example.com` is not a hostname a client could resolve).
Named-parameter rendering is unchanged.

## KDD-HW6 — request hosts stay concrete

`RouteHostRequest::canonicalise()` treats a `*` spelling the way it treats
`{`: as a pattern, not a hostname — a null miss. A client sending
`Host: *.example.com` gets the not-found answer, never an error and never a
pattern-on-pattern match.

## KDD-HW7 — the claim identity is a tuple, not a string

`RouteMethodClaims` keyed a route's claim canonically as
`($host ?? '*') . '|' . ($version ?? '*') . '|' . $path`. Two defects came
with that spelling. A declared host of literally `*` collided with the
host-agnostic sentinel and was refused as a method duplicate — a false
conflict between two routes with different host semantics. And the
concatenated key is redundant machinery: the mutation adversary produced
an unkillable mutant (a trailing separator suffixes every key uniformly,
which no behaviour can observe), which is the repo's signal that the code
says two things where one suffices. The claims map is now the nested tuple
it always encoded — host => version => path => method — so no separator
grammar can alias two different tuples onto one string, and no concat
mutant has a target. Host-agnostic routes claim under the empty string,
which no canonical host can equal (the grammar refuses empty hosts); the
unversioned under `"*"`, which `RouteVersion` refuses as a version. The
bare-`*` route and the host-agnostic route on one path with overlapping
methods now coexist and stay separately selectable, and the host dimension
of the tuple is pinned: two routes on the same host and method claim
different paths without a false collision.

## The authority posture

"Full authority parsing" ends at an honest boundary rather than a widened
grammar:

- **Ports** stay refused in declared patterns. Matching is port-agnostic by
  design — `Middleware\RequestAuthority` already strips the port a client
  sends — so `api.example.com:8080` is a compile-time refusal with the
  reason in the message.
- **IPv4 literals** work by construction: every octet label is a valid DNS
  label, so a declared `192.0.2.1` matches exactly that request host. The
  grammar does not special-case it and does not need to.
- **IPv6 literals, userinfo and scheme spellings** fail closed at both
  faces: a declared `[::1]` or `https://api.example.com` is a compile-time
  refusal; a request host carrying them is a runtime miss.

## Verification

`tests/Config/Routing/RadixTreeTest.php` pins the grammar and the matching
semantics: whole-label wildcard matching (including case-insensitivity and
the one-label boundary), the bare `*` host against the agnostic fallback
(the sentinel regression), exact > wildcard > fallback precedence, the
specificity ordering that a capture-less wildcard must lose to a more
specific pattern, equal-specificity ambiguity refusal between wildcard and
named patterns, wildcard/named composition, the 405-not-fallthrough rule
across the exact/wildcard boundary, the request host spelled as a pattern
(`{...}` and `*`) answering a runtime miss, the same-host method claims on
different paths, the IPv4 literal, the refused port, and the refused
partial-label spellings (`a*.example.com`). `tests/Router/RouterTest.php` pins the facade surface —
wildcard matching through `Router::match()` and the `absoluteUrl()`
refusal. `tests/Config/Routing/RouteTreeCacheTest.php` carries a
wildcard-host entry through the dump/load round trip against
`RadixTree::compile()`, so the cache replays the extended grammar through
its existing registry re-run with no format change.
