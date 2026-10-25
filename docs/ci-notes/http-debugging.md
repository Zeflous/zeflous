# HTTP debugging utilities — decision record

The HTTP lane's Debugging Utilities slice (roadmap "Fase 4.2"): the
raw dump inspector and the security-header validator, two CLI commands
(`bin/zef http:dump`, `bin/zef http:headers`) over one inspection boot.
This file records the decisions the slice made, the same way the lane's
other decision records do.

---

## KDD-DD1: one boot, two commands

Both commands share `tests/Support/Tooling/HttpInspectionBoot` — the
route inspection commands' own doctrine replayed for the execution
side: a configuration directory becomes the answered exchange through
the same application orchestrator the runtime rides
(`ApplicationBootstrapper::boot()` + `::pipeline()`), never a parallel
parse, with the router's own PSR-15 handler
(`RouterRequestHandler`) as the pipeline's terminal. The container AOT
artifact is the throwaway file the analysis CLIs share
(`ContainerAnalysisArtifact::path()`), unlinked in the boot's own
`finally` whether the boot answered or refused.

## KDD-DD2: the simulation is end-to-end truth

`http:dump` renders the exchange the application's own stack actually
answered: the middleware the fixture's `middleware.stack` declared
actually ran (the error boundary translates a routing refusal into the
RFC 9457 problem the wire would carry; the security-headers middleware
stamps the hardening the response actually carries), the terminal
handler the route named actually answered, and nothing is re-derived
or simulated at the rendering layer. A host debugging a missing header
sees exactly what the wire would see.

## KDD-DD3: the boot refuses before the pipeline ever runs

The boot keeps the route inspection seam's warm-up (`$router->tree()`):
a defective table surfaces as the boot's own typed refusal, never as a
failure from inside a pipeline that already started. The doctrine is
pinned by the marker-armed defect test — an outermost middleware whose
entry the boot's own contract forbids (`assertFileDoesNotExist`), the
cheapest observation of WHEN a boot refused.

## KDD-DD4: the wire spelling is the wire's own

The dump renders CRLF between every head line, one `Name: value` line
per field VALUE (a repeated field answers one line per value — never
the joined spelling), the empty line closing the head, and the body
verbatim after it — raw bytes, control bytes included, because a raw
dump's one job is to be truthful. The status line omits the reason
phrase entirely when the response carries none (the status-line
serializer's own doctrine, never a dangling space). The request block
always carries the Host line the shim derives from the URI.

## KDD-DD5: the body is bounded, in bytes, with the honest count

`HttpWireBody::rendered()` renders at most the caller's bound (the
CLI renderers carry the family default, 8192 bytes), truncating beyond
it with the notice naming exactly how many bytes were withheld. Both
measurements run under the `'8bit'` encoding — byte semantics, the
unit the notice names — the `BodyBudget` entry's own argument, with
the same scoped MBString ignore in `infection.json5`.

## KDD-DD6: a synthetic request carries no invented context

The simulated `ServerRequest` carries only what the caller spelled:
method, path, host, scheme, header pairs, body. No server params, no
cookie/query/upload/parsed-body context — a CLI request has no SAPI
context to invent. The scheme and host default to `http` and
`localhost` (the local-debugging reality), `--scheme=https` flips the
one applicability rule that reads it. A repeated `--header=` name
answers a repeated field (one line per value in the dump), never a
silent overwrite.

## KDD-DD7: the ruleset mirrors the scan, two tiers

`SecurityHeaderRuleset` declares ten checks: the six unconditional
hardening pairs the baseline family stamps, the Content-Security-Policy
and (https-gated) Strict-Transport-Security toggles, and the
default-on Permissions-Policy — all required — plus the
Cross-Origin-Embedder-Policy the framework deliberately does not ship,
audited as advisory: reported when present, never failing the audit.
The value grammars are the check kinds (`exact`, `one-of`, `non-empty`,
`max-age`), each pinned by its own fail arm.

## KDD-DD8: the audit judges the answer, not the declaration

`SecurityHeaderAudit::over()` judges the response the exchange actually
answered — never the configuration that declares the posture — so the
verdict is end-to-end truth: a middleware that failed to run, a header
a later middleware stripped, a value a host overrode, all surface as
findings. An https-only check over a cleartext exchange answers
not-applicable (the toggle family's own applicability rule mirrored),
and a duplicated hardening header fails its value grammar through the
joined header line — the duplication itself is the defect.

## KDD-DD9: the CLI is a bounded channel too

The argv grammar is fail-closed with the route commands' own bounds:
the method token and path grammar (≤2048, no control bytes), the host
(≤253), the scheme (`http`/`https` exactly), at most 16 `--header=`
flags each spelling `Name: value` with a printable value ≤4096 bytes,
and the body ≤8192 bytes (the dump bound). Exit codes follow the lane's
verdict doctrine: `http:dump` exits 0 on any answered exchange (the
dump is the evidence, whatever the status), `http:headers` exits by the
audit's verdict (0 pass, 1 findings), 2 for usage refusals, 1 for a
boot or simulation refusal.

## KDD-DD10: the fixture keeps the dump lane observable

The fixture application (`tests/Scripts/fixtures/http-app/`) declares
class-shaped handler services — never closures — with the AOT lane
enabled under production container mode, so the boot's throwaway
artifact is actually written and its `finally`-owned cleanup is
observable rather than vacuous; the same posture keeps the fixture
answering through the same compile/dump lane a production host rides.

## KDD-DD11: the fold split the ceiling demanded

The audit family ships as six units — `SecurityHeaderCheck` (the
declared unit), `SecurityHeaderRuleset` (the declaration), the finding
record, `SecurityHeaderGrammar` (the value judgement), `SecurityHeaderWalk`
(the per-check fold), and `SecurityHeaderAudit` (the record plus the
pass verdict) — the difficulty ceiling's own doctrine applied twice:
the first cut (grammar out of the walk) was not enough, the second
(walk out of the record) brought every class under 20.
