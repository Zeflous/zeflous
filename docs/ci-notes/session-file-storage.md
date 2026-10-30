# The Sessions Lane and the File Session Driver — KDD

**Scope:** the storage layout's second named family integration (the
follow-up KDD-SL6 reserved) and the session family's own named driver
reservation landed together, the census-moment doctrine's own answer: the
`sessions` lane joined the `StorageLane` vocabulary in the same pull
request as its first writer, and the file driver over the
`SessionAdapterInterface` port answered the reservation its own docblock
spelled ("a file driver owns directory posture and permissions"). Ten
classes under `src/Zef/Framework/Adapters/Session/`, one store vocabulary
(`SessionStores`) and two new knobs under the session family's census, one
wiring fold (`SessionStorageWiring`) and one factory branch
(`SessionFactory`) under the Middleware lane, the `files/sessions/`
skeleton entry, deptrac widening twice (`Adapters += Http`, the port's
lane; `Middleware += Adapters`, the composition naming the driver — the
port's own promise), branch `feat/session-file-storage`.

**Verified locally before the PR:** `ci:static` EXIT-0 (6078 tests /
14658 assertions; smoke 2760B/20000 iterations; workflow concurrency 23;
roadmap:gate pass; audit 0 advisories) · coverage 98.48% against the 90%
floor · psalm EXIT-0 with no new baseline entry (every new class gained
an in-src consumer — the factory names the adapter, the policy names the
vocabulary, the adapter names its folds) · deptrac 0 violations over the
two widenings · archeology 0 error-level findings — the first spelling
reddened the gate FOUR times (the codec at 39.64, the adapter at 26.93,
the layout at 20.50, and the policy's `fromMap` over the cc-10 ceiling)
and the fold-split discipline answered every one the house way, recorded
under KDD-SF9 · scoped infection per file over the eighteen touched
files, MSI 100/100 with 0 escapes after the mutation round KDD-SF11
records, and three new MBString ledger entries joining the
ASCII-by-construction class.

## The map

| Unit | Question it owns |
|---|---|
| `FileSessionAdapter` | the port's three verbs, thin delegations over the folds; the injectable clock; the store's posture refusal at construction |
| `SessionStoreDirectory` | WHERE the record files may live: existing, writable, non-symlink, owner-only, outside the public document root (`StorageDocumentRoot` composed one-way) |
| `SessionRecordFiles` | WHICH FILE one identifier is (`<id>.json`, the base64url alphabet its own filename), the id a filename spells, the miss a directory named like a record answers, the concurrency-honest removal |
| `SessionRecordScan` | WHICH FILES the store's walks see: every record file, the staging dot-files and directories skipped |
| `SessionRecordStaging` | HOW one record becomes a file: dot-file staging, owner-only securing, one `rename()` |
| `SessionRecordEncoder` | the record's writing half: the bag's survivability judgement, the depth ceiling, the JSON translation |
| `SessionRecordDecoder` | the reading half: the JSON decode over the shape and values gates |
| `SessionRecordShape` | the record's own object shape: exactly the two keys, the written-at half an integer |
| `SessionRecordValues` | the bag half, proven string-keyed: the JSON grammar's own answer set |
| `SessionBagDepth` | the bag's nesting-depth question, refused at the write |
| `SessionRecordLoad` | the load walk: existence, read, decode, the sliding expiry's lazy purge |
| `SessionSweep` | the cadenced retirement of the abandoned record |
| `SessionStores` | the `http.session.store` vocabulary: `memory` \| `file`, case-folded, fail-closed |
| `SessionStorageWiring` | the file driver's EFFECTIVE directory: explicit `http.session.path` verbatim, else the `sessions` lane a declared root provisions, else the empty posture the factory's combination refusal names |
| `SessionFactory` | the composition seam: `only the composition names the driver` — the `file` word wires the filesystem adapter over the port |

## KDD-SF1 — the lane and the writer land together (the census moment answered)

The `StorageLane` vocabulary carried no `sessions` lane while the driver
was a named reservation, because a lane without a writer is a directory
nobody provisions and nobody checks. This pull request is the moment the
doctrine reserved: the lane (`Sessions = 'sessions'`, its one-line purpose
"the session file driver's identifier-keyed bags"), the repository
skeleton entry (`files/sessions/.gitkeep` under the contents-ignored /
structure-kept gitignore discipline), the `storage:map` inspector's six
lanes, and the first writer all landed in one change, so
`StorageSkeletonTest`'s tree↔vocabulary↔gitignore pin and the lane's
first consumer were born agreeing. The `app/configs/storage.php` grammar
now spells the six-lane tree the root provisions.

## KDD-SF2 — the written-at reading rides inside the record, never on its mtime

The port's own contract says the expiry semantics are the driver's own,
and the driver's own docblock illustration names "a file's mtime" as one
idiom a file store might ride. This driver refuses that idiom on purpose:
the expiry judgement belongs to the ONE clock seam the suite can freeze
(the same discipline the in-memory driver's injectable clock spells —
inline `time()` is arithmetic a mutation run rewrites invisibly), and the
filesystem's own clock is not that seam. The record carries
`{"data": {...}, "written_at": <int>}` — exactly the two keys — so the
boundary pinned by test (`1029` alive, `1030` expired against a
`30`-second timeout) is deterministic under the frozen clock, and the
sweep, the load and the record all read the same reading.

## KDD-SF3 — the store vocabulary and the address knobs

The `store` knob names the driver the composition wires (`memory`, the
zero-dependency in-process default whose honest scope the in-memory
driver's own documentation spells; `file`, the filesystem answer), read
through a closed case-folding vocabulary mirroring `RateLimitStores` — a
typo is a named halt, never a silently defaulted driver, because the
driver a deployment named is a statement of storage topology. The `path`
knob spells the file driver's directory — PHP's own `session.save_path`
spelling for exactly this concept — and the wiring's precedence is the
integration doctrine's own, one-way: an explicitly spelled address always
wins, derivation only fills the address the host left empty. The `file`
word with neither an address nor a declared root is a NAMED halt at boot
("declare storage.root or spell http.session.path"), never a silently
defaulted directory the host never asked for.

## KDD-SF4 — the record's privacy posture and its write discipline

The session bags are the users' own data spelled in the clear, so the
store's directory posture is its own fold over the same geometry the
delivery store spells (existing, writable, non-symlink, owner-only,
outside the public document root — `StorageDocumentRoot` composed
one-way, the one spelling of "a web-served tree is a disclosed tree" the
whole tree already shares, the adapters lane's second composition of a
core fold). Every record file is secured owner-only (`0o600`) and written
through the one-rename discipline — staged under a leading-dot name the
scans ignore, renamed into place — so a load never observes a
half-written bag, and a process that dies between staging and rename
strands a dot-file the operator may sweep while no writer runs, never a
partial record a later load would have to refuse (the crash window the
staging docblock promises, pinned by test exactly as the delivery store's
was).

## KDD-SF5 — write what you can read: the survivability and depth judgements at the save

A bag value the JSON grammar cannot carry — an object, a resource, NAN,
a string that is not valid UTF-8 — is refused AT THE SAVE that would have
written it, by the one JSON-round-trip walk the tree already spells
(`SerializableValues`, reached one-way, the same cross-lane composition
the delivery store's directory fold spells for the document-root
judgement), naming the value's own kind and path: never an opaque encode
failure, never a corruption the next load discovers. The depth question
is answered at the same boundary by the depth fold: the engine's own
decode grammar reads a bag nested at most 510 levels deep (pinned
empirically: 510 decodes, 511 refuses), so the write refuses deeper — a
written record a load can never answer is a save that lied. The decode
side is fail-closed in every direction: invalid JSON (the engine's own
reason, the record named), a shape that is not the two-key record, a bag
that is not a string-keyed map, a written-at that is not an integer.

## KDD-SF6 — the concurrency posture: multi-worker by purpose

The in-memory driver's own documentation says a multi-worker deployment
pointing at process-local storage would hold sessions no sibling worker
can see; the file driver exists precisely so every worker that can read
the directory shares the sessions. That purpose earns its own retirement
idiom: the removal is idempotent AND honest under the race — a record a
sibling worker retired between the existence check and the unlink is the
destroyed state arrived at through the window (the re-check answers what
the unlink's own false could not explain), never a refusal; a vanished
record the sweep's read answers false for is the retired state itself,
never a refusal. The one honest failure arm left: the record that exists
and cannot be removed, refused with its name spelled. The sweep itself is
fail-closed on undecodable records (the operator must hear about a
corrupt record — a file the sweep quietly stepped over would linger
forever, seen by no load and retired by no pass), the delivery store's
own due-selection posture replayed.

## KDD-SF7 — the expiry regime is the port's own contract, replayed over files

The lazy refusal at load (an expired identifier answers null and is
purged at its own load), the sliding boundary (the timeout fully elapsed
is expired, the last instant before it is not), and the cadenced sweep
riding a save that was already paying a write (at most once per 60
seconds, one directory walk per cadence, never a background worker) — the
whole regime is `SessionEntries`' own arithmetic replayed over the
filesystem, boundary pinned both sides by test (59 seconds inside the
cadence rides no walk, 60 runs it).

## KDD-SF8 — the deptrac widenings, both essays in the config

`Adapters += Http`: the same hexagonal direction the lane's first pair
planted — the port depends on nothing, the adapter depends on the port —
with the adapter additionally reaching the Event lane one-way for the
JSON-round-trip walk. `Middleware += Adapters`: the session port's own
promise spells that only the composition names the driver, and the
factory is that composition — the `file` word wires the filesystem
adapter the Adapters lane owns over the port, while the engine-blind
layers below keep answering the `memory` default without ever knowing an
adapter exists.

## KDD-SF9 — the archeology gate reddened four times; the fold-split discipline answered every one

The first spelling put the codec at difficulty 39.64, the adapter at
26.93, the layout fold at 20.50, and the policy's `fromMap` over the
cc-10 ceiling (nine `??` coalescences + two guards). The answers, each
the house's own: the record's shape and values gates became their own
folds (the `DeliveryRecordShape`/`DeliveryRecordValues` pair replayed);
the load walk became its own fold; the scan became its own fold; the
depth walk became its own fold; the encoder and the decoder split the
way the delivery record's own halves always did; and `fromMap` now
merges the knob defaults with `+=` BEFORE the strict readers read —
zero coalescences, cc 3, the ceiling pressure gone rather than survived
(the rate-limit family's own `fromMap` sits AT the ceiling with its
eight; the merge is the spelling that leaves room for the next knob).
The session shape's own lesson: the difficulty metric is Halstead's
(UO/2 × N2/n2) — operand REPETITION is what it counts, so the `$id`
threaded through every private refusal gate was the weight, and the
consolidated two-key judgement (the count against the vocabulary's own
size, the two halves' presence in one `isset`) answered it honestly —
every arm of that gate answers the same operator question with the same
remedy. Final difficulties: adapter 16.45, shape 16.50, sweep 18.28,
encoder 13.50, everything else under 15.

## KDD-SF10 — the analyser-honest mutable clock

The cadence tests need ONE adapter instance (one sweep state) whose
clock ADVANCES mid-flight. The first spelling was a by-reference closure
over a local variable — a read the dead-code and unused-variable sniffs
cannot see (rector's `RemoveUnusedVariableAssignRector` silently deleted
the clock advances and broke the tests' meaning), so the honest spelling
is a named support class with its own verbs
(`MutableSessionClock::advanceTo()`), one both the analysers and the
mutation run read. The lesson generalises: a by-reference capture hidden
inside a helper is analyser-invisible at the call site; spell the
mutability as a type.

## KDD-SF11 — the mutation round: observation tests first, the ledger only for the construction-equivalent class

The first mutation pass answered 15 escapes. Twelve died to real
observation tests the adversary demanded: the corrupt-record refusal
pinned EXACTLY (the engine's own reason and the trailing stop carried,
the wrapped code zero asserted — three Concat mutants and two code
mutants on one refusal); the decode's depth ceiling pinned from BOTH
sides (a hand-written 511-deep record refuses at the load — the boundary
the 512→513 depth mutant lives on, pinned empirically because the
engine's depth arithmetic counts the record's own root); the sweep's
boundary pinned exactly (the record whose timeout has fully elapsed is
swept, the last instant before it is not — the `<`→`<=` mutant's home);
the scan's skip arms pinned observably (a stranded dot-file carrying
invalid JSON stays ignored by the sweep's walk — the `||`→`&&` mutant
would read it and refuse; a directory named like a record that sorts
BEFORE an expired record never ends the walk — the `continue`→`break`
mutant would leave the expired record unretired); and the depth walk's
leaf-first bag pinned (a scalar before a 511-deep nest still refuses —
the `continue`→`break` mutant would stop measuring at the leaf). The
running-max comparison in the depth walk was re-spelled `max()` because
the comparison spelling's equal-case mutant is value-equivalent BY
ARITHMETIC (assigning the same value the guard skips) — the third
ASCII-by-construction-style lesson: when the equivalence is the
arithmetic's own, change the spelling, never the ledger. The three
ledger entries that DID join (`SessionStores::read`,
`SessionRecordStaging::write`, `SessionRecordFiles::recordIdOf`) each
carry the exact essay of their own precedent twins
(`RateLimitStores::read`, `DeliveryStaging::write`) — the vocabulary fold
with no ASCII `k` in its words, the write-length guard over the
ASCII-by-construction record, and the ASCII suffix cut whose last five
characters and last five bytes are the same five bytes.
