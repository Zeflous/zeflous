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

The remaining families, in the order the comparison surfaced them:
rate limiting (three stores + `X-RateLimit-*`), CSRF (HMAC double-submit),
authentication (bearer + the distributed security boundary), then the
unified runtime boundary and the observability/runtime lanes they imply.

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
word no operator could ever type by accident.

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
