# Uploads store — the lane's first writer

The decision record for the `uploads` lane's first writer
(`Adapters\Uploads\FileUploadsStore` over `UploadsStorageWiring`), the
slice issue #722's own directive prioritised: **the one Partial area
that is a production attack surface** — an uploads lane without its
writer leaves every host hand-rolling file handling outside the
audited layout. The lane's reservation (named in the vocabulary since
`StorageLane` first shipped, honest about the writer it did not hold
per issue #788's finding 9) is closed by the pull request that lands
this note; the `temp` lane's writer with its cadenced sweep is the
reservation the layout still carries.

## KDD-US1 — the census-moment doctrine, answered on the reservation's own terms

The `sessions` and `queue` lanes joined the vocabulary in the same
pull requests as their first writers (KDD-SL3). The `uploads` lane
deliberately took the other posture the doctrine allows: a NAMED
RESERVATION in the vocabulary, carried through the layout's whole
lifetime precisely because the reservation was the roadmap's own
recorded open slice (issue #788, finding 9's honesty: the docblock
naming the reservation, never a writer it did not hold). This slice
closes the reservation without the vocabulary growing: the lane's
directory, walk answer, inspector row and skeleton pin were already
live, answering seven lanes, and the writer rides them unchanged.
The DoD's "the skeleton, walk, inspector and pin answer nine lanes"
was a drafting slip in the priorities row (#738): the seven lanes
were the count the whole time, the reservations already carried
their directories, and KDD-SL3's own doctrine forbids adding a lane
without a writer — so no ninth or eighth lane was ever there to
answer.

## KDD-US2 — fail-closed means the validations refuse, never the upload's evidence

The two validations the DoD names are both fail-closed on the
engine's own bounds, in the order the evidence is available: the
transport's own error code first (an errored upload's size and type
are not trustworthy answers, so the refusal names the error, never
a misleading 413 or 415 over numbers the transport already refused),
then the size, then the type. The unmeasurable size refuses as the
honest 400 it is — the same doctrine the head-answer lane spelled
for the unmeasurable cursor ("loses the field honestly", #900) —
never a 413 that would name a number the engine does not hold. The
typeless upload refuses the same way the refused type does: a null
client media type can never be judged against an allowlist, and the
415's evidence carries the null with the accepted list spelled —
both sides of the refusal, the way out included.

## KDD-US3 — the exact-match comparison, no widening

The allowlist comparison is exact, the tree's own media-type
posture: `RouteAcceptRange::matches()` compares `type`/`subtype`
string-equal, and so does the writer. The declared vocabulary's
grammar (KDD-US4) owns the canonical lowercase spelling, so a
deployment's `IMAGE/PNG` entry is a named halt at the declaration,
never an allowlist that silently never matches; a client's
`image/PNG` answer against a declared `image/png` is refused — the
over-strict direction the fail-closed posture prefers over the
RFC's case-insensitivity, which this first writer does not spell.
A widening would be its own slice, over its own evidence.

## KDD-US4 — the allowlist grammar narrows at the declaration, loudly

`uploads.types` entries must be the closed shape the tree's own
comparisons answer: lowercase alphanumerics first on each side,
the registered subtype punctuation (`.` `+` `_` `-`) after, exactly
one separating slash, no parameters. Every refusal names the entry
inside the message — the strict census lineage (`StrictKnobs`,
`EventDeliveryKnobs`): a typo'd or uppercase entry that fell
through would leave a deployment believing it accepts what the
exact-match never matches. A repeated entry rides verbatim — the
comparison is repeat-invariant, so the fold never silently
de-duplicates what the deployment spelled.

## KDD-US5 — the landed name carries no client spelling at all

The writer owns the landed name entirely: thirty-two hexadecimal
characters over sixteen random bytes, extension-less by
construction. No client filename, extension or path fragment ever
reaches a filesystem call — the same discipline the move
machinery's own temporary sibling spells ("a random suffix that
never carries request input into a filesystem call"), closed
here for the permanent name too. The extension question (which
client extension is safe to preserve?) stays unanswered rather
than answered loosely: the type the validation already judged is
the host's own knowledge to serve, and the lane's files carry no
execution ambiguity for a misconfigured host to trip over. The
collision arithmetic is spelled honestly: the rename's own POSIX
replace answers a 128-bit draw that lands on an existing name —
a probability the engine does not spend a branch on, the identity-
keyed lanes' replacement semantics carried to their random-name
conclusion.

## KDD-US6 — the silent knob is the attack surface's own opt-in

The family ships silent: `uploads.types` defaults to the empty
allowlist, and the empty allowlist IS the opt-out — the events
family's `store` word answered with the allowlist's own spelling.
Resolving `uploads.store` without a declaration is the named halt
with the way out spelled ("spell the media types the lane accepts,
or compose the writer by hand over the layout address"), and the
registration is TRANSIENT for the same reason the events family's
planted: the warm boot's singleton warmer never resolves the
family, so a silent knob stays green at boot and the refusal fires
only at the host's own ask — the honest moment. A writer that
accepts nothing is a surface that stays shut: the fail-closed
default is not "everything below two megabytes" but "nothing",
until the host's own declaration opens it.

## KDD-US7 — the one-way precedence and the whole-tree walk

The address precedence is the cross-cut's own: an explicitly
spelled `uploads.path` verbatim (judged by the writer's own
directory posture, never re-spelled by the wiring), else the
layout's `uploads` lane over the declared `storage.root`, else the
combination refusal the session and events families spelled first
— a directory the host never asked for is never silently
defaulted. The walk is the layout's own over the WHOLE tree,
fail-closed with the refusing seam named: the uploads family
answers to the same map the inspector prints, never a one-lane
shortcut that could drift from it (KDD-ES4's doctrine, carried
the way the error, session, cache and events families carry it).

## KDD-US8 — the directory posture, the strictest the sister lanes carry

The lane's directory posture is the sister writers' own:
existing, writable, non-symlink, owner-only permissions, outside
the public document root — the last judgment through the storage
layer's own shared fold (`StorageDocumentRoot`), the geometry
spelled fresh in the family's own words. The uploads lane is the
one area the priorities table spells as a production attack
surface, so the posture is not a convenience: a world-readable or
web-served uploads directory would turn a validated landing into
a disclosure.

## KDD-US9 — the 415 widens the typed family in the same pull request

The typed family's status map did not carry 415
(`ProblemDetailsMetadata`), so the slice extends it —
`unsupported-media-type` / `Unsupported Media Type` — in the same
pull request the first 415-raising component lands
(CONTRIBUTING rule 17: "when the family lacks the status, error
code or payload the component needs, extend it in the same pull
request"). The Adapters lane reaches the Exception root one-way
for the request-surface refusals (`Adapters += Exception`, the
same one-way widening the database adapter's audit emission
planted for `Telemetry`): the sister adapters' construction-time
refusals stay SPL (the Storage posture) exactly as they were.
## KDD-US10 — the archeology ceiling demanded the fold split, and the suite's own tree helper answered with it

The first spelling carried the four gates inside `FileUploadsStore::store()`
beside the constructor's own bounds, and the archeology gate's difficulty
ceiling (20) answered with two error-level findings -- the file and the
class both over. The split the storage layout's own record spells
(the `StorageRoot` fold split that answered its 26.50 at 16.67/17.50,
the `StorageRootDeclaration` split at 16.67/17.50): the gates live in
`UploadsAdmission` (the admission judgment: the four questions, asked
in the evidence's own order), the store keeps the landing alone (the
name, the move, the address), and the admission owns the ceiling and
the allowlist's construction bounds -- neither fold ever answers for
the other, and each sits under the ceiling on its own terms. The CI
round's other findings rode the same settle: the boundary-test
variable name the rector rule demands, and the suite's three
identical private `removeTree` helpers folded into the one
`UploadsTemporaryTree` support fold the three test classes share.

The round's second measure kept the advisory duplication lane's
density above its 2% bound -- the small slice cannot amortise the
per-family spellings the house doctrine keeps (the census and the
directory posture are each deliberately the sister folds' shape,
the words the family's own), and the removeTree extraction alone
did not move the number. The answer the house's own disciplines
spell, not a detector dodge: the census walks as a filter over
the declaration's own key set (the first foreign key answered
identically, the message unchanged -- a family spelling the sister
censuses' fail-fast loop does not own), and the directory posture
splits into its three guards -- the geometry, the canonical
address, the permission mask -- the depth-split discipline the
tree's folds keep under the architecture gates' ceilings, each
guard owning one question and one refusal, every message and
every refusal semantics unchanged (the tests never changed).

