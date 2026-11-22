# Temp store — the lane's writer with its cadenced sweep

The decision record for the `temp` lane's writer
(`Adapters\Temp\FileTempStore` over `TempStorageWiring`): the
reservation the Storage layout row carried alone after the
uploads lane's first writer landed (#908), the priorities
table's own P1 row's last remaining clause -- the one Partial
area that is a production attack surface, closing with the
cadenced sweep the row's own DoD spells. With this slice the P1
row retires: both its clauses answered, the Storage layout row
its own, and the priorities table's remaining rows all P2.

## KDD-TS1 — the census-moment doctrine, the reservation's own terms one last time

The `sessions` and `queue` lanes joined the vocabulary in the
same pull requests as their first writers (KDD-SL3); the
`uploads` and `temp` lanes took the other posture the doctrine
allows: NAMED RESERVATIONS carried through the layout's whole
lifetime, honestly, because the reservations were the roadmap's
own recorded open slices. This slice closes the second one
without the vocabulary growing: the lane's directory, walk
answer, inspector row and skeleton pin were live from the
layout's first day, answering seven lanes, and the writer rides
them unchanged. The vocabulary's own docblock corrects itself in
the same pull request: the `temp` bullet names the writer it now
holds, the class-level "or a named reservation" qualifier retires
(every lane answers a live writer today), and the `uploads`
bullet -- stale since #908 landed its writer, still spelling "a
named reservation the roadmap's own open slice keeps" -- is
corrected in the same docblock, the drift-honesty doctrine the
roadmap documentation hygiene pass (#905) spelled for the whole
tree.

## KDD-TS2 — the TTL is the opt-in word, the allowlist posture replayed

The family ships silent: `temp.ttl` defaults to null, and the
null retention IS the opt-out. Resolving `temp.store` without a
declaration is the named halt with the way out spelled ("spell
how long the lane keeps its files, or compose the writer by hand
over the layout address"), and the registration is TRANSIENT for
the same reason the events and uploads families planted it: the
warm boot's singleton warmer never resolves the family, so a
silent knob stays green at boot and the refusal fires only at
the host's own ask. The uploads family's opt-in word was the
allowlist -- a writer that accepts nothing is a surface that
stays shut; the temp family's is the retention -- the mortality
the host spells is the mortality the lane keeps, and a knob the
host never spelled keeps the surface shut. No default retention
exists anywhere in the family: the uploads ceiling's
`DEFAULT_MAX_BYTES` answered the question "what bound when the
host spells the opt-in but not the ceiling"; the temp TTL has no
such second knob, so no default was ever needed. The CI round
taught the family its armed question needs no fold of its own:
the retention's null reading IS the answer (the wiring's refusal
narrowing over it, the null and the declared integer the family's
tests pin directly), and an unconsumed vocabulary fold is a
psalm finding the uploads family never met -- its own
`wantsWriter()` is consumed by its own wiring, while the temp
wiring's guard is the retention's null reading itself, so the
declaration keeps no second spelling of a question one reading
already answers.

## KDD-TS3 — the cadence is the lane's own memory, not the composition's

The sweep's cadence marker is persisted in the lane itself --
the session store's own doctrine (issue #788, finding 2) replayed
on the temp lane's own terms: the writer is composed per ask
(the transient registration), so an instance field would reset
with every composition and every write-bearing ask would pay a
full lane walk while the sixty-second cadence promised the walk
once a minute. The marker is a leading-dot file the scan's own
grammar skips, laid down through the one-rename discipline,
fail-open to read (absent, unreadable or corrupted answers zero
-- the walk the store pays, one extra walk the worst a missing
marker can inflict, never a missed one), best-effort to write (a
marker the store cannot lay down leaves the next write sweeping
again -- the degraded-but-correct cadence, spelled by the walk
it repeats rather than a refusal that would brick the very
writes the cadence exists to cheapen). The recomposition pin
holds it observable: a second store over the same lane answers
the first store's marker, not a fresh cadence.

## KDD-TS4 — the sweep rides the write, after the write

The cadence bounds the walk to at most one lane walk per sixty
seconds, riding a write the process was already paying -- never
a background worker, the same cost bound the session lane
spells. The ordering is the session exchange's own: the write
lands first, then the sweep rides it -- a sweep that takes its
files never touches the write that paid for it, and a refused
write never pays a walk. The one honest exception rides the
sister family's own idiom: an un-unlinkable scratch (a lane the
process suddenly cannot write into) is the loud refusal riding
the write, because a lane the process cannot write is a lane the
process's own writes already refused in -- the environment's own
break, never a defect the sweep could quarantine.

## KDD-TS5 — the mortality boundary, and the file's own clock

A scratch file is swept exactly when its own retention fully
elapses -- the timeout fully elapsed is mortal, the last instant
before it is not, the same boundary discipline the session
retirement spells. The clock the lane's files carry is the
filesystem's own modified reading: the session record carries
its written-at word inside the payload it encodes, but a scratch
file IS its bytes and nothing else -- no sidecar metadata, no
encoded written-at, no bookkeeping file the scan would have to
distinguish, the one clock every file in every tree already
keeps for free. A file whose age the engine cannot measure
while the file exists is retired, never skipped: the lane's
files are mortal by declaration ("without anyone mourning it"),
so an unmeasurable age is the defect the mortality itself
answers -- a skipped defect would be an immortal file in a
lane whose own word is that nobody mourns it.

## KDD-TS6 — the scan carries no name grammar, the lane is the mortality

The scan takes every non-dot file in the lane: no hex-grammar
filter decides whose file the sweep may take. The session scan's
extension test distinguishes a record from a neighbour's
artefact because the sessions lane is one grammar among several
in one tree; the temp lane IS the whole tree of its own kind --
the mortality is declared over the LANE, never over a name
shape, and the writer's random names are its own landing grammar
but never a survival privilege. Dot-files are skipped (the
cadence marker, the stranded staging files, operator sweep
material -- the same blind spot the session scan spells);
directories are skipped without ending the walk (a container the
lane never provisioned, never a mortality the sweep owns).

## KDD-TS7 — the landed name and the write discipline

The landed name is the writer's own 128-bit random hex,
extension-less by construction -- no host spelling, filename,
extension or path fragment ever reaches a filesystem call, the
same discipline the uploads lane spells for its landed names.
The write rides the one-rename staging discipline the session,
delivery and route-cache writes already spell: staged beside its
destination under a leading-dot name, secured owner-only, then
renamed into place with one `rename()` -- a later read never
observes a half-written scratch, and a process that dies between
staging and rename strands a dot-file the scan ignores.

## KDD-TS8 — the byte-count identity is the payload's own bytes

The staging write's completeness check compares the written
count against `mb_strlen($payload, '8bit')` -- the coding
standard's own byte-semantics idiom, the same spelling the
database and telemetry lanes use. The session and delivery lanes'
plain `mb_strlen` pairing rides the invariant their encoders
escape every multibyte byte away (issue #788, finding 6's
ledger: ASCII-by-construction, verified by their suites); a
scratch payload is ARBITRARY host bytes, never ASCII by
construction, so the plain pairing would count every multibyte
payload's CODEPOINTS against its written BYTES and refuse the
write that succeeded. The `'8bit'` spelling is the honest pairing
on this lane's own terms, pinned by the multibyte round-trip
test.

## KDD-TS9 — the one-way precedence and the whole-tree walk

The address precedence is the cross-cut's own: an explicitly
spelled `temp.path` verbatim (judged by the writer's own
directory posture, never re-spelled by the wiring), else the
layout's `temp` lane over the declared `storage.root`, else the
combination refusal the session and events families spelled
first -- a directory the host never asked for is never silently
defaulted. The walk is the layout's own over the WHOLE tree,
fail-closed with the refusing seam named: the temp family
answers to the same map the inspector prints, never a one-lane
shortcut that could drift from it (KDD-ES4's doctrine, carried
the way the error, session, cache, events and uploads families
carry it).

## KDD-TS10 — the directory posture, strictest, a fortiori

The lane's directory posture is the sister writers' own:
existing, writable, non-symlink, owner-only permissions, outside
the public document root -- the last judgment through the
storage layer's own shared fold (`StorageDocumentRoot`), the
geometry spelled fresh in the family's own words (the depth-split
three guards the difficulty ceiling demands, each carrying the
family's own spellings: the landable-lane guard judged as a
positive boolean, the resolved lane address, the shared-lane
refusal -- never the uploads fold's own guard names, the density
bounds' own answer). The uploads argument lands a fortiori
here: the uploads lane validates a type before landing, while
the temp lane lands whatever bytes the host hands it,
unvalidated by design -- a world-readable or web-served temp
directory would turn the lane's own word, "nobody mourns it",
into "everybody reads it".

## KDD-TS11 — the folds' split is pre-emptive, the ceiling never fires

The writer's questions are split into the folds the archeology
gate's difficulty ceiling demanded of the uploads slice
(KDD-US10) from the first spelling: the directory posture
(three guards), the staging write, the scan, the cadence marker,
the one-file retirement, the cadenced walk, and the store's own
landing -- each fold owning one question, one refusal, one
seam, so neither the store nor any fold answers for another.
The knob family follows the uploads slice's own density
settle: the census walks as the inverted-guard fail-fast loop
over the declaration's own key set (the first foreign key
answered identically, the message unchanged -- a third spelling
beside the elder sisters' loop and the uploads slice's filter,
none of them a copy of another), and the test tree helper is the
temp slice's own shared fold, the per-family spelling the
advisory duplication lane's bound already amortises for the
house doctrine (KDD-US10's own accepted posture). The CI rounds
taught two more density settles the uploads slice also spelled:
the cadence marker's folds ride the family's OWN spellings over
the session lane's cadence fold (the absent-and-unreadable
answer ONE ternary guard, the rename destination a lane address
hoisted once), and the directory fold's guards carry the temp
family's own names and positive spellings over the uploads fold's
-- every message, refusal and fail-open/best-effort semantics
unchanged, the sister lanes keeping their own spellings, and the
pairs answering the duplication bounds the way the census pair
does, families' own words for the same doctrines. The cloud
lane's coverage condition taught the failure-path arms: the
suite's temp function doubles (`temp_file_function_doubles.php`
over `TempFileDoubleState`, the session and uploads families'
seam) answer the impossible filesystem answers (an unsecurable
scratch, an unfinalisable rename, an unscannable root, an
unresolvable address) the real filesystem cannot produce in a
test process, closing the failure-path coverage the 95-on-new-code
condition demands.

## KDD-TS12 — the shipped profile sentences the slice re-homes

The shipped profile's own three sentences ride the slice: the
new `app/configs/temp.php` (the retention knob's grammar, the
cadence deliberately out of the block -- a cost bound the host
cannot spell wrong is a cost bound the family owns outright),
the `uploads.php` docblock's "named future slices" sentence
(now its own family's block, the reservation it named closed),
and the `storage.php` root provisioning sentences gaining the
temp writer's derivation -- the request-time families list
corrected to carry the uploads writer's directory too, stale
since #908 landed it, the drift-honesty doctrine riding the
file the slice already touches.
