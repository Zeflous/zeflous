# HTTP Audit — KDD

**Scope:** issue #801's six minor findings across the HTTP lane -- the
head fold's lost length, the non-seekable reconciliation's stale
blessing, the unbounded userland form decode, the slow-request
bracket's placement dependence, the compression walk's server-first
override, and the session lane's missing identifier rotation --
inspected against `main` at `fae61e3c` (2026-11-20), the closing
rounds the `#768-#789` audit wave's leftovers named.

## KDD-HA1 — the head fold carries the GET twin's length (finding 1)

The router's fold emptied the body for HEAD (and 304) answers, so the
emitter's own reconciliation -- measuring the stream, not the wire --
dropped the `Content-Length` the mismatch created: RFC 9110 section
9.3.2's SHOULD (a HEAD answer carries the GET twin's fields, the
length clients timing HEAD utilisation read) was answered with no
length at all, and an outer middleware (the ETag hasher) met the
emptied body besides the fold's own ordering promise.

The fix gives the head view its own body: `HeadBody` answers the
representation's length as its size and no content at all, so the
emitter's length reconciliation -- measuring the stream, not the
wire -- KEEPS the framing header the empty swap's zero-size stream
made it drop, and a verbatim forwarder ships a header that tells the
truth about the GET twin. The view's own stamp replaces whatever the
handler declared (the measured truth when the measurement exists, the
honest strip when it does not, the guarded non-seekable body losing
the field exactly as finding 2's own doctrine spells), and a status
the framing doctrine itself forbids the field on (the 1xx and 204
classes, section 8.6's MUST NOT) or the 304 (whose field-value is the
would-be 200's own length, a body the conditional fold already
suppressed -- the zero the emptied stream measures is that length's
own lie) never earns one.

**Scoping lesson, recorded.** The 304 twin -- keeping a truthful
`Content-Length` on the not-modified answer, the first draft's
`StatusFraming` arm -- was deliberately NOT taken: the issue's own
direction names the HEAD fold alone (its "arah perbaikan" reads
"simpan/rekonsiliasi CL GET sebelum pengosongan body HEAD"), section
9.3.2's SHOULD has no 304 counterpart (section 8.6 grants the 304 a
MAY, and the fold's strip is the safe answer it always was), and the
half-taken arm would have had the emitter re-judge what the protocol
suppressed. The draft ran, was reset onto the round's own base, and
the lesson rides here.

## KDD-HA2 — the unmeasurable cursor loses the field (finding 2)

`ContentLength::reconcile` answered a NON-seekable stream's full size
as its remaining length, so a partially-consumed foreign stream's
stale size blessed a declared `Content-Length` larger than the octets
that would ever leave the process -- the client hang the
reconciliation exists to prevent. The unmeasurable cursor is now the
unmeasurable size's own family: the remaining length is measured only
when both the size and the cursor answer, and every other shape
refuses the field honestly. The battery's own pin flipped with the
doctrine: the guarded non-seekable body loses its declared field even
when it happens to match the size, because a match nobody measured
is not a verdict.

## KDD-HA3 — the userland decode owns the pair bound (finding 3)

The form strategy decoded its budget-bounded body through
`parse_str()` with no pair bound of its own -- the SAPI decoder's
`max_input_vars` posture absent from the userland lane, an ampersand
storm inside the two-mebibyte budget decoding into a quarter-million
array entries (the memory amplification the SAPI exists to stop,
reproduced inside the process). The strategy now censuses the raw
pair count BEFORE any decode allocates (`pairCount()` -- the engine's
own byte scan, no member array ever built), refusing over-bound
bodies with the malformed-body signal, and the replay lane's own form
walk reads the same bound with its own failure posture: the over-bound
capture answers not-replayable rather than throwing, the failure lane
that family exists to serve never throwing over a body it merely
records. The bound's own edge is pinned both ways: a body of exactly
the thousand pairs parses (and replays), the refusal starts one pair
beyond.

## KDD-HA4 — the total duration measures from the ingress (finding 4)

The slow-request bracket surrounded only the span its own middleware
saw -- the pipeline below it, the ingress guards and every outer
layer excluded -- so the "duration" the operator read was
placement-dependent, not apple-to-apple across deployments. The
canonical start point is now the stamp the ingress factories
themselves write onto the request (`REQUEST_START_ATTRIBUTE`, a
monotonic reading at the construction seam -- both the SAPI twin and
the worker lane's own spelling, one point across both ingress
lanes), and the middleware measures from the stamp when one rides,
keeping the local bracket as the honest fallback for a foreign
request the ingress never decorated.

## KDD-HA5 — the client's quality decides the compression (finding 5)

The coding walk followed the server's preference order and answered
the first coding above zero -- a client that spelled `deflate;q=1,
gzip;q=0.1` was answered gzip, the server's preference overriding the
client's explicit quality, the deviation the first docblock recorded
as deliberate. The walk now answers the producible coding whose
granted quality is the HIGHEST (the client's voice, section 12.5),
the server's preference breaking ties alone -- and the wildcard's
own grant survives: an unspelled producible coding still rides the
wildcard's quality, never the casualty of the retirement.

## KDD-HA6 — the session identifier rotates (finding 6)

The lane had no `regenerate()` spelling at all (the audit's grep:
zero hits), so an identifier minted pre-login rode the post-login
session -- the OWASP fixation window. The state now carries the
rotation (`SessionState::regenerate()`): the identifier swaps through
the resolution's own mint (kept at construction, so an unrotatable
state refuses LOUDLY rather than degrading into the silent no-op a
privilege change must never read), the data deliberately kept
(erasure stays destruction's own verdict), one rotation per request,
and the settlement destroys the retired entry, saves the bag under
the fresh identifier, and re-issues the cookie carrying it -- a
destruction after a rotation sweeping both identifiers whole.

## The round's own gate lessons

The rewrite cost the round its own lesson: the first draft's head
view reconciled the declared field against the full body and then
emptied the stream -- the RESPONSE object carried the truth, and the
emitter dropped it again at the wire (the reconciliation measures the
stream, not the response's history). The `HeadBody` view is the
lesson: a body whose SIZE answers the GET twin's length is the one
spelling that survives both the SAPI emitter and a verbatim
forwarder. The battery pins every new seam at its own edge (the
bound's boundary pair, the stamp's presence and fallback, the
rotation's idempotence and refusal), and the local gates ran green
before the branch moved (the full suite, PHPStan, Psalm, the fixer,
Rector).
