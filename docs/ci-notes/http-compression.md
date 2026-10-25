# HTTP Output Compression Engine — KDD

**Scope:** the `http.compression` family — ROADMAP.md "HTTP Layer" Phase 3.2
(the readiness matrix's last open ❌ row: "Compression ❌ Belum → ✅ Streaming
Negotiation"). Thirteen source classes over the vendored contracts, one
middleware family in the strict-reading lineage, `feat/http-compression`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5014 tests / 12440
assertions; smoke 20000 iter / 2760 B; concurrency 23 workflows; audit 0
advisories) · psalm 0 errors (99.73% inference) · progpilot `[]` · scoped
infection 196 mutants, MSI 100/100, 0 escapes (1 timeout — the spin mutant,
below) · archeology 0 error-level findings after the fold · bench subject
green at ~58.3 μs per compressed 5.6 KB answer (±0.23%, 500 revs).

## The family

| Unit | Question it owns |
|---|---|
| `CompressionKnobs` / `CompressionPolicy` | the strict knob reading (`http.compression`: `enabled`, `minimum_bytes` 1..1048576, `level` 1..9) |
| `HttpFamilies` | the `http` map's family census — the second family is the moment the census exists (KDD-CP10) |
| `AcceptEncodingQualities` | the `Accept-Encoding` grammar (bounded 8192, coding→q map, weight-grammar OWS read, malformed members skipped) |
| `CompressionCapabilities` | what the family's fold can produce (gzip+deflate over the bundled zlib engine; `br` unadmitted until the fold speaks it) |
| `CompressionNegotiator` | the preference walk: roadmap order `br` > `gzip` > `deflate`, ∩ client grant ∩ capability |
| `ContentCoding` | the vocabulary: preference table + zlib windows (`gzip`=31, `deflate`=15 — the RFC 9110 zlib reading) |
| `CompressedBody` | the streaming fold: 8192-byte chunk reads → `deflate_add()` → `php://temp/maxmemory:65536` |
| `CompressionEligibility` | whether a response earns the engine at all (status, existing encoding, threshold) |
| `CompressedResponse` | the answer construction (body swap, coding declared, stale CL dropped, `Vary` merged) |
| `CompressionVary` | the `Vary: Accept-Encoding` token walk (first-spelling dedup, idempotent) |
| `CompressionMiddleware` | the PSR-15 boundary; `CompressionFactory` the config seam |

## KDD-CP1 — the zlib seam is incremental, and PHP's own signature surprised

The roadmap asks for *streaming* compression, and the engine seam that
delivers it is `deflate_init()` + `deflate_add()` — not the one-shot
`gzencode()`. Two signature facts the first cut got wrong and the engine
corrected: `deflate_init(int $encoding, array $options = [])` takes the
window bits as its FIRST argument (`['level' => N]` in options, window 31
for the gzip wrapper, 15 bare for HTTP-`deflate`'s zlib wrapper), and the
flush constants are the `ZLIB_*` family (`ZLIB_SYNC_FLUSH` per chunk,
`ZLIB_FINISH` for the trailer) — `DeflateContext::FINISH` does not exist
(PHP 8.4 verified). The FINISH call is not optional: without it the
wrapper's CRC and size trailer is never written, and no decoder accepts
the stream.

Each chunk is flushed (`ZLIB_SYNC_FLUSH`): a flush point costs some ratio
but bounds the destination's latency and keeps the walk's memory flat —
the same trade the emitter's chunked `echo` makes. The destination is
`php://temp/maxmemory:65536`: the compressed payload lives in memory to
its 64 KB allowance and spills to disk beyond it — Phase 1.1's
memory-safe-buffering posture replayed for the compression lane.

## KDD-CP2 — the capability probe is honest about the fold, the preference table is the roadmap's

The preference order `br` > `gzip` > `deflate` is the roadmap's own.
`CompressionCapabilities::detect()` answers the family's own fold, not
the function table: `CompressedBody` folds through the zlib engine's
incremental seam, so the probe admits `gzip`+`deflate` over
`deflate_init()` and nothing else. Brotli exists only behind a PECL
extension, and the extension's presence is not a capability this
family owns -- the fold has no brotli seam to ride, so admitting `br`
would hand the negotiator a coding whose first eligible body answers
500 (the round-3 audit's R3-2: ext-brotli build + browser clients +
one compressible response = a guaranteed failure; CI's stock runners
never saw it because they carry no brotli). The negotiator walks the
preference order ∩ the probe's answer: `br` never wins however loudly
the client asks, until the fold grows a brotli seam and the probe grows
its admission with it. The probe is injectable (the negotiator takes
the list), so the capability-absent posture — an empty list that must
answer identity — is pinnable without uninstalling an extension.

## KDD-CP3 — identity is served, never 406'd

A client that refuses every producible coding — including
`identity;q=0` — is served identity, not a 406. The router's 406 arm
exists for `Accept`/`producible-media-type mismatches the table can prove;
replicating it for `Accept-Encoding` is out of the v1 scope (the roadmap's
Phase 3.2 does not ask for it, and the reference engines do not send it).
Deliberate, recorded here.

## KDD-CP4 — `Vary` is stamped on the compressed answer, minimal-correct

A compressed representation is one of several the same URL serves, chosen
by `Accept-Encoding` — the `Vary: Accept-Encoding` line on the COMPRESSED
answer is what stops a cache storing gzip bytes under the bare URL and
serving them to an identity client (the poisoning direction). The
identity passthrough deliberately carries no `Vary`: identity is
acceptable to every client, so the identity variant is safe to store under
the bare URL. The full-fidelity posture (varying even the identity answer
when the family is armed) is a documented future tightening, not a v1
claim. The walk itself is `VaryTokens`'s own shape — first-spelling dedup,
empty members skipped, idempotent — for the encoding token.

## KDD-CP5 — the stale `Content-Length` is dropped at the response level

The handler's `Content-Length` describes the uncompressed bytes; after the
body swap it is a lie. It is dropped (`withoutHeader`) rather than
recomputed: the emitter walks the body in chunks and reconciles framing
itself, and a response object whose declared length matches its actual
body is consistent for every downstream consumer — the head-view doctrine
(ZEF-DEEP-05) replayed for the compression lane.

## KDD-CP6 — a stream that cannot report its size passes the threshold

`getSize() === null` (a non-seekable stream mid-flight) compresses without
the threshold's protection: buffering the whole body to learn its size is
the memory posture this family exists to avoid, and an unknown-size body
is a streaming response the operator chose. Pinned by test over the
lying-size double.

## KDD-CP7 — the mutation campaign: equivalence settled by construction, not by ignore

196 mutants, all killed or timed out, zero escapes, zero new ignores. The
first round's 19 escapes fell to three moves:

**Settled by construction** (the honest seam-redesign the lineage
demands): the `explode(';', $member, 2)` limit — element-[0] reads are
limit-invariant, so the limit was dropped entirely (#571's own lesson);
the redundant `if ($member === '') return null;` guard — the token regex
refuses the empty string with the same null, so the guard was deleted, not
ignored; the negotiator's `if ($acceptEncoding === '') return null;` —
the empty header parses to the empty map which walks to null, same
refusal; `'php://temp/maxmemory:' . self::TEMP_MEMORY` — the concat
operand became one const string with nothing to remove.

**Killed by tests that pin the semantics the mutants would silently
flip**: the max-fold (`gzip, gzip;q=0.5` — the earlier 1.0 survives); the
quality anchor (`q= 1` — a spaced value is not a quality; the caret the
mutant removes is what refuses it, and PHP's float cast would otherwise
mask the difference by casting `' 1'` to 1.0); the multibyte bound (`é`
padding — the ceiling counts CHARACTERS, so a 10 KB header of two-byte
characters is within it while the byte-scale slice the mutant spells is
not); the lengthening fold (`İ` padding — the full case fold that grows
its input carries the tail past the ceiling, the byte-scale fold cannot);
the multibyte Vary dedup (`Ärger, ärger, ÄRGER` — the character-scale fold
recognises every case pair); the empty-map defaults (`fromMap([])` — the
`?? 1024` map-defaults path, not the constructor's); the unspelled
preference without a wildcard (the `?? 0.0` fallback — `gzip;q=0` with
only `deflate` producible answers identity); the lying-eof defensive stop
(the `Break_` mutant spins forever below a lying not-at-eof and is killed
by timeout — the one timed-out mutant in the run, which is the kill).

**One real bug the static gate caught before any reviewer**: psalm's
`PossiblyUnusedMethod` on `CompressionNegotiator::__construct` exposed
that the middleware's negotiator parameter defaulted to `null` — a
default-null negotiator under a null-safe call means the factory-built
middleware NEVER negotiates, never compresses, and every test that passed
one explicitly was testing a different composition than production would
build. The constructor now defaults to a live negotiator over the engine's
own probe. `findUnusedCode` was not debt theater here; it was the bug's
only witness.

## KDD-CP8 — the archeology fold: two questions, two files

The first middleware cut measured difficulty 28.73 against the ceiling of
20. One fold was not enough (22.00). The settle that fit: the eligibility
question and the answer construction are SEPARATE questions and belong in
separate files — `CompressionEligibility` (9.00) owns whether a response
earns the engine, `CompressedResponse` (6.38) owns the compressed answer's
shape, and the middleware (18.50) owns only the walk. Same lesson as the
export lane's `OpenApiOperationAttributeDeclaration`: a fold is a new
file, not a new method in the busy one.

## KDD-CP9 — the `http` map gets its family census

`HttpFamilies` (`error_handling`, `compression`) follows `SecurityFamilies`'
own doctrine: the census "belongs to the PR that introduces a second
family to know it" — the error family landed first, the compression family
is the second. The error family's own factory still reads its knobs through
the repository's dotted addresses (its strict reading predates the seam);
its migration to the family seam is a one-line follow-up its own lane owns.
The census already protects the map for every boot that resolves a family
through it: an `http.compresssion` typo halts the boot with the known
families spelled out.

## KDD-CP10 — the wiring rides the existing seams only

`http.compression` service registration ahead of the composition (the
timing family's precedent — engines registering before their producers);
NOT a member of the shipped `middleware.stack` (the stack's membership is
the host's declaration); the profile block in `app/configs/http.php`
mirrored and pinned by test against `CompressionPolicy::fromMap(null)`;
the boot test's expected services map widened by one entry. No new layer,
no deptrac change, no dependency change: `require` stays `{"php":"^8.4"}`
(zlib is the bundled engine the capability probe reads, fail-closed to
identity without it — never a composer entry).

## What remains open in the HTTP lane

The XML body-parser strategy (Phase 3.1), the cookie/session abstraction
(Phase 3.3), the slow-request threshold (Phase 4.1), the CLI raw-dump
inspector and header validator (Phase 4.2), and the worker-context
adapter (the shim roadmap's Phase 2.2 — the ecosystem §5.1 lane's own
dependency decision). The readiness matrix's Compression row is closed.
