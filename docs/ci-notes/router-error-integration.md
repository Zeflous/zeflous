# Router error integration — the dispatch translation (E3)

Campaign: Router lane, closing the loop the error system's consumers were
waiting for. Branch `feat/router-error-integration`; the roadmap pointer is
`ROADMAP.md` "Error Handling System" phase 1/2 plus the Router area's
"RFC 9457 Problem Details: format error standar (JSON) untuk 404, 405, 406"
entry. This is the E3 slice the error campaign planned ("konsumen
DispatchOutcome — 404/405+Allow, menutup loop routing") and that
`ROADMAP-HISTORY.md`'s Error Handling row tracked as "the 405 throw site
(waiting on the Router lane)".

## The finding that demanded the slice

Every router slice merged to main (#512 facade, #522 compiled cache, #528
wildcard hosts) answered its 404/405/406 outcomes through a self-contained
inline renderer: `RouteDispatchHandler::problemFor()` hand-built a problem+json
body with its own `json_encode`, its own `about:blank` type, and its own
header hops. That renderer duplicated the error lane's RFC 9457 machinery
while skipping everything the error system had already shipped: the
disclosure policy, the Accept negotiation with the HTML fallback, the
`https://zef.io/errors/*` type URIs, the machine-readable error codes, the
trace-id and correlation-id resolution, the `no-store`/`nosniff` headers, the
4xx-equals-warning log policy, the fault metrics and the replay capture. The
typed family's `NotFoundException`/`MethodNotAllowedException` were never
thrown by the router, and `ProblemDetailsResponseHeaders`' `Allow` arm was a
consumer waiting for a producer. One posture was the contract — the
`NotFoundException` docblock's own words: "the client sees one 404 posture
whether the miss happened at the pattern or behind it".

## KDD-EI1: the throw site is the PSR-15 terminal handler, not the engine

The `Config/Routing` dispatcher keeps returning outcome objects by design —
`RouteDispatcher`'s docblock pins its scope as the configuration system's
integration seam ("not the HTTP one"). The translation into the typed refusal
family lives where the HTTP concern already lives:
`Middleware\RouteDispatchHandler::refusalFor()` is the throw site. This keeps
the deptrac layering intact (Config stays ignorant of the Exception family's
HTTP semantics; the Middleware layer already sits above both).

## KDD-EI2: each refusal carries exactly what its outcome can know

- not acceptable → `NotAcceptableException` (new, 406, `CarriesHttpStatus`)
  with the validated `acceptableMediaTypes` payload; its message keeps the
  inline renderer's tested public wording byte-identical
  ("Supported media types: application/json") so the integration changes the
  envelope, not the detail;
- method not allowed → `MethodNotAllowedException` with the request's own
  method and path (threaded on the `ServerRequestMetadata` the adapter
  already extracted — the refusal reports what the client asked, not what
  the HEAD-over-GET fallback re-derived) and the sorted allowed methods; the
  `Allow` header renders from the payload in `ProblemDetailsResponseHeaders`,
  exactly the arm that landed waiting for this;
- any other non-executed outcome → `NotFoundException` with the inline
  renderer's wording ("No route matches the requested resource.").

The 405 detail deliberately becomes richer than the old generic sentence
("The request method is not supported for this resource."):
`Method 'POST' is not allowed for /profile.` is the E1 family's own tested
message and the legacy parity — one posture means the same words whether the
refusal came from the router or from behind a matched route.

## KDD-EI3: ProblemDetailsMetadata learns 406

The typed 406 member required its stable RFC 9457 vocabulary:
`https://zef.io/errors/not-acceptable` / "Not Acceptable". Without the arm a
406 would have answered the internal-error type — a lie in the document.

## KDD-EI4: the factories the inline renderer consumed are dead and removed

`problemResponse()` was the only caller of the PSR-17 response/stream
factories inside the terminal handler. With the rendering gone, both
constructor parameters became dead machinery — removed from
`RouteDispatchHandler` (internal, one day old) and the response factory
removed from `RouterRequestHandler`'s constructor (the stream factory stays:
conditional requests and HEAD suppression still use it). `psalm` then
flagged `ResponseFactoryInterface::createResponse` as having no src caller:
the method joined `createRequest`/`createServerRequest` in the documented
contract-surface baseline category, and the dead `NotFoundException`
`UnusedClass` entry was removed — the baseline net shrank. The lesson
recurs: every rendering seam you delete takes its factory seam with it.

## KDD-EI5: the error boundary is the only renderer — composition, not duplication

`ErrorMappingMiddleware` needed zero changes: `ThirdPartyExceptionMapper`
passes typed Zef exceptions through, `ErrorStatusMap` resolves
`CarriesHttpStatus` to the contract status, `ErrorFaultRecorder` logs 4xx at
warning, and `ErrorEnvelope` renders the negotiated problem/HTML posture.
The router joins the same composition contract its hosts already honour —
hosts run the handler inside a pipeline carrying `http.errors`. A host that
opts out of the error boundary gets uncaught-exception semantics for router
misses exactly as it already does for its own handlers' typed refusals; that
is the one-posture contract, not a regression.

## KDD-EI6: negotiation is honest on the error path too

A client whose Accept header prefers HTML (the very reason a 406 happened)
receives the HTML error page for the 406 — the error negotiator reads the
same header the route negotiator rejected. The JSON problem posture is
asserted with a non-HTML, non-matching Accept (`application/xml`), the HTML
fallback with `text/html`. The old inline renderer answered problem+json to
everyone; the integration makes the refusal speak the client's language.

## KDD-EI7: HEAD-on-miss joins the error lane's posture

`RouterRequestHandler`'s HEAD body suppression applies to the responses the
router lane returns (executed representations, 304s); a thrown refusal
travels to the boundary and renders the problem/HTML posture with a body —
the same shape an application-level 404 on a HEAD request already answers
today. Hosts that want bodiless HEAD error responses compose the opt-in
`HeadMiddleware` outermost (it reissues HEAD as GET and empties the view on
the way out — covering misses and application failures alike). Pinned at
the throw site: a HEAD miss raises the same typed refusal as a GET miss.

## Observability: what the router's refusals gained

Before: silent answers, no log, no metric, no replay, no trace id. After:
every router 404/405/406 is a logged (4xx at warning through the PSR-3 seam
or the structured SAPI record), metered (`zef.http.errors.total`), replay-
captured (when the replay store is wired), correlation-stamped, trace-id'd
typed event — the metrics `route` dimension the error row named as waiting
on the Router lane now has its throw site feeding it.

## The mutation-lane trap the boundary tests carry

The boundary-wrapped tests exercise the error recorder's no-logger arm,
whose SAPI record writes to the process's real STDERR — and Infection's
`InitialTestsRunner` **terminates the suite on the first stderr byte it
sees** (`$process->stop()` on `Process::ERR`), long before a mutant is
generated. The suite's existing discipline (route `error_log` to a temp
file in `setUp`, restore and unlink in `tearDown` — the exact pattern
`ErrorMappingMiddlewareTest` documented for its own SAPI battery) now
covers `RouterRequestHandlerTest` too. The lesson: any new test that
drives an unlogged error boundary inherits the routed-log obligation, or
the mutation lane dies with a bare "exit code 143" that names no test.

