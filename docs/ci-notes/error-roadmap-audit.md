# Error Handling Roadmap Audit

**Scope:** the closing requirement-to-component audit for `ROADMAP.md` → **Error Handling System (Enterprise-Grade)** against `main`, inspected 2026-11-25. The lane's campaign is landed — E1 the typed family (#434), E2 the RFC 9457 render (#444), E3 the router dispatch translation (#533), E4 the observability (#513, #856), E5 the operator tooling (#444, #577, #739, #855, #914, #915) — and this record reconciles every roadmap promise with its openable evidence, answers the lane's last named open item on the record, and turns the `ROADMAP-STATUS.md` row `✅ Implemented`: the P2 Error Handling row's own retirement slice, the last P2 area standing.

## Requirement-to-evidence reconciliation

### Fase 1 — Standardization & Core Structure (The Foundation)

| Roadmap promise | Evidence | Verdict |
|---|---|---|
| 1.1 Unified error object — `code`/`message`/`details`/`trace_id`, the structured payload | `src/Zef/Framework/Exception/ZefException.php`: the stable machine-readable error code (the `ExceptionErrorCode` default fold, 1–64 uppercase), the developer-only `details` array (never public by default — the renderer includes it only in explicitly enabled debug responses, after redaction), the bounded trace id (1–128 ASCII token); E1's record `docs/ci-notes/error-typed-hierarchy.md` | Present |
| 1.1 HTTP status mapping exception-type → status | The three-tier `ErrorStatusMap` (`src/Zef/Framework/Http/ErrorStatusMap.php`): `instanceof CarriesHttpStatus` → the `httpStatus()` contract, untyped `HttpException` → 400, anything else → 500 — the message-sniffing retirement E1 exists for (a refusal that merely *says* `BODY_TOO_LARGE` never again decides a 413) | Present |
| 1.2 RFC 9457 problem+json | `src/Zef/Framework/Http/ProblemDetailsResponseFactory.php` — the RFC 9457 fields over the Accept negotiation (#444); the router's 404/405/406 outcomes the typed family's first framework throw site (#533, `ProblemDetailsMetadata` owning the 406 arm, the machine-readable error codes N_F / M_N_A / N_A) | Present |
| 1.2 HTML fallback | The same factory's escaped-HTML render for the client that prefers it — a 406's own Accept line now meets the HTML error page for the very refusal it caused (#533's pinned behaviour) | Present |
| 1.3 The exception hierarchy | All eight roadmap classes live under `src/Zef/Framework/Exception/` — `ValidationException`, `AuthenticationException`, `AuthorizationException`, `NotFoundException`, `MethodNotAllowedException`, `ConflictException`, `RateLimitException`, `InternalException` — beside the family's wider leaf set (`NotAcceptableException`, `UnsupportedMediaTypeException`, `UploadTooLargeException`, the ingress-signal `PayloadTooLargeException` under `Http/`, `NativeErrorException`, `InvariantViolationException`), every leaf `final`, the root deliberately open (E4), and the `CarriesHttpStatus` capability marker the host's own exception class joins the resolution through without touching framework code (E2 — a host signal maps to its status by its own `httpStatus()`, never an instanceof ladder) | Present |

### Fase 2 — The Handler Chain (The Logic)

| Roadmap promise | Evidence | Verdict |
|---|---|---|
| 2.1 Single-entry global handler, context preservation | `src/Zef/Framework/Middleware/ErrorMappingMiddleware.php` — one `Throwable` catch → status resolution → record → delegate (E6, E7); the request-scoped context through `ErrorRequestContext` (the boundary mint's `correlationId`, the mutable W3C `traceId`, the route the router wrote at the match it already computed, the query count the PDO feeder feeds — #856, #915, #914); the boundary registered in the default middleware stack (the #897 finding's answer: the defaults-only boot ships the boundary at priority 1000, never a silent posture loss) | Present |
| 2.1 Graceful degradation | `ErrorEnvelope` → `FallbackResponse` — the double-failure plain-text 500 whose honest trigger is `json_encode()` throwing on dev-mode diagnostics over invalid UTF-8 (KDD-H17), the #508 hardening naming the construction failure's class on the SAPI log | Present |
| 2.2 Third-party exception mapping (PDO/Guzzle → Zef) | `src/Zef/Framework/Http/ThirdPartyExceptionMapper.php` — PDO normalised, Guzzle optional (the extension-detection posture, never a runtime dependency); the process boundary's uncaught arm composing the same mapper | Present |
| 2.2 The specific-handler pattern (`MethodNotAllowedException` → the `Allow` header) | `MethodNotAllowedException` carries `method`/`path`/`allowedMethods` (E3 — the payload follows the class, the mapper never re-derives what the matcher already resolved) and #533's dispatch translation renders the `Allow` header from exactly that payload; `RateLimitException` carries `retryAfterSeconds` for the `Retry-After` the rate-limit denial answers with | Present |
| 2.3 Debug mode vs production mode | `src/Zef/Framework/Http/ErrorDisclosure.php` — the typed family's messages public through `RedactedMessage` in both modes (an author who smuggles `password=…` into a client-facing message is stopped by policy, not review — E5), dev-mode diagnostics redacted, production the generic envelope; the `AppEnvironment` fold the error factories share (#562 — one doctrine, one spelling); the process boundary's own arm behind `http.error_handling.process_boundary` (#513) | Present |

### Fase 3 — Observability & Logging (The Eyes)

| Roadmap promise | Evidence | Verdict |
|---|---|---|
| 3.1 Structured JSON logging | `src/Zef/Framework/Logging/JsonLineLogger.php` — bounded JSONL records (the 2048-byte message clamp, #508; the byte-true `mb_strcut` spelling, #897), owner-only files outside the document root | Present |
| 3.1 Log levels (4xx WARNING / 5xx ERROR / fatal CRITICAL) | `ErrorFaultRecorder`'s severity policy — E4's promise, the recording gate moved to the recorder's own domain (E6) so the mapper stays one shape; the process boundary's fatal arms answering the CRITICAL class (#513) | Present |
| 3.1 Context enrichment | Answered privacy-by-default: the request/correlation identifier, the exception class and error code, the route dimension and the operation word carried; user IDs, IP addresses, user-agent strings, query strings and request bodies deliberately excluded — the recorded doctrine `docs/error-handling.md` spells (KDD-EA2 below) | Deviation, recorded |
| 3.2 Span close-on-error, the error attributes, trace propagation | The correlation family's request span records the failure — the error status with the 5xx fold, the exception's type, the exception event verbatim (#371); the propagation promise answered by the boundary mint: every record of the window, the response's `X-Request-ID` and the toolbar's lookup command join on the one identifier (#856, KDD-SR9 — the join pinned by test as the SAME string) | Present |
| 3.3 Error-rate metrics | `zef.http.errors.total` on the shared meter (`src/Zef/Framework/Middleware/ErrorFaultMetricsRecorder.php`), armed by the `observability.metrics` family, exposed by the `/metrics` endpoint (#386) — the series carrying `exception.type` and `http.route`, exactly the two dimensions the roadmap's own `zef_exceptions_total{type, route}` example spells (#915 for the route, the closed `unrouted` word answering every refusal that landed on none) | Present |
| 3.3 The analytics dashboard | De-scoped on the recorded grounds — KDD-EA1 below | De-scope, recorded |

### Fase 4 — Developer Experience & Tooling (The UX)

| Roadmap promise | Evidence | Verdict |
|---|---|---|
| 4.1 The debug toolbar (execution time, query count, exception count, the link to the logs) | `src/Zef/Framework/Middleware/DebugToolbarMiddleware.php` + `DebugToolbarMarkup.php` — the bounded, escaped, development-only aside carrying Duration / DB queries / Exceptions / Trace, and the `<details>` panel spelling the request-ID lookup command (`bin/zef errors:report --request-id=<trace>` — the roadmap's "Link ke log error terkait" answered as the bounded command the toolbar renders, clickable as the panel the markup carries); CSP-safe by skipping injection when a policy is already present; the query count fed by the PDO adapter's own feeder (#914 — `ErrorRequestContext::recordQueryFor()` once per executed statement in the finally seam the measured window's closing edge shares) | Present |
| 4.2 The CLI error inspector | `bin/zef errors:report` (the counted report, the `--request-id=` filter, the control-byte-stripped display) and `errors:clear` (the `--confirm` refusal, the truncation under `LOCK_EX`) — #444's guarded commands, lifted into three pure verb classes over shared folds by #739 | Present |
| 4.3 Local error replay | `src/Zef/Framework/Http/ErrorReplayStore.php` + `bin/zef errors:replay` — structured JSON/form payloads captured, free-form text/XML refused; the snapshot bound (#577, oldest-first pruning); the storage layout's `replay/` lane the derived address when a root is declared (#653); the boolean knobs folding through the one vocabulary (#855) | Present |

### The readiness matrix

| Matrix row | Verdict |
|---|---|
| RFC 9457 Format (JSON + HTML fallback) | Complete |
| Exception Hierarchy (`ZefException` & subclasses) | Complete — the eight roadmap classes plus the wider leaf set, the capability marker the extension point |
| Debug/Prod Mode (distinct behaviours) | Complete |
| Structured Logging (JSON + context enrichment) | Complete with the recorded privacy-by-default divergence (KDD-EA2) |
| Tracing Integration (span closing on error) | Complete |
| Debug Toolbar (Zef Profiler) | Complete |
| Third-Party Mapping (PDO/Guzzle → Zef) | Complete |
| Error Analytics (Prometheus metrics) | The series complete; the dashboard de-scoped (KDD-EA1) |

## KDD-EA1 — the optional analytics dashboard is the deployment's, and the record says so

The roadmap's Fase 3.3 carries two bullets: the error-rate metrics ("kirim jumlah error per tipe ke Prometheus", the `zef_exceptions_total{type=…, route=…}` example) and the dashboard ("Error Analytics Dashboard (Opsional)", the "Top Errors" view). The metrics bullet is landed evidence: `zef.http.errors.total` with exactly the two dimensions the example spells — `exception.type` and `http.route` (#915) — on the shared meter, exposed by the `/metrics` endpoint (#386), armed by the `observability.metrics` family. The dashboard bullet is de-scoped, on the grounds this record exists to make explicit:

- a dashboard is a deployment's own Grafana/Prometheus composition over the exposition the framework already answers — no lane of this tree ships a dashboard, and the zero-dependency invariant (CONTRIBUTING rule 1) fences the tree against carrying one;
- the roadmap's own marking is "Opsional" (the lowest-priority row of its readiness matrix), and the lane priorities table's definition of done — "each named open slice has a landed, openable evidence pointer" — reads the promise as the series the framework must answer, never the presentation a deployment composes over it;
- the "Top Errors" answer is a PromQL `topk()` over the series the tree already carries — nothing in the framework is missing a consumer, and nothing a dashboard would render is absent from the exposition.

The de-scope follows the Router lane's own doctrine (KDD-RA4 in `docs/ci-notes/router-roadmap-audit.md`): an explicit de-scope with recorded grounds, so the roadmap's promise never silently outgrows the tree's grammar — the optional item is answered by naming what the framework owes (the series, landed) and what it never will (the presentation, the deployment's).

## KDD-EA2 — the log context is privacy-by-default, and the enrichment list is the deviation

The roadmap's 3.1 sketch enumerated a pre-GDPR idiom: `user_id`, `ip_address`, `user_agent` beside `request_id` and the exception facts. The tree's answer is deliberately narrower, and the divergence is recorded doctrine rather than an oversight: `docs/error-handling.md` spells the exclusions (user IDs, IP addresses, user-agent strings, query strings and request bodies) — the posture every record-writing lane of the tree shares, the slow-request record's own privacy fold (KDD-SR4 in `docs/ci-notes/http-slow-request.md`), the replay sanitizer's grammar, the route dimension's closed `unrouted` word refusing the raw target (#915: "a cardinality bomb and a privacy leak the low-cardinality doctrine refuses"). The enrichment that IS carried: the request/correlation identifier the whole window joins on (#856), the exception class and error code, the route the request landed on, the operation word, the method and status the series' low-cardinality slices own. A fault record's diagnostic job never needed the user's identity — a framework that records it by default would be the leak the exclusion documents, and the correlation table's own doctrine spells the family's posture: a causal diagnostic may *point at* credential material without ever *carrying* it.

## KDD-EA3 — the E5 backlog was promoted through its own slices, never silently

The E1 record's queue named E5 as backlog "tidak dinaikkan diam-diam" — not promoted silently. The promotion happened through its own pull requests, each with its evidence: the toolbar (#444, its counting fed by #914), the CLI inspector (#444, lifted into pure verb classes by #739), the replay store (#444, bounded by #577, re-homed onto the storage layout by #653, toggle-unified by #855), the analytics series (#915), and — beyond the roadmap's own ask — the process error boundary (#513, the composition's second error face installed around the PHP process itself, the `http.error_handling.process_boundary` knob). This closing audit promotes nothing either: it reconciles what landed, and the row's flip rides its own pull request with its own record commit — the same rule-13 discipline every landing before it kept.

## Verdict

The Error Handling lane is COMPLETE: every Fase 1..4 promise and every readiness-matrix row carries a landed, openable evidence pointer; the two divergences (the privacy-by-default log context, the de-scoped optional dashboard) are the lane's recorded answers, not gaps. The `ROADMAP-STATUS.md` row turns `✅ Implemented` in this pull request's record commit; the P2 Error Handling row retires from the lane priorities table; five Partial areas remain after this landing, all P3.
