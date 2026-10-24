# HTTP Multi-Format Body Parser — KDD

**Scope:** the `http.body_parser` family — ROADMAP.md "HTTP Layer" Phase 3.1
(the multi-format body parser: the strategy pattern, the `Content-Type`
auto-negotiation, and the multipart posture the bullet list asks about).
Thirteen source classes over the vendored contracts, one middleware family
in the strict-reading lineage, `feat/http-xml-parser`.

**Verified locally before the PR:** `ci:static` EXIT-0 · psalm 0 errors ·
progpilot `[]` · scoped infection over the family's source files, MSI
100/100, 0 escapes · archeology 0 error-level findings · bench subject
green (the JSON decoration over a 5.7 KB document).

## The family

| Unit | Question it owns |
|---|---|
| `BodyParserKnobs` / `BodyParserPolicy` | the strict knob reading (`http.body_parser`: `enabled`, `json_depth` 1..512, default 32) |
| `HttpFamilies` | the `http` map's family census — the third family joins the list the census spells out |
| `BodyParserInterface` | the strategy contract: `supports()` owns a media-type grammar, `parse()` answers the PSR-7 parsed-body shape |
| `MediaTypeSuffix` | the structured-suffix grammar: `application/<anything>+<suffix>`, byte-wise case-insensitive, non-empty subtype required |
| `JsonBodyParser` | `application/json` + `application/*+json`: the associative decode, the depth ceiling, the two refusals |
| `XmlBodyParser` | `application/xml` + `text/xml` + `application/*+xml`: SimpleXML over libxml, the XXE posture, the extension probe |
| `FormBodyParser` | `application/x-www-form-urlencoded` on the non-SAPI path: the engine's own `parse_str()` decode |
| `TextBodyParser` / `TextBody` | `text/plain`: the value object a scalar document legally rides |
| `RequestBodyParsers` / `StandardBodyParsers` / `RequestMediaType` | the negotiation table (declaration order, first owner wins), the shipped family's manifest (the four strategies, the JSON one alone reading the policy), and the request's own media type (the factory's Content-Type spelling lifted to the seam) |
| `ParsedBodyReader` | the request-level walk: the no-type-line and already-parsed gates, the non-consuming capture, the owning parser's answer |
| `MalformedBodyException` | the malformed-document refusal (the coarse-tier 400, below) |
| `BodyParserMiddleware` | the PSR-15 boundary (the arm and the decoration); `BodyParserFactory` the config seam |

The three-seam settle is itself a lesson: the first fold answered
SonarCloud's S1142 (the four-return private walk) by moving the whole
walk into the negotiation table -- and the archeology ceiling refused
THAT (the table's Halstead difficulty 22.67 > 20), the
media-type-seam and manifest extractions alone unable to bring it
back under (the algebra cuts distinct operands faster than total
ones), and the walk's return to the boundary pushed the boundary one
point over in turn (21.00). The settled shape gives each question its
own seam -- the media type, the manifest, the table, the reader, the
boundary -- with difficulties 1.80 / 1.50 / 6.00 / 12.00 / 15.71.

## KDD-BP1 — the strategy face is named by the house convention, not the roadmap sketch

The roadmap prose sketches `BodyParserStrategy`; the interface that landed
is `BodyParserInterface`. The repository's convention is the `*Interface`
suffix (`RateLimiterInterface`, `ReplayProtectorInterface`, the vendored
`MiddlewareInterface` itself), and the roadmap's sketch names are sketches:
the same prose sketches `ZefStream` and `ZefRequest`, and the layer that
landed answers `Stream` and `Request`. Consistency with the convention a
hundred call sites already spell beats fidelity to a sketch.

## KDD-BP2 — multipart is the SAPI's seam, and the DoS posture in force is the one that already shipped

The family does not own `multipart/form-data`, deliberately. The SAPI
consumes `php://input` for a multipart request before userland runs — the
engine decodes the parts into the `$_POST`/`$_FILES` bags subject to its own
INI ceilings (`upload_max_filesize`, `max_file_uploads`, `post_max_size`),
the request factory honours that decode for the parsed body, and a
userland multipart parser could only ever serve a synthetic request no
real pipeline delivers. The DoS posture actually in force is the one the
earlier phases shipped: the ingress body budget (the eager
`Content-Length` claim and the lazy limited stream, 413 on breach), the
engine's upload ceilings during the SAPI decode, and the upload-tree
normalisation. The middleware's already-parsed gate preserves the SAPI's
decoded body untouched, so the family composes cleanly under the standard
path without ever re-reading a multipart stream.

## KDD-BP3 — the XML extension probe refuses fail-closed, not gracefully

The compression family's brotli probe bypasses gracefully when the
extension is absent — a substitute coding exists to negotiate down to.
XML has no substitute: the honest options were a silent no-parse or a
loud refusal, and the silent one is the exact misconfiguration trap the
knob-census doctrine exists to refuse (an operator who composed the
family for XML bodies believes parsing is live while every XML request
silently arrives unparsed). The probe is taken at construction,
injectable for the pin, and an XML body on a build without SimpleXML
answers a fail-closed `HttpException` naming the missing extension. A
deployment that never receives XML never pays for the extension it lacks.

## KDD-BP4 — the XXE posture is the engine's own hardening, kept, and pinned

No `LIBXML_NOENT` (external entities are never substituted into the
tree), `LIBXML_NONET` on every parse (no network fetch a DTD or entity
reference could trigger), and no `LIBXML_PARSEHUGE` (the libxml depth,
text-node and entity-amplification ceilings stay in force). The pins:
an external-entity document parses with the entity resolving to empty
(the `file:///etc/passwd` content never enters the tree), and an
entity-amplification document whose expansion crosses the engine's own
text ceiling is refused (the pinned 10^7 chain — 518 source bytes that
expand past the ten-megabyte text limit every libxml2 release since
2.9 enforces without `PARSEHUGE`). WHICH ceiling fires varies by
build: the legacy 2.9 series answers its entity-reference-loop
heuristic on the nesting alone, the modern series lets the expansion
accounting refuse — and the first CI round caught exactly that lesson:
a small nested-entity chain (expansion in the kilobytes) is refused by
the legacy heuristic but is a legal document the modern engine parses,
so it is deliberately pinned on neither side. The refusal is the
universal observable; the ceiling that answers it is the engine's own
business. The internal-errors mode is process-global engine state, so
the parse restores the prior mode and drains the error queue in a
`finally` — a persistent worker never observes a leaked diagnostic
mode or a stale libxml error, and both facts are pinned under the mode
the caller left on (a mode-restore to false makes the queue read empty
on every build — the pin had to observe before the restore).

## KDD-BP5 — the two JSON refusals, and the null document's honest answer

A malformed document refuses with `MalformedBodyException` carrying the
engine's own `JsonException` as the previous throwable — the attribution
survives for whoever logs the refusal. A well-formed document whose top
level is a scalar (`5`, `"text"`, `true`) refuses the same way: the PSR-7
parsed body is array, object or null, and the associative decode folds
every legal JSON document into an array EXCEPT the scalars — silently
nulling a scalar would tell the controller "no body" about a body that
arrived. A well-formed `null` document answers null — the triple's own
third member, and the honest answer for a document that declares the
absence of any payload. The depth knob's engine semantics were probed
empirically before pinning: N levels of containers need a depth
ceiling of N+1 (the top-level container consumes one unit), which the
corridor 1..512 passes through to the engine's own maximum.

## KDD-BP6 — the parse never destroys the request it was handed

The body is read through the non-consuming snapshot
(`StreamSnapshot::capture()`): the cursor is restored to where it stood,
so a controller that prefers the raw stream still reads the whole body
after the family parsed it — pinned by the middleware test that rewinds
and re-reads the observed request's body. A stream that cannot be
captured (neither tellable nor restorable — the piped-body shape) passes
the request through rather than draining it: the parser's refusal to
destroy the request outweighs its eagerness to parse it, and the body
stays deliverable downstream.

## KDD-BP7 — another seam's answer is never re-parsed

A request whose parsed body is already set passes through the family
untouched, before the strategy table is even consulted (pinned with a
spy table that is provably never asked). That gate is what preserves the
SAPI's form decode, the host that parsed the body itself, and any
upstream middleware's decoration — the body-parser composes under
answers that already exist instead of competing with them.

## KDD-BP8 — the structured suffix walks the `application/` tree only

`application/vnd.api+json` and `application/atom+xml` are owned;
`image/svg+xml` and `text/*+xml` are not. The suffix grammar is the
replay lane's own house spelling (`ReplayBodyDecoder`): the structured
suffix is honoured under `application/` alone, an image type is not a
document API type, and a `text/` member with a suffix is not a
registered XML document family. The grammar is byte-wise
case-insensitive (`strcasecmp`) over the two ASCII segments — the same
fold the request factory's two form media types make — and the subtype
between the prefix and the suffix must be non-empty: `application/+json`
owns nothing.

## KDD-BP9 — the malformed refusal joins the error map's coarse tier, deliberately

`MalformedBodyException` extends `HttpException` and stops there: it does
NOT implement `CarriesHttpStatus`, because the 400 it must reach is the
coarse status the error map's second tier already answers for every
`HttpException` — the typed tier exists for the statuses that differ
from the coarse default (`PayloadTooLargeException` 413). The class
still earns its name: a malformed body is a distinct refusal hosts catch
structurally, and the message-sniffing the typed tier retired never
returns. The refusal fires BEFORE the handler runs — a 400 the error
boundary answers at the boundary, instead of a controller faulting one
layer deeper on a null it was never told about.

## KDD-BP10 — the form strategy serves the non-SAPI path only

`RequestFactory` already honours the SAPI-decoded `$_POST` for the two
form media types, so the form strategy's honest role is the request that
arrived with its form body still on the stream: the explicitly-sourced
twin (`fromServer` with no parsed body), or a foreign PSR-7 request the
ingress guard hands the application. The decode is `parse_str()` over a
body the ingress policy already budget-bounded — the same seam the
replay lane reads over the same budget.

## KDD-BP11 — the empty-body gate, and what the family never throws at

An empty body (`text/plain` or otherwise) passes through with the parsed
body null: an empty body is not a malformed document, and the SAPI does
the same for an empty form. A request with no `Content-Type` line owns
no parser. A media type the family does not own passes through —
multipart, `application/octet-stream`, `image/svg+xml` alike. The
refusals the family throws are exactly two: a malformed owned document,
and (on an XML-owning runtime without the extension) a body the runtime
cannot parse. Everything else is a pass-through the handler answers as
the handler's own.

## KDD-BP12 — the coverage round's lesson: the covers filter drops undeclared collaborators

The gate round after the slice's landing taught the covers-filter
lesson the injector lane already paid for (KDD-C4.16's round): with
`requireCoverageMetadata` on, phpunit filters per-test attribution to
the test's own covers list — a collaborator the walk executes but no
test declares reads as ZERO in the clover, and SonarCloud's
new-code gate prices it as uncovered. The slice's three static seams
(`StandardBodyParsers` the manifest, `RequestMediaType` the type
segment, `BodyParserFactory` the config seam) were executed by the
very tests that shipped with the slice; they were never declared, so
the gate saw 16 uncovered lines (89.19% on new code, floor 90%). The
honest fix is declaration plus the seams' own pins, not coverage
theater: the table test declares the manifest (its routing assertions
already pin all four strategies), the reader test declares the segment
seam and gains the walk's null answer plus the OWS trim the grammar
promises, the boundary test declares the walk's collaborators the
compression family's own covers list taught the pattern for, and the
boot test declares the factory the container resolution already
executes — with a dedicated factory test whose depth-knob refusal
kills the default-policy bypass a resolution assertion alone cannot.
The mutation ledger follows the coverage: a line the covers filter
drops is a line infection never mutates, so the newly declared seams
brought 21 fresh mutants with them — all killed by the same round's
tests, none ignored.
