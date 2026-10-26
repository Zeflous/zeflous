# HTTP Session & Cookie Abstraction — KDD

**Scope:** the `http.session` family — ROADMAP.md "HTTP Layer" Phase 3.3
(the stateless cookie helper with the secure-default flags, and the session
adapter interface the hexagonal storage promise spells) plus the
Zero-Library Shim's own Phase 3.2 pair (the secure cookie builder and the
`session_id()` replacement that never starts a native PHP session).
Fourteen source classes over the vendored contracts, one middleware family
in the strict-reading lineage, `feat/http-session-cookies`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5,233 tests /
12,828 assertions) · psalm 0 errors · progpilot `[]` · scoped infection
over the family's source files, 325 mutants, MSI 100/100, 0 escapes ·
archeology 0 error-level findings · bench subject green (the live
session round trip, ~6.6µs per request).

## The family

| Unit | Question it owns |
|---|---|
| `SessionKnobs` / `SessionPolicy` | the strict knob reading (`http.session`: `enabled`, `cookie_name`, `idle_timeout` 1..2592000, `id_bytes` 16..64, `cookie_secure`, `cookie_http_only`, `same_site`) |
| `SessionBounds` | the two integer corridors, asserted at the policy's constructor (the `CsrfBounds` pattern) |
| `SessionCookieName` / `SameSiteVocabulary` | the two vocabulary folds (the RFC 9110 token name; the SameSite words — the vocabulary fold the duplication gate later paid into one shared reading, `docs/ci-notes/qa-gates.md` KDD-QA3) |
| `SessionId` | the identifier core: issuance over injectable `random_bytes`, the incoming grammar's alphabet and length corridor |
| `RequestCookies` | the stateless helper's request half: the fail-closed read and the symmetric write over the PSR-7 cookie bag |
| `SetCookie` | the response half: the secure-default cookie value object and its `Set-Cookie` renderer, deletion included |
| `SessionAdapterInterface` / `InMemorySessionAdapter` | the hexagonal storage port and the zero-dependency driver that proves it |
| `SessionState` | the request-scoped bag: the identifier, the data, and the three flags the outbound walk reads |
| `SessionResolution` / `SessionSettlement` | the inbound walk (carried cookie → grammar → storage → mint) and the outbound walk (destroy / issue / write-only refresh) |
| `SessionMiddleware` | the PSR-15 boundary (the nineteenth face): the arm, the two decorations |
| `SessionFactory` | the config seam the `http.session` service registration names |

## KDD-SC1 — the helper and the port are named by the house convention, not the roadmap sketch

The roadmap prose sketches a "Stateless Cookie Helper" and a "Session
Adapter Interface"; what landed is the two halves spelled as their own
seams (`RequestCookies` the request half, `SetCookie` the response half —
reading a bag and answering a header line are different questions) and
`SessionAdapterInterface` per the repository's `*Interface` convention.
The same KDD the body-parser family recorded as BP1: the roadmap's sketch
names are sketches, and consistency with the convention a hundred call
sites already spell beats fidelity to a sketch.

## KDD-SC2 — the idle doctrine is write-only refresh, spelled as such

The idle timeout is the storage's own last-write clock: a request that
reads without writing does not extend the session. The alternative
doctrine — touch-on-read, where every armed request answers a storage
write to refresh the clock — is a real posture some deployments want,
and it is deliberately NOT grown speculatively: the family grows it as
a knob the day a consumer asks for it. The write-honest dirty flag is
the doctrine's own seam: a removal over an absent key never dirties the
bag, so the storage is never asked to persist a no-op, and the
middleware's clean-request pin proves the clock was not refreshed (the
frozen-clock test advances past the timeout from the ORIGINAL write and
observes the null).

## KDD-SC3 — a malformed carried identifier is an absent one, never a refusal

A stale, truncated or hand-mangled session cookie is a client condition
every deployment meets: the grammar refuses it before the storage is
ever asked (the recording adapter pins the empty load list), and the
request answers a freshly minted session instead of an error-boundary
refusal. The length corridor deliberately spans the whole entropy
corridor's spellings (22..86 characters over the base64url alphabet),
not the exact length this deployment issues: a valid-shaped identifier
the adapter does not know answers a fresh session anyway (the miss is
the truth), and a tighter grammar would buy nothing the adapter lookup
does not already decide.

## KDD-SC4 — the vendored cookie pair's first src consumer, the promised drop

The CSRF family reads its cookie through the `Cookie` header (the
reference implementation's own posture), so the vendored
`ServerRequestInterface::getCookieParams()` / `withCookieParams()` pair
had no src consumer at all — the two baseline entries the remaining-
findings round (#594) explicitly booked as "the cookie/session slice
next drops its pair". `RequestCookies` is that consumer, both halves:
the read walks the bag the request factory decodes `$_COOKIE` into, the
write is the symmetric fold a host uses to pass a cookie downstream
without a round trip. The pair's entries drop; the helper's own
host-facing write joins the baseline as the host API it is (the
`TextBody::$text` precedent), alongside the session bag's four
controller-facing members.

## KDD-SC5 — the secure defaults are the constructor's own, and the CSRF family is not touched

The cookie value object's defaults ARE the roadmap's promised trio:
`Path=/`, `HttpOnly`, `SameSite=Lax`, Secure armed — a host's plain
`new SetCookie('theme', 'dark')` answers the secure spelling, and
relaxing a flag is a declaration. The Secure attribute's emission stays
scheme-conditional with the one exception the browser contract forces
(`SameSite=None` carries Secure whatever the scheme), exactly the
posture `CsrfSetCookie` documents — but the CSRF family's own issuance
line is NOT folded here: it predates this seam, its attribute order is
the reference implementation's own provenance, and restructuring a
family this slice does not own is the one-way-composition discipline
every lane follows. The SameSite vocabulary folds stayed two
(`SessionSameSite` beside `CsrfSameSite`) for the same reason the
security map's census deferred its seam: the shared cookie-attribute
vocabulary is a fold a THIRD cookie-writing family's landing would
justify -- a deferral the duplication gate's own arrival later answered
(`SameSiteVocabulary`, `docs/ci-notes/qa-gates.md` KDD-QA3: two
byte-identical 38-line bodies are a clone regardless of how many knob
surfaces ask the question).

## KDD-SC6 — a destroyed fresh session still answers the deletion cookie

The edge the uniform verdict covers: a client carrying a now-expired
cookie mints a fresh session, and a handler that immediately destroys
it (a logout on the first request after idle) must still retire the
stale cookie the CLIENT still holds — the deletion line answers, the
unsaved entry is destroyed (the no-op it spells, pinned by the
recording adapter), and nothing is ever saved for it. Skipping the
deletion line because "the session was new this request" would leave
exactly the stale cookie that caused the mint.

## KDD-SC7 — the in-memory driver's scope is spelled, and the port is the way out

The shipped driver is process-local: the single-worker deployment, the
test suite, the local bootstrap. A multi-worker deployment pointing at
it holds sessions no sibling worker can see, and the driver's own
docblock says so — the honest scope statement, not a limitation
discovered in production. The Redis / file / table drivers the roadmap's
swap promise names are the lane's own reservations, each a slice of its
own (a file driver owns directory posture and permissions; a Redis
driver owns a connection the zero-dependency gate would have to probe;
a table driver owns a schema), and the port's shape — load, save,
destroy, the expiry semantics the driver's own TTL idiom owns — is the
surface each lands against without an application change.

## KDD-SC8 — the bounds live at the constructor, the CsrfBounds pattern

The first spelling kept both integer corridors inline in the map
reading (the BodyParserPolicy idiom), and the architecture gate
answered: the policy's difficulty 20.08 against the ceiling 20, the
map reading's complexity over. The settle folds the two seams out —
`SessionCookieName` (the token grammar both construction paths read
through) and `SessionBounds` (the two corridors) — with the bounds
asserted at the CONSTRUCTOR rather than the map reading, which is the
`CsrfBounds` pattern's own argument: the corridor then holds at both
construction paths alike, the config-driven one and the programmatic
one, and a host spelling an out-of-corridor value by hand gets the
same named halt an operator's typo gets. Difficulty after the fold:
15.22.

## KDD-SC9 — requireCoverageMetadata filters infection's test selection too

The escaped-mutant round: forty-three mutants in `SetCookie` (the
constructor's rejection-message concatenations, the value-grammar
check's visibility, the SameSite fold's byte agreement) escaped a suite
that demonstrably exercises them — because `requireCoverageMetadata`
attributes coverage only to tests whose `CoversClass` list names the
executed class, and `RequestCookiesTest` (which drives
`SetCookie::assertValueGrammar` from outside the class) does not
`CoversClass(SetCookie::class)`. This is the SonarCloud covers-filter
lesson the body-parser round paid for (#589, KDD-BP12) re-learned on
infection's own ground: the SAME metadata filter governs both tools'
attribution. The kills are honest ones, not attribution edits: the
constructor-rejection provider gains the message column every
`Concat`/`ConcatOperandRemoval` mutant dies against, and the
value-grammar seam gains its own direct public pin (the visibility
mutant cannot survive a call from outside the class).

## KDD-SC10 — the identifier's entropy seam is the CsrfTokenManager shape, and the two analysers agree on it

The issuance fold takes its entropy as a parameter
(`randomBytes(int $bytes)`) with the constructor's floor asserted at
the fold — the exact `CsrfTokenManager::entropy()` shape, because the
two analysers disagree about what they can see through a private
property: psalm wants the narrowing before `random_bytes()` (its
`int<1, max>` demand), while PHPStan already knows the property is
above the floor and reports an always-true comparison when the assert
reads the property directly. The parameter is the shape both accept —
the analyser-boundary family the Container answer documented twice,
dodged rather than argued with here.

## KDD-SC11 — PHP's copy semantics are the raw bag's own guard

`SessionState::data()` answers the bag by value: PHP copies the array
at return, so a host mutating the array it read mutates its own copy,
never the session's — the exposure cannot dirty the bag, and the pin
proves it (the mutated copy asserted on its own terms, the session
asserted unchanged, the dirty flag still down). The copy is not an
accident of implementation but the documented contract: the
request-scoped bag is mutable through its own named members only.

## KDD-SC12 — the deletion cookie is belt-and-braces and flag-matched

The deletion spelling carries BOTH `Max-Age=0` and the epoch
`Expires` (the browsers that predate Max-Age honour only the latter),
and it rides the SameSite/HttpOnly/Secure posture of the issuance it
retires: a deletion the browser cannot match to the cookie it holds (a
different Path or Domain) deletes nothing. The optional-attributes
order the renderer spells — name, Domain, Path, Expires, Max-Age,
SameSite, Secure, HttpOnly — is deterministic and pinned end-to-end
through the exact-string assertions the mutation round added.
