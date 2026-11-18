# CI Note — Security Runtime Families (Config-Driven)

Lane: the security runtime port campaign -- the reference repositories'
security strengths, re-landed here as fully config-driven families over
the application profile's own machinery (`app/configs/`, the layered
Default < App < Env merge, the declarative `services` grammar and the
one-call boot). The campaign's order follows the reference comparison
recorded in the work log (session `REF-GITLAB`): the GitLab snapshot
(`zeflous/zef` `zef-main`, milestone E2) is the *what* -- the widest
security surface -- and this repository's own discipline is the *how*.

The work log of this lane's PRs:

| PR | Scope |
|----|-------|
| Q1 | The security-headers family: `SecurityHeadersMiddleware` (+ `SecurityHeadersPolicy`, `BaselineSecurityHeaders`, `ToggledSecurityHeaders`), the `SecurityHeadersFactory` construction seam, the framework profile's first service registration and knob block (`security.headers.*`), the shipped `app/configs/security.php` override surface, the `framework.config` container service, the `HttpHotPathBench` decoration subject |
| Q2 | The rate-limit family: `RateLimitMiddleware` (+ `RateLimitPolicy`, `RateLimitDecision`, `RateLimiterInterface`, `ClientAddress`, `SecurityFamilies`, `StrictToggles`), the three stores (`InMemoryRateLimiter` bounded fixed-window with an injectable clock; `ApcuRateLimiter` over the atomic `ApcuCounterStore` primitives; `RedisRateLimiter` over `RedisSharedRateLimitStore` and the `PhpRedisCommands` boundary), the `RateLimitFactory` store-selection seam, the framework profile's second registration and knob block (`security.rate_limit.*`), the family census strictness (`security` map's unknown-child rejection), the `ext-apcu`/`ext-redis` suggestions, and the process-isolated extension-double test discipline |

The remaining families, in the order the comparison surfaced them:
CSRF (HMAC double-submit), authentication (bearer + the distributed
security boundary), then the unified runtime boundary and the
observability/runtime lanes they imply.

## The decisions (KDD-SC)

**KDD-SC1 -- The frozen repository is itself a container service, and that
is the whole seam.** A config-driven middleware registration needs exactly
one thing the boot did not yet provide: a way for a declarative definition
entry to reach the merged configuration. The bootstrapper registers the
frozen repository under `framework.config`
(`FrameworkDefaults::CONFIG_SERVICE` -- the identifier the profile itself declares) *before* any definition is
installed, and the framework's own registration addresses a factory
through it: `'factory' => SecurityHeadersFactory::class . '::fromConfig'`
over `['service' => 'framework.config']`. Everything else is machinery
that already existed and was already tested -- `FactoryClosures`'
static-call arm, `DefinitionEdges`' leaf-edge acceptance of a
container-registered reference, `ArgumentResolution`'s reference form. Two
alternatives were rejected: letting the HTTP-layer policy read the
`Config` class directly (Http += Config would break the zero-library
shim's isolation, and the Http layer owns messages, not repositories), and
a kernel-layer `SecurityBootstrapper` installing services programmatically
(a Kernel += Http deptrac widening to avoid, and a registration lane
parallel to the declarative grammar instead of through it). The factory
itself lives in the Middleware layer -- the one layer already allowed to
see both `Config` and `Http` -- and is string-addressed, which is why it
is marked `@api` rather than being baselined: the declarative grammar is
its consumer, invisible to static analysis by design.

**KDD-SC2 -- Framework registrations live in the reserved `framework.services`
namespace of the Default layer; stack membership stays the host's explicit
choice.** The registration and the knob defaults are declared by
`FrameworkDefaults::profile()` -- code, the undeletable layer (KDD-AC2's
own argument: a host can delete or half-edit shipped files; code cannot
be edited out from under the framework). The first draft declared the
registration under the plain `services` key, and the boot test killed it
within minutes: the merge contract counts an empty array as a *clearing
list* ("a layer that sets a key to `[]` clears it"), so the shipped
`app/configs/services.php` -- an empty map -- wiped the framework's
registrations out of the merged tree. That collision is not a bug to work
around but the design pointing at itself: the host's `services` key is the
host's declaration surface and must stay fully theirs to clear, so the
framework's own machinery belongs in its own reserved `framework.services`
namespace, which `ContainerBootstrapper` reads in addition to the host's
map (framework definitions install first, so a same-identifier host entry
is an explicit rebind that wins). The registration therefore always exists
in a booted container, while *running* it is the host's decision: the
`middleware.stack` list opts in by name (`'security.headers'`), exactly
the way any other middleware does. An auto-injected default stack was
rejected -- implicit stack magic contradicts the pipeline lane's explicit
composition contract. `app/configs/security.php` ships as the documented
override surface and deliberately returns `[]`: the defaults are the
posture, the file is where a host writes its adjustments, and a host that
deletes the file still boots the full default posture.

**KDD-SC3 -- Knobs are read strictly, in both spellings.** The merged
subtree can carry a native bool (file or default layer) or a raw string
(environment overlay -- `EnvSource` passes values through as the strings
the environment carries, casting being the schema layer's job). The strict
reading accepts a native bool, folds a string through the closed boolean
vocabulary (`1`/`true`/`on`/`yes` and their negations, case-insensitive)
and rejects everything else with the knob named -- a `maybe` or a mistyped
`ture` halts the boot rather than silently reading as false. This is the
legacy `zef-framework` `readBoolStrict` lineage. Unknown keys inside the
family map are rejected with the known list spelled out (the restored
reference's strict-mode lineage): a `htss` typo must never fall through to
defaults unnoticed -- the operator believes the protection is on while it
is not. A `null` subtree (or a null knob) is the host declaring no
opinion: the default posture applies. Family-level unknown-child
rejection (`security.rate_limitt` before the rate-limit family exists) is
deliberately deferred until the second family lands: today the `security`
key has exactly one legitimate child, and the strictness seam that knows
the whole family set belongs to the PR that introduces a second family to
know.

**KDD-SC4 -- Replace, never append.** The decoration uses `withHeader()`,
so a handler that set `X-Frame-Options: SAMEORIGIN` is answered with
`DENY`: the middleware is the hardening boundary, and the reference's
posture (its `withHeader()` calls over the handler response) is preserved
verbatim. The six baseline headers are constants, not knobs -- weakening
the baseline means removing the middleware from the stack, which is a
decision, not a spelling.

**KDD-SC5 -- The port preserves the reference's semantics, value for
value.** The six baseline pairs, the deny-all Permissions-Policy value,
the one-year HSTS `max-age` with `includeSubDomains`, the https-only HSTS
emission, the default CSP directive string, and `permissions_policy`
defaulting to on while `hsts`/`csp` default to off are all move-verbatim
from the reference's E2 middleware. The restructured part is construction:
the reference read a raw policy array at every call site; here the
validated knob set is a `SecurityHeadersPolicy` value object built once at
boot through the layered configuration. HSTS's fixed `max-age` and the
Permissions-Policy value stay deliberately un-knobbed -- the surface keeps
exactly the toggles the reference exposed.

**KDD-SC6 -- The defaults are declared twice, on purpose, and pinned
together.** The knob defaults necessarily live in two code places: the
kernel layer's `FrameworkDefaults::profile()` (the Default layer's data)
and the Http layer's `SecurityHeadersPolicy` constructor (the hand-
construction defaults) -- the layer rules forbid the policy from reading
the kernel's declaration (Http depends on neither Kernel nor Config
types at construction time; the factory passes the *merged* map, which the
Default layer has already saturated). The drift is therefore pinned by
test from above, where both declarations are visible:
`testTheFrameworkProfileMirrorsThePolicyDefaults` asserts the profile
block equals the policy-from-empty-map key for key and value for value,
and `testAnEmptyMapReadsTheDefaultPosture` pins the map reading to the
bare constructor. Any drift in either direction fails the suite.

**KDD-SC7 -- Validation rides the resolution the stack already forces.**
The knobs are validated when the middleware is *resolved* -- the
`middleware.stack` entry triggers the factory at pipeline-build time,
which in the one-call boot flow is composition time, before the first
request. A family the host never opts into is never validated and never
promised (KDD-AC3's honesty rule applied to security: the surface promises
what the running stack honours). An invalid knob on an opted-in family
halts the boot exactly as a malformed route table does.

**KDD-SC8 -- The toggle fold's case-map zone, analysed honestly.** The
strict vocabulary folds through `mb_strtolower()` (the repo-wide
multi-byte mandate), and the analysis of its MBString mutator swap to
`strtolower()` initially looked killable: find a value that only the
Unicode case map folds into a vocabulary word. The candidate was the
Kelvin sign (U+212A), the famous lower-case-into-ASCII mapping -- but it
lands on `k`, and no vocabulary word (`true`/`false`/`on`/`off`/`yes`/
`no`/`1`/`0`) contains a `k`. In fact `k` is the *only*
single-character lower-case landing into ASCII the Unicode case map has
(U+0130 lands on `i` *plus a combining dot* -- a two-character mapping,
not a pure ASCII landing), so **no** value distinguishes the two folds
within reach of the closed vocabulary: every fold-divergent value is
rejected under both spellings, with the knob named. The zone is therefore
value-equivalent by construction, and it is answered the way the
ETagMiddleware method-token fold was answered (`mb_strtoupper` over
GET/HEAD, KDD-H15 in `docs/ci-notes/http-layer.md`): a method-scoped,
fully argued ignore entry in `infection.json5` -- the fourth of its
kind -- rather than a decorative test that asserts the fold accepts a
word no operator could ever type by accident. **Q2 amendment:** the fold
moved from the security-headers policy into `StrictToggles::read()` when
the second family needed the same reading -- the extraction is
coverage-neutral (the strict-toggles suite owns the method, and the
ignore entry's target was renamed to the extracted seam), and the
argument above travels with the code.

## The decisions (KDD-RL, the rate-limit family)

**KDD-RL1 -- Three stores behind one contract, selected by one knob,
fail-closed without an environment-dependent branch.** The family ports
the reference's breadth (the per-process, one-process-tree and
cross-host stores) through this repository's decomposition:
`RateLimiterInterface` is the verdict contract; `InMemoryRateLimiter` is
the bounded fixed-window arithmetic with the injectable clock; the
shared stores own only atomicity (`ApcuCounterStoreInterface`'s two
primitives, `RedisCommandsInterface`'s three commands), and the window
semantics above them are pure classes (`ApcuRateLimiter`,
`RedisSharedRateLimitStore`) tested against doubles. The `security
.rate_limit.store` knob selects the store at the factory, and a store
the runner cannot honour fails its own honest way -- never a silent
downgrade: the redis boundary establishes its connection at
construction (boot time) and refuses with the endpoint and the
remediation spelled out for *every* way the connection can fail (the
extension's class missing, the endpoint refusing, the connection
throwing -- one answer, one branch, covered in every environment), and
the apcu boundary's absence surfaces through the middleware's catch-all
(an accounting call that cannot even be made answers the fail-closed
503, never an unaccounted admission). The reference fell back to the
per-process limiter with an `error_log` line instead -- a silent
downgrade that multiplies the declared limit by the worker count while
the knob promises shared accounting, the exact "protection the operator
believes is running" failure the strict reading exists to prevent. An
unknown store name is rejected the same way (the reference silently
answered its in-memory default for any typo), and the reference's
unconnected `\Redis` (whose first request's commands fataled into its
catch-all 503 -- an outage the operator never heard about until a client
did) became the boot-time refusal above.

The design deliberately carries **no `function_exists`/`class_exists`
availability branch**: the CI runners carry APCu and phpredis while the
local environment carries neither, so an extension probe is an
environment-dependent branch -- unreachable in one of the two
environments by construction, which would leave its mutants uncovered
in that environment's mutation run. Fail-closedness is preserved by the
failure paths the stores' own constructors and calls already own (the
redis connection refusal; the middleware's catch-all), which are
exercised identically everywhere.

**KDD-RL2 -- The extension boundaries are verified through doubles
that shadow the calls in every environment, and no eval anywhere.**
The local test environment carries neither APCu nor phpredis; the CI
runners carry both -- and function doubles cannot redefine the global
functions a running process already has. The APCu boundary calls its
functions *unqualified*, so PHP resolves them in the boundary's own
namespace first: the doubles the suite loads there (one real, analysed
file, `tests/Support/Http/apcu_function_doubles.php`, required once
into the process by the support class that resets its typed state)
shadow the global calls **whether the extension is present or not**, no
process isolation needed. The phpredis boundary speaks the family's
own seam: `RedisConnectionInterface` (the command surface plus the
connection), carried as the union `\Redis|RedisConnectionInterface` --
the extension's own class in production, engine-checked exactly as
phpredis declares it wherever the runner carries it, and the test
double through the interface arm: a real, autoloaded, fully analysed
class whose answers are the suite's own in every environment, with no
signature gymnastics against the extension's shapes at all (the double
implements the family's seam, not the extension's class -- an earlier
draft subclassed `\Redis` and had to carry phpredis 6's exact
parameter list to satisfy PHP 8.4's override rules; the seam makes
that whole discipline unnecessary). Every delegation and both
connection-failure arms are the doubles' own answers, the coverage is
real in both environments, and `infection.json5` gained **no** boundary
ignore entries (the one addition is the store vocabulary's MBString
fold, KDD-SC8's fifth, argued identically). The discipline is the
experiment's answer, run before a line of the family was written --
though the first draft assumed both environments lacked the
extensions, and the CI run on the real runners corrected that
assumption within minutes: environment-dependent test design is itself
a defect, caught the same way. The eval'd predecessors of both doubles
met a second discipline: the security scanner blocks `eval` outright
(a rule this repository keeps, not suppresses), and the honest answer
was not an inline suppression but the seam redesign above -- the
doubles became real files the analysers read like any other code, and
the mutation score did not move.

**KDD-RL3 -- The APCu store honours the reference's contract, not its
bug.** The reference's `SharedRateLimitStoreInterface` docblock demands
atomicity ("implementations MUST be atomic for read-modify-write on one
key... ApcuRateLimiter -> apcu_inc()/apcu_cas()"), but its APCu
implementation was an `apcu_fetch`/`apcu_store` read-modify-write that
races between workers (two workers can read the same count and both
write the same increment, losing one). The port keeps the contract and
replaces the bug: one `apcu_inc` step per request (atomic per key, the
TTL initialising a fresh counter at one) plus one `apcu_add`-claimed
reset epoch per window (set-only-if-absent, so the window's first
request fixes the epoch everyone else reads). The documented residual
(amended by issue #785's finding 2, 2026-11-19): counter and epoch are
two keys written in two steps, so a window boundary can skew by
microseconds -- the counter key outlives the epoch by exactly the skew,
and a request landing inside that gap reads the new epoch against the
old window's still-living count. The skew only ever *lengthens* the
reported `Retry-After` (conservative), never shortens it; the aggregate
count per window, however, is **not exact across that boundary** -- the
new epoch's first verdicts can carry the previous window's tail (the
limiter's own docblock claimed exactness until the audit's micro-race
walked the two-key timeline; the honest directional claim is the
conservatism, not the count).

**KDD-RL4 -- The in-memory store's sweep is capacity-triggered, with
identical verdicts.** The reference swept every bucket on every check
(O(buckets) per request at the default ten-thousand-key capacity --
thousands of comparisons per request to reclaim space that only a *new*
key needs). The port sweeps only when a new key arrives at capacity:
existing-key rollovers were already answered by the per-key epoch check
(the reference's own post-sweep `reset <= now` arm), capacity semantics
are identical (reclaim-then-refuse, with the same exhaustion error), and
the hot path is O(1) amortised -- the bench subject
(`benchARateLimitedRequestAccountsAndStampsTheHeaders`) pins it.

**KDD-RL5 -- The family census lands with the second family.** KDD-SC3
deferred the `security` map's unknown-child rejection to "the PR that
introduces a second family to know" -- this is that PR.
`SecurityFamilies::subtree()` is the shared census (`headers`,
`rate_limit`): every family's factory reads its subtree through it, so
a `security.rate_limitt` typo halts at resolution with the known list
spelled out, exactly as an unknown knob inside a family map always has.
The census is family-additive -- the CSRF and authentication families
append their names when they land.

**KDD-RL6 -- The middleware is the admission seam; the knob arms it.**
The reference's runtime boundary carried `rateLimitEnabled` as a policy
field the middleware short-circuited on, and the port keeps that
semantic deliberately: a disarmed policy is a pass-through, the
middleware in the stack is the admission seam, and `enabled` is the
emergency switch an env overlay can flip without touching the stack.
This is the one toggle where "remove the middleware from the stack" is
*not* the spelling for "off" -- removing it also removes the seam an
operator may need to re-arm in a hurry. The denial and outage answers
are the reference's, move-verbatim in order and clamps (429 +
`Retry-After` + the accounting headers; a limiter failure answers the
fail-closed 503 with `Retry-After: 1`), restructured in rendering: the
hand-rolled JSON shape folds into the layer's one error envelope
(`ErrorResponseFactory`), so a client correlates a 429 exactly like a
500, and the correlation id is always present.

**KDD-RL7 -- Validation at the seam, once.** The reference repeated
input validation inside every store implementation (empty key,
`limit < 1`, `windowSeconds < 1` -- its own in-memory limiter carried
none of them, an asymmetry). The port validates once, where the values
enter: the policy's constructor invariants hold the integer knobs at
their ranges (a mistyped zero halts with the knob named -- the
reference's `max(1, ...)` silently promoted it), the strict integer
reading accepts a native int or an all-digit string (the environment
overlay's spelling) and rejects everything else, the store vocabulary is
closed and case-folded, and the address resolver guarantees a non-empty
validated key. The stores below carry the arithmetic only -- which is
what keeps their mutants honest: every branch a store owns is a branch
the accounting needs, not a guard the composition already proved.

**KDD-RL8 -- The clock is injectable in every store, not just the
test-visible one.** The reference gave its in-memory limiter the
`?\Closure $now` seam but read `time()` inline in the APCu and Redis
limiters -- the port makes the seam family-wide (the requirement
"clock injectable" taken literally), so the window arithmetic is
deterministic under test for all three stores and no store's
`Retry-After` derivation is untestable by construction.

## The decisions (KDD-AU, the auth family's audit round, issue #785)

The authentication family (#357) carried its decisions in the code's own
docblocks and the history row; this round -- the http auth/rate-limit
audit (A-M1..A-M12, checkout `091e5c85`) -- lands the first standing
record here, beside the security families it shares the runtime with.

**KDD-AU1 -- The anonymous principal's name is reserved for the
anonymous resolution (finding 4).** The authorization policy finds the
anonymous branch by folding the *context's principal id*
(`AllowScopePolicy`, the reference's own case-insensitive comparison --
a proxy the policy cannot escape: it never sees the handle the
credential travelled under). A custom provider that resolves a
*presented* credential into a principal named `anonymous` (any fold)
would steer an authenticated principal into that branch and its
blanket allowance, with no scope ever consulted. The fix rides the one
collaborator that holds both facts: the boundary refuses the resolution
as `MALFORMED_METADATA` -- the taxonomy's contract vocabulary, whose
reference application (authenticated-without-context) was unreachable,
gaining its one live arm here instead of a new enum value (a wiring
error answers the 500 it honestly is; the client cannot act on a valid
token the wiring refuses, so no 401 masquerade). The discriminator is
the handle's own anonymous state, never the argument's nullness: a
caller passing `AuthCredential::anonymous()` explicitly travels the
anonymous resolution and keeps the name. Belt and braces on the
framework's own wiring: `StaticCredentialProvider`'s composition halts
on a table principal that claims the reserved name (the same
fail-closed boot posture as the empty-token table) -- the boundary
refusal covers every custom provider, the composition refusal keeps the
shipped table from ever needing it.

The fold itself stands as its own single-question unit
(`ReservedPrincipalName` -- the `FactoryShadows::target()` idiom, a
judgment three call sites share: the policy's branch, the boundary's
refusal, the table's composition halt), which the round's archeology
gate demanded: the first form (the fold inline in the boundary's
`admit()`) measured class difficulty 21.94 against the ceiling of 20
the family borrows -- the extraction brought the boundary to 19.43
with the unit at 8.75, the same lesson the pipeline lane's KDD-M13
recorded in the same round.

The unit's own fold carries the KDD-SC8 argument as its method-scoped
ignore entry in `infection.json5` (the MBString mutator's rewrite
`mb_strtolower` -> `strtolower`): the reserved word spells no `k` and
no `i`, and the Unicode case map's only single-character lower-case
landings into ASCII are the Kelvin sign's `k` and the Turkish capital
I's two-character `i-dot` -- so every fold-divergent principal id
fails to equal `anonymous` under both spellings, the zone is
value-equivalent by construction (verified by running the family
battery under the manually applied mutation -- the complete battery
passes, the case-fold pins the boundary, policy and table batteries
carry).

**KDD-AU2 -- The APCu docblock answers the availability and exactness
questions the code actually owns (findings 1 and 2).** The limiter's
docblock promised a factory availability check that never ran and a
count exactness the two-key timeline never held. Both claims are now
the code's own: availability is the deployment's to guarantee (no
`function_exists` branch, KDD-RL1's own doctrine -- the extension's
absence surfaces per-request through the middleware's catch-all
fail-closed 503, never a boot halt with a remediation), and the
boundary skew is spelled honestly (conservative direction, count
straddling the epoch rollover -- KDD-RL3's amended residual). No
behaviour changed: the audit's disposition was docblock honesty, the
same round the #784 lane gave the compression and body-parser
families.

**KDD-AU3 -- The middleware entry map is closed like a family map
(finding 3, recorded with the pipeline lane).** The `middleware.stack`
entry grammar silently ignored unknown keys -- a typo'd `prioriti`
fell through to the priority-0 default unnoticed, exactly the class of
failure the strict-knob census refuses ("a typo'd key must never fall
through to the default posture unnoticed"). `MiddlewareDefinition::
fromArray()` now rejects unknown keys with the known list spelled out
(`service`, `id`, `priority`) -- the entry-level closing of KDD-SC3's
own doctrine, recorded in full in `docs/ci-notes/middleware-pipeline.md`
(KDD-M13). The audit's remaining findings are informational posture
(documented properties, no code change): the IP-only rate-limit key with
the unparsed peer's shared `0.0.0.0` bucket, the raw replay-id bytes
in APCu, the static table's residual index timing, the wall-clock auth
seam, the consume-before-handler replay order, and the redis store's
three-command non-atomicity -- each stays the documented operational
awareness the audit recorded, none a strict-reading violation.

## Legacy attribution

The security runtime surface is ported from the GitLab snapshot of the
reference implementation (`zeflous/zef` `zef-main`, milestone E2, see its
`docs/SECURITY_RUNTIME_E2.md`): the header set and values, the https-gated
HSTS, the replace-not-append decoration and the toggle defaults are that
implementation's, restructured onto the layered configuration and the
declarative service grammar. The strict boolean reading descends from the
older GitHub reference (`zef-framework` `app/Bootstrap.php`
`readBoolStrict` -- fail-closed env toggles), and the unknown-knob
rejection descends from the restored reference's Configuration System v2
strict mode (`ConfigSchemaValidator` with `allowUnknownKeys=false` -- a
typo'd key refuses the boot). The reference's env-only configuration
posture (every knob an environment variable, no files) was consciously
*not* ported: this repository's host-facing contract is files-first with
an opt-in env overlay, which is the direction the owner set for the
restart.
