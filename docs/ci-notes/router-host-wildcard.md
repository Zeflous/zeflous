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

## KDD-HW8 — the 405's diagnostics stop hiding the shadowed methods (#795)

The audit's sharp edge: `RouteHostSelection`'s either/or contract means a
claim that wins the specificity bucket owns the whole path at its host for
EVERY method -- the host-agnostic routes and the lower-specificity
co-matching claims for the same path are dropped from consideration
entirely. The dispatch half of that is correct and stays: `POST /x` behind
a host-agnostic claim is genuinely not dispatchable at an exact-claimed
host, so the 405's own `Allow` naming the winning claim's methods alone is
the honest spelling for the resource as dispatched (the mirror test
`testExactHostRouteTakesPrecedenceOverHostAgnosticFallback` pins it). What
the audit filed was the OTHER half's absence: the dropped claims' methods
were invisible everywhere -- an API consumer reading `Allow: GET` could
not tell a host-exact claim from a table that answers one method, the
`route:test` verb explained the 405 without the shadow, and the OpenAPI
servers list said nothing about the reachability rule.

The fix follows the issue's own union-with-host-marker direction, on the
diagnostic side only: `RouteHostShadowedMethods` answers the methods the
same path answers through the routes the host stage dropped, each entry
carrying the host claim that owns it (null: the host-agnostic claim), and
the explanation lane carries it -- `RouteMatchExplanation::shadowedMethods()`
on both spellings of the method refusal (the method answer's own and #794's
version-miss method judgement, whose allowed list is the host-selected
set's and therefore bites by the same shadow), `RouteMatchSummary`'s
bounded `shadowed=METHOD@HOST` token (`*` for the agnostic claim, each
entry clamped to the candidate budget), the debug header's dev line, and
the `route:test` text/JSON forms (the JSON field always present, spelled
as its own empty answer, so a consumer's schema stays stable). The
response's own `Allow` header deliberately does NOT carry the union: a
client that retried a shadowed method at the same host would meet the
405 again -- the header invites what dispatches, the diagnostic names
what hides.

The fold's judgment replays the selection's own predicates on the dropped
routes: agnostic claims answer every host; host-claiming routes answer
only request hosts their claim matches (`CompiledRoute::hostParameters()`
-- the same fold the bucket judged by, canonical request host and all); a
route whose claim does not answer the request host contributes nothing --
it is merely elsewhere, and naming it would pretend a method one host-hop
away belongs to this request's answer. The empty-selection guard answers
nothing: no claim won the path, no claim cast a shadow over it. The entry
map's key -- the method, a NUL separator, the owning host's own spelling
(the agnostic claim's empty spelling) -- owns both the deterministic
order (method ascending, then host ascending, the agnostic claim first
among its method's peers because a shorter key sorts before its own
extensions; no comparator, the `RouteMethodClaims` tuple lesson applied to
a sort key) and the duplicate fold (two routes behind one claim spelling
answer one entry). `RouteHostShadowedRoute` -- the per-route entry fold --
is its own unit because the archeology difficulty ceiling scores a class
carrying both the selection diff and the entry judgment above 20: the
`ForwardedProtocolClaim` lesson (#797) replayed, the vocabulary a fold
consults belongs to its own unit. The round's own gate replay also
taught the method-count twin of that lesson: `shadowedMethods()` pushed
`RouteMatchExplanation` to 21 methods, over Sonar's S1448 ceiling (20
authorized), and the honest fix was the same split -- `RouteMatchOutcome`
(`of()`, the MatchResult-to-spelling fold the span's status, the meter's
dimensions and the `route:test` answer read) extracted from the value
object it never belonged to, no exclusion added, the spellings still
owned by the explanation the fold translates into.

The infection gate answered with the mutation adversary's own lesson
set, all killed by construction or by fresh observation, zero new
ignores: the entry key's `method . "\x00" . host` concat was exactly
the separator grammar KDD-HW7 had already retired once (three concat
mutants targeting a key no legal grammar could make observable), so
the map became the nested tuple (method => host => entry) and the
mutants lost their target; the selected-identity map's `true` value
(nobody reads it) became the route itself; the two `ksort`s became
observable by re-declaring the order pin's fixture so the insertion
order is NOT the sorted order (the multi-method agnostic route
declared after the {tenant} claim -- a table whose insertion was
already sorted made the sort invisible, the classic unkillable-by-
fixture mistake). And the one pre-existing escape -- the workflow
collector's `continue`-to-`break` (a #824-class runner-dependent
mutant that escapes whenever the filesystem iterates the non-yml
sibling last) -- was retired by construction: the loop's skip guards
became one positive condition, a shape with no `continue` to mutate
into a truncated walk, the order-luck test beside it kept as the
behavioural pin.

The OpenAPI consequence is documented where the audit read it: the
`servers` list's entry presence does not promise every operation is
reachable on it (the specificity shadowing rule applies, `x-zef-host`
carries the claim, the rule has one owner and the export is not it --
`OpenApiServers`' docblock), and the dispatch half's own Allow semantics
are named on `RouteDispatchHandler`. The covers-filter lesson rode the
split: `RouteHostShadowedRoute` joins `RouteHostShadowedMethods` on the
explainer test's covers list, or mutation testing reads the collaborator
as unattributed.

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
partial-label spellings (`a*.example.com`); #795's own pin
(`testTheExactHostClaimShadowsTheAgnosticMethodClaimForTheWholePath`)
spells the audit's exact repro -- the exact claim's 405 `Allow` against the
cross-host dispatch of the method it shadowed. `tests/Config/Routing/RouteMatchExplainerTest.php`
carries the shadow's own battery: the agnostic and lower-specificity
entries with their host markers, the elsewhere exclusion, the
fallback-without-shadow, the dedupe behind one claim spelling, the
method-then-host order with the agnostic claim first, the version-miss
refusal carrying the shadow, the budget clamp over an overlong co-matching
claim spelling, and the empty-selection guard. `tests/Middleware/RouteDebugMiddlewareTest.php`
pins the dev header's `shadowed=` token end to end, and
`tests/Tooling/RouteMatchTest.php` pins the `route:test` text and JSON
forms. `tests/Router/RouterTest.php` pins the facade surface —
wildcard matching through `Router::match()` and the `absoluteUrl()`
refusal. `tests/Config/Routing/RouteTreeCacheTest.php` carries a
wildcard-host entry through the dump/load round trip against
`RadixTree::compile()`, so the cache replays the extended grammar through
its existing registry re-run with no format change.
