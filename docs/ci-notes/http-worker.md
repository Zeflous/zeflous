# HTTP Worker Context — The Inbound Payload Vocabulary, The Outbound Answer

KDD-WK1..WK9 for `src/Zef/Framework/Http/` — the ROADMAP's
"Zero-Library Shim" Fase 2.2 slice ("Worker Context Adapter,
RoadRunner/Swoole Mode"): the inbound half of the runtime adapter for
persistent-worker runtimes. Where the SAPI twin
(`RequestFactory::fromServer()`) reads the engine's process state (the
superglobals the engine filled, `php://input` the engine owns), this
slice reads the wire shape the deployment's own glue hands over and
answers the same `ServerRequest` the application's stack dispatches --
one composition every runtime mode funnels into, so the middleware lane
never learns which runtime carried the request in. `feat/http-worker-context`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5847 tests /
14214 assertions; smoke 20000 at 2760 bytes; concurrency 23 workflows;
audit 0 advisories) · psalm 0 errors (baseline SHRINKS: one newly
consumed vendored interface method) · progpilot `[]` · scoped
infection over the slice's nine source files, 107 mutants, MSI
100/100, 0 escapes, one line-scoped equivalence ignore · archeology 0
error-level findings (the first cut's WorkerConnection answered the
difficulty ceiling at 21.58 and was split, below) · bench subject green
(the worker payload round trip, ~31µs per request).

## The map

| Unit | Question it owns |
|---|---|
| `WorkerRequest` | the wire shape a worker runtime hands the application, every field judged at construction |
| `WorkerHeaders` | the payload's header map in the layer's canonical form |
| `WorkerPeerAddress` | the peer-address literal's grammar and one canonical spelling |
| `WorkerConnection` | the port corridor and the precise entries a peer owns |
| `WorkerBody` | the payload-to-stream conversion: memory-resident, budget-judged |
| `WorkerUri` | the effective request URI: the payload's spelling, the Host join |
| `WorkerQuery` | the query component decoded into the query bag |
| `WorkerCookies` | the `Cookie` header values decoded into the cookie bag |
| `WorkerRequestFactory` | the composition root: one payload, one `ServerRequest` |

## KDD-WK1 — The vocabulary is ours; the adapter is the deployment's

The roadmap names RoadRunner and Swoole, but the zero-dependency
doctrine pins the shape before any runtime SDK can: no third-party type
may cross into `src/`. The port is therefore `WorkerRequest` -- a value
object in the layer's own vocabulary that the deployment's glue fills
from whatever its runtime hands it (the PSR-7 request a RoadRunner
PSR7 client answers, the raw request object Swoole's event loop
delivers), and `WorkerRequestFactory` converts through the layer's own
validated seams. This is the durable-store slice's port-first pattern
replayed on the inbound side: the Event lane spelled its
`DeferredDeliveryStore` port and let the filesystem answer it; the HTTP
lane spells the wire shape and lets the runtime answer it. The
`@api` markers the analyser respects name both surfaces as the host's
own (`RequestFactory::validateIngress()`'s posture, not a baseline
entry) -- the analysed tree composes the SAPI twin instead, and the
calls live in the deployment's glue, where the analyser never looks.

## KDD-WK2 — Construction refuses what cannot become a request

The `DeliveryEnvelope` doctrine replayed: the payload's constructor
judges every field it carries before any request exists. The method
token is uppercased and grammar-judged (`HttpMethod`, the single
ingress boundary the SAPI twin normalises at -- the long-s case-map
fold pinned the same way `RequestFactoryTest` pins its twin); the URI
string parses through the layer's own `Uri` (control bytes, the port
range, the percent-encoding); the header map canonicalises through
`WorkerHeaders` (names judged, values the grammar's own string lists);
the protocol version judges through `HttpVersion` in its PSR-7
spelling -- "1.1", the form every worker runtime's request objects
carry, the "HTTP/1.1" spelling refused because the vocabulary carries
the layer's own form, never the SAPI's; and the peer pair judges
through the connection folds. A payload that cannot become a request
never leaves its constructor -- the refusal is the vocabulary telling
the glue which spelling is which, never a runtime crash downstream.

## KDD-WK3 — The canonical header map owns the engine's key fold honestly

The folds read the header map BEFORE any request exists (the body
claim, the cookie line, the host authority), so `WorkerHeaders` owns
the one spelling they all read: names lowercased (the `ServerHeaders`
representation for the SAPI twin), integer keys cast to their decimal
spelling, every name and value judged by `HeaderGrammar` (the mixed
boundary is deliberate -- the PSR-7 contract's own untyped-parameter
doctrine, non-strings rejected rather than coerced). The map's own
keys carry the engine's fold stated rather than hidden: a name that is
itself a numeric string lands under its INTEGER engine form, because
PHP folds a numeric-string key on every insert and no array can
observe the difference -- the KDD-H10 claim, replayed on the name. The
docblock spells `array<array-key, ...>` and the factory's bag handoffs
carry the same claim inline (the `fromServer()` idiom), so neither
analyser is asked to believe a `array<string, ...>` docblock the
engine itself refutes. Two spellings of one name merge in encounter
order -- the map form of two header lines of one field -- never a
last-wins overwrite that silently drops a value the client sent.

## KDD-WK4 — The budget's lazy half has no worker twin, and none is wrapped

The SAPI doctrine -- "a lying or absent header only decides WHICH half
throws" -- collapses into ONE total judgment here: the SAPI's
`LimitedInputStream` policing exists for a body whose true size the
boundary cannot state (`php://input` hides it behind the claim), but
the payload's byte length is known before the stream is even opened.
The claim is judged eagerly (`claimExceeds()`, the OWS-trimmed line),
the true length is judged eagerly (`mb_strlen($payload, '8bit')` --
the byte measurement `BodyBudget` owns, the character fold would admit
a multibyte payload half again over the policy, and the boundary test
pins exactly that divergence), and both lie directions -- a claim
inflated over the policy, a claim deflated under an over-budget
payload -- throw the same typed refusal at the boundary. There is no
lazy half left to police, so none is wrapped: a wrapper that can never
trigger is dead logic, and the layer does not carry it. The stream
itself rides `php://memory`, never a temp file -- the ROADMAP's own
demand, and the honest arithmetic behind it: the payload is already
resident (the protocol carried it into memory), so a `php://temp`
spill past its threshold would copy the payload to disk for zero
memory win while the policy's own byte bound keeps the stream bounded
by construction.

## KDD-WK5 — The connection injection is precise, and absence stays absence

The ROADMAP names the injection "precise", and the precision is a
negative result: the two entries a peer connection owns are
`REMOTE_ADDR` and `REMOTE_PORT` (the spellings the layer's own
downstream readers look for -- `ClientAddress` reads the address, the
trusted-proxy boundary walks it), and NOTHING else is invented. No
fake `REQUEST_METHOD` or `REQUEST_URI` entries, because the wire's own
shape already lives in the request's own API, and a synthetic SAPI
array would hand one request two spellings of the same fact. A runtime
that cannot name its peer (a unix-socket peer, a synthetic request)
contributes no entry rather than a placeholder value; the empty
address spelling normalises to the same absence; and a port stated
without its address is refused outright -- no socket names a peer's
port while losing its address. The address itself is judged by the
engine's own literal validator (`filter_var()` with the IP flags, the
same trust class as the `parse_url()` the URI fold rides), a bracketed
IPv6 literal normalising to the bare form, the one lowercase spelling
every downstream reader compares against. The integration test walks
the whole chain: the built request through
`RequestFactory::validateIngress()` (the trusted-host half reading the
effective host, the body half a stream that states its size) and
`ClientAddress::resolve()` answering the injected address -- the same
protections, replayed on a request no superglobal built.

## KDD-WK6 — The absolute spelling rides; the origin form joins what the Host spells

A worker runtime hands the URI exactly as it read it off the wire: the
absolute form some runtimes reconstruct, or the origin form the
request line itself carries. The absolute spelling rides as-is -- the
wire's own reconstruction wins, and a Host header that disagrees with
it rides the PSR-7 preserve-host rule verbatim, never silently
reconciled (the disagreement is the glue's own, and both spellings
stay observable). The origin form carries no authority, so the fold
joins one: the Host header's authority parsed by the layer's own
`HostAuthority` grammar (bracketed IPv6, the non-default port), the
`localhost` authority when the payload describes neither -- the same
CLI-simulation posture the SAPI twin takes for a hostless server
array. The joined scheme is the plain one the worker's own socket
speaks: TLS termination is upstream of every worker runtime, and
honouring `X-Forwarded-Proto` is a trusted-proxy policy decision the
security slices own -- never a default an extraction silently makes
(the `ServerUri` doctrine, replayed on the worker twin). Two Host
values are the impossible line the authority grammar refuses -- the
degraded-line doctrine, fail-closed through the layer's own grammar.

## KDD-WK7 — The bags are the derivations the engine would have performed

The SAPI engine decodes the query string into `$_GET` and the `Cookie`
header into `$_COOKIE` before the application ever runs; a worker
runtime hands both still on the wire. The folds replay those
derivations with the engine's and the reference's own machinery:
`WorkerQuery` is `parse_str()` over the URI's query component (the
routine the SAPI's own `$_GET` build rides -- the numeric-string key
fold, the bracket nesting, the `max_input_vars` ceiling, all the
engine's own), and `WorkerCookies` is the pair grammar the reference
spelled and the CSRF family already carries (`CsrfCookieValue`,
move-verbatim): pairs split on `;` and trimmed, each pair split on the
first `=` only (values may themselves carry `=`), the value trimmed --
the name keeping its own bytes, a bare token answering the
empty-string value, a nameless pair contributing nothing, a repeated
name answering its LAST occurrence (the fresh value shadows the stale
one, the wire's own left-to-right order, across values exactly as
within one). The parsed body deliberately starts null for EVERY
content type -- the worker has no engine-decoded form bag the SAPI's
`$_POST` twin would carry, so the decode belongs downstream where
`FormBodyParser` already owns the url-encoded non-SAPI path. The
upload tree starts empty: a worker protocol that ever carries parsed
uploads widens the payload with its own upload bag, and until then the
multipart DoS posture is the ingress budget plus the glue's own
ceilings.

## KDD-WK8 — The difficulty ceiling forced the peer fold out

The first cut judged the peer address inside `WorkerConnection`, and
the archeology gate refused it at difficulty 21.58 against the ceiling
of 20 -- the fold-count doctrine the durable-store slice spelled
(EA11a/ED11a replayed): the address grammar and the entries injection
are two questions, and the ceiling is the third voice that names them.
`WorkerPeerAddress` now owns the literal's grammar and its one
canonical spelling; `WorkerConnection` owns the port corridor and the
precise entries; the gate answers zero error-level findings with every
touched class under the ceiling. The Halstead lesson the router lane
recorded holds here too: difficulty counts distinct operands, and the
split that separates the questions also separates the operands.

## KDD-WK9 — The mutation ledger's two new entries carry their proofs

Three equivalence zones surfaced, and each got the verdict its
construction deserves rather than a blanket pattern: the byte
measurement in `WorkerBody::bounded` joins the `BodyBudget::refuses`
class (`mb_strlen($x, '8bit')` and `strlen($x)` answer the identical
count for every input -- the '8bit' encoding IS byte semantics, and
the character-side divergence is pinned by the multibyte boundary
test, so only the byte-equivalence is ignored); the two character-map
calls in `WorkerPeerAddress::judged` are ASCII by construction (a
literal that survives the engine's IP judgment carries ASCII bytes
only, so the character map and the byte map agree on every string the
fold can ever answer -- the RedactionCandidate measurement argument);
and the cookie pair split's literals were RESTRUCTURED rather than
ignored -- the explode limit moved to its own line and stays under the
mutator (the bag's values can legally carry `=`, so raising that limit
would truncate `token=b=c` at the second `=` and change the bag: the
kill is owned by the value-with-equals test), while the array_pad size
alone carries the line-scoped ignore (padding a two-arm list to three
appends an arm the destructuring never reads, for every pair shape).
One new psalm-baseline line freezes the deliberate mixed boundary
(`$value` in the header walk -- the UploadOrigin posture for a fold
whose whole purpose is refusing what the grammar names); one entry
SHRINKS -- `UriInterface::getAuthority` gained its first src consumer
and left the baseline, the #298/#303 lineage continued.

---

# The Outbound Half — The Wire Shape, The Skip-On-Invalid Fold, The Read-Whole Body

KDD-WK10..WK14 for the runtime adapter's outbound half (#944) — the lane's
last named open slice, the twin the inbound `WorkerRequestFactory` (#638)
answered on the other side of the request cycle: where the inbound lane
converted the wire shape the deployment's glue hands the application, the
outbound lane converts the application's PSR-7 response into the wire shape
the glue hands its runtime. `feat/worker-response-emitter`.

**Verified locally before the record commit:** the four outbound batteries
over the layer's own doubles (46 tests / 97 assertions green under the
sandbox's PHP 8.4), the full HTTP suite (2330 tests green over the anchor
fix), and the whole-tree suite answering green everywhere the slice
touches (the six `ErrorsReplayTest` failures the sandbox answers are
main's own environment shape, reproduced byte-identically on a clean
`main` checkout — the subprocess lanes' cwd sensitivity, never the tree).

## The map

| Unit | Question it owns |
|---|---|
| `WorkerResponse` | the wire shape a worker runtime hands back, every field judged at construction |
| `WorkerResponseHeaders` | the foreign response's header map re-judged, the unacceptable members skipped |
| `WorkerResponseBody` | the payload rules and the read-whole pump onto the resident string |
| `WorkerResponseEmitter` | the composition root: one response, one wire shape |

## KDD-WK10 — The vocabulary is ours, both directions now

KDD-WK1 replayed outbound: the deployment's glue answers its runtime with
this vocabulary, never a runtime SDK type crossing into `src/`.
Construction refuses what cannot become a response — the KDD-WK2 doctrine
mirrored: `HttpStatusLine::assertCode` the corridor, `assertPhrase` the
byte grammar, `WorkerHeaders::normalize` the one canonical form the worker
lane owns BOTH directions through, and `HttpVersion::assert` the PSR-7
spelling. The body rides as the binary-safe string the wire shape is: no
length claim to reconcile at the constructor, the payload's own bytes the
whole truth — the inbound twin's `php://memory` posture mirrored (a worker
runtime carries the answer as one payload, so the outbound wire is a
resident string, not a stream: the inbound lane judged the payload's known
byte length at the boundary; the outbound lane answers the same
known-length payload back).

## KDD-WK11 — Skip-on-invalid on the wire, fail-closed on the status

Two doctrines meet at the emitter, each answering its own input class. The
foreign response's header fields re-judge and SKIP — never trusted, never
fatal — through `WorkerResponseHeaders`, the `EmittedHeaders` policy
replayed on a payload no SAPI ever touches: one attacker-shaped header
must not cost the answer its remaining, well-formed fields, a non-string
member dropping exactly like an invalid name or value. The STATUS itself
refuses fail-closed: a worker wire shape has no implicit SAPI default to
fall back on (the SAPI twin's skip leaves the implicit 200 standing; a
payload cannot carry a default posture silently), so a foreign status
outside the 100..599 corridor is the programming defect the typed refusal
names, for the glue to catch and answer. The reason phrase rides the
skip-on-invalid doctrine through the new
`HttpStatusLine::phraseIsEmittable()` infallible gate — the
`HeaderGrammar::acceptable()` shape, the pattern the one `PHRASE_PATTERN`
const both spellings now share: a phrase the status-line byte grammar
refuses answers the registered phrase for the code (or an empty phrase) —
never the bytes that would corrupt any status-line serialiser the runtime
might run.

## KDD-WK12 — The SAPI ordering and framing, replayed on a string destination

The emitter rewinds the seekable body first (a drained body never answers
empty — middleware that inspects the body leaves the cursor at the end),
threads the framing through the existing `StatusFraming` fold (the lying
Content-Length reconciled away before any field reaches the wire, the
1xx/204 statuses losing the framing header outright — RFC 9110 section
8.6, the fold reused verbatim), and reads the body whole through
`WorkerResponseBody` — the `EmittedBody` doctrine onto a resident string:
the 1xx/204/205/304 corridor, the unreadable fail-closed, the fixed-size
chunk pump with the defensive empty-read stop (the lying-eof engine
disagreement), the read starting at the cursor the emitter left exactly
as the SAPI twin's body path does. The `ContentLength` reconciliation
rides the rewound cursor's remaining length, the fold's own arithmetic
untouched.

## KDD-WK13 — The gate round's own lesson: the anchor the battery caught

The phrase pattern the gate lifted out of `assertPhrase` carried PCRE's
bare `$` anchor, which also matches immediately before a final newline —
the anchor class #381 (the constraint's closing anchor) and #867 (the
event family's D-modifier sweep) had each caught before, and the slice's
own battery caught it a third lane over: the constructor test feeding
"OK\n" expected the typed refusal and main's `assertPhrase` answered
acceptance, the trailing newline riding the canonical spelling past the
byte grammar. The pattern const both spellings share now carries the `\z`
anchor (the absolute end), the battery's line-feed arm the kill, and the
pattern living in ONE const so the assert and the gate can never drift
apart on the question — the same single-source-of-grammar discipline the
correlation id's claim grammar carries.

## KDD-WK14 — The posture: no knob, no widening, one bench subject

No config knob lands (the worker lane's own recorded posture — the
inbound twin's doctrine, the runtime SDK stays out and the glue owns the
composition), no deptrac widening (the four folds live inside the `Http`
layer's existing ruleset, every refusal answering the layer's own
`HttpException` family), and the `HttpHotPathBench` gains the outbound
round-trip subject beside the inbound twin's — the lane's adapter story
complete, both runtime directions answering, the shim row the roadmap
meter records turning Implemented.
