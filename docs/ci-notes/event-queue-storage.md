# The Durable Delivery Store's Wired Default — KDD

**Scope:** the Adapters lane's own recorded reservation — the `queue/`
storage lane plus the `events` knob, "the wired default and its
configuration reader" — closed by the pull request that lands this
record. Three ledgers carried it verbatim (KDD-DU10's census-moment
doctrine, KDD-DQ8's honest scope ledger, KDD-CS7's closing paragraph):
the file delivery store deliberately kept its own directory posture
until the wired default landed, and the `queue` lane deliberately
stayed out of the vocabulary until its first wired writer existed.
This slice lands both halves together, exactly as the
`sessions` lane and its file driver did (#656): the lane, the knob
and the reader are one pull request, so no directory exists that
nobody provisions and nobody checks, and no knob exists that no
reader reads.

**Verified locally before the PR:** `ci:static` EXIT-0 (6122 tests /
14747 assertions; smoke 2760B/20000 iterations; concurrency 23
workflows; roadmap:gate pass; audit 0 advisories) · coverage
98.19% against the 90% floor · psalm EXIT-0 with the baseline
SHRINKING -- `FileDeliveryStore`'s `UnusedClass` entry left the
ledger the day the wiring became its first in-src consumer ·
progpilot `[]` · archeology 0 error-level findings (the gate's
own ceiling split the reading family -- see EQ9) · infection: the
CI's own full round over 16569 mutants answered 16441 killed with
TWO escapes the first local scoped round missed (its kills were
contaminated by `/tmp` staging debris the mutant experiments
stranded -- a false green the clean CI runner exposed); both were
settled honestly -- the `Continue_` escape (the census's loop-shape:
a `break` would stop at the first known knob and wave a later
unknown one through) killed by the order-aware census test, and the
`MBString` escape settled by the ledger: the store-vocabulary fold
joined the documented no-`k` equivalence class (the `SessionStores`
twin's own argument -- `file` carries no ASCII `k`, the Kelvin sign
is the only single-character non-ASCII landing into ASCII, so every
fold-divergent value misses the closed set under both spellings),
verified by the complete declaration battery passing under the
manually applied mutation, the ignore scoped to that single method
with the in-config essay the ledger demands · deptrac 0 violations
over the `Kernel += Event, Adapters` widening · zero-deps PASSED
(`require` still `{"php":"^8.4"}`, lock `[]`).

## The map

| Unit | Question it owns |
|---|---|
| `StorageLane::Queue` | the seventh lane's canonical spelling and one-line purpose — the vocabulary's own census, walked by every family that resolves a root |
| `files/queue/.gitkeep` + `.gitignore` | the repository skeleton's pin: the structure tracked, the contents never |
| `EventFamilies` | which children the `events` map may carry — the family census, the `HttpFamilies` seam mirrored at the composition lane |
| `EventDeliveryKnobs` | which knobs the `events.deferred` family owns — the unknown-key census, the `StrictKnobs` lineage mirrored |
| `EventDeliveryStores` | the store word's closed vocabulary, the environment's spelling folded — the `SessionStores` lineage mirrored |
| `EventDeliveryCapacity` | the capacity knob's integer seam, the all-digit string folded — the `StrictIntegers` lineage mirrored |
| `EventDeliveryDeclaration` | what the host declared: the value object the census family reads into |
| `EventStorageWiring::store()` | what the word composes: the wired default over the port, the address derivation, the combination refusal — the `events.deferred.store` registration's own factory seam |
| `FrameworkDefaults` | the `events` knob block (silent by default) and the `events.deferred.store` service registration (transient) |
| `app/configs/events.php` | the shipped profile's own comment block — the knob grammar a deployer reads |
| `deptrac.yaml` | `Kernel += Event, Adapters` — the composition lane joins the port to its adapter from above |

## KDD-EQ1 — The census-moment doctrine honoured: the lane, the knob and the reader are one pull request

The `queue` lane joins `StorageLane` in the same change that lands
the wired default that reads it, answering the doctrine the
`sessions` lane spelled first and DU10 carried verbatim through three
ledgers. The consequence a deployment must know: a declared
`storage.root` now provisions — and the whole-tree walk demands —
seven lanes, not six. The skeleton ships `files/queue/`, and the
`storage.php` comment says so plainly; a deployment upgrading with a
custom root creates one more directory, or the walk refuses with the
lane named (the same first-moment verdict every misprovisioned tree
already earns). A lane that nobody wrote would have been the opposite
bargain: a directory every deployment must provision while no code
ever touches it — the drift the census-moment doctrine exists to
prevent.

## KDD-EQ2 — The store word is a closed vocabulary; the cross-process answers are named futures, not silent absences

The `events.deferred.store` knob reads '' (the shipped default: no
wired default is declared, the durable arm stays opt-in) or 'file'
(the filesystem answer over the `queue` lane). A typo or a foreign
word halts with the known list spelled — the same strict census every
family's knob grammar carries, because a `store: 'files'` misspelling
that fell through to the silent posture would leave a deployment
believing it asked for the wired default while every resolution keeps
refusing. The Redis answer already exists in the tree
(`RedisDeliveryStore` over the same port) and still does not earn the
word: its wiring needs its own knobs (host, port, the claim
semantics' visibility timeout) and therefore its own slice, its own
KDD, and its own census of what a deployment must declare — the word
arrives with the reader that can read it, never before. The refusal
message spells exactly one known word today for the same reason the
lane vocabulary spells exactly one purpose per lane: the list is the
contract.

## KDD-EQ3 — The precedence is one-way, and the privacy posture stays the adapter's own

An explicitly spelled `events.deferred.path` answers verbatim and
NEVER WALKS THE TREE — the KDD-CS2 posture replayed: the root is the
derivation's input, not a promise to validate a tree nobody consumes,
so a host spelling both an explicit path and a nonsense root gets the
explicit store, exactly as a host handing both cache instances over an
unusable root boots as it did before the layout existed. The derived
address, when the knob is empty, is the whole-tree walk's `queue`
lane over the declared root (KDD-ES4's doctrine: never a one-lane
shortcut that could drift from the map the inspector prints — the
error, session and cache families already answer to that same map).
The `file` word with neither an address nor a declared root is the
named halt the session family's combination refusal spelled first: a
directory the host never asked for is never silently defaulted.
WHERE is this fold's question; HOW PRIVATE stays the store's own
(`DeliveryStoreDirectory`: existing, writable, non-symlink,
owner-only, outside the public document root) — two questions, two
seams, so an explicit address is judged by the posture the store
itself already owns, never re-spelled here.

## KDD-EQ4 — The silent default is the honest refusal, and it fires only at the host's own ask

A null `events` block, an absent `deferred` map, or the empty store
word each declare no wired default — the arm stays opt-in exactly as
KDD-EA11 spells it structurally: a dispatcher that was never asked
for the durable vocabulary simply has no durable verbs to call. But
the service identity exists from the moment the profile boots, so the
honest answer to a host RESOLVING `events.deferred.store` without
declaring one is a named halt with the way out spelled: write the
'file' word, or compose the arm by hand over the port the way every
composition before this fold did. A null answer would have been
quieter and worse — a service identity that sometimes answers
"nothing", which every future consumer must then guard against; the
refusal keeps the identity's contract total.

## KDD-EQ5 — The registration is transient: the warmup never asks the family, and the books are the directory anyway

The singleton warmer eagerly resolves every planned singleton the
moment `aot.warm` is on — a shared-lifetime registration of this
family would make every warm boot refuse until the host declared a
store, which is the boot answering a question nobody asked it. The
transient lifetime is not a dodge but the honest lifetime for what
this store is: `FileDeliveryStore` owns no process state — the entry
book IS the directory, and the books (`dead/`, `attribution/`,
`quarantine/`, `drains/`) are created on demand by the folds that own
them — so two constructions over the same address answer the same
behaviour and sharing buys nothing. The boot test pins the posture
both ways: a warm boot over the silent knob stays green, and the
refusal still answers the host's own `get()` afterwards. (The warm
pin also records an existing-posture interaction this slice inherited
rather than introduced: the default profile arms the CSRF and auth
families without secrets, so ANY warm boot over the default profile
disarms or provisions them — the test disarms both, and the events
family's posture is the question under test.)

## KDD-EQ6 — The capacity knob is the admission bound, and the environment overlay folds to the integer it spells

The wired default's pending bound is a deployment judgment (the
backpressure ceiling a batch refusal spells with its counts), so it
is a knob with a reader: the declaration folds it into the store's
own constructor word, where `DeliveryBatchWrite`'s whole-batch
refusal already lives and is already tested on its own terms. The
integer seam accepts the all-digit string an environment overlay
writes (`APP_EVENTS__DEFERRED__CAPACITY=2048`) and rejects
everything else with the knob named — the same fold the HTTP
families' `StrictIntegers` owns for their own numeric knobs, spelled
in the events family's own words here because the composition lane
reads this one, not the HTTP lane. The default (1024) is declared
once in the profile and pinned against the shipped file by test, so
the two cannot drift.

## KDD-EQ7 — What stays OUT of the block stays out by name: the arm's remaining words are the composition's, not the configuration's

The retry policy, the quarantine threshold and the listener
resolution table are deliberately absent from the knob block: they
are consumed at the `EventDispatcher::durableDelivery()` composition
seam, where the host (or the module system's own events reader —
DU10/DQ8's named future, still future) spells them. A knob without a
reader is a dead key — the doctrine every family's arrival has held
since the storage layout's own first slice — and the module reader,
when it lands, is the change that may widen this block with the
arm's remaining words. The same honesty names the two composition
freedoms this slice does NOT negotiate: a host rebinding
`events.deferred.store` with its own definition wins (the container's
same-identifier rebind grammar, exactly as for every framework
registration), and a host composing its own store by hand keeps the
pre-fold posture unchanged (the port never knew a wiring existed).

## KDD-EQ8 — The deptrac widening is the composition lane joining the port to its adapter from above

`Kernel += Event, Adapters` is the first widening of its kind for
this lane, and the essays in `deptrac.yaml` record the direction:
the fold's answer is TYPED against the Event layer's
`DeferredDeliveryStore` port (the question that lane owns, reached
one-way exactly as the Adapters lane's own ruleset already spells
it), and the composition constructs the file answer the Adapters lane
owns (`FileDeliveryStore`, the `queue` lane's first writer). The
hexagonal direction is unchanged by the widening: the port still
depends on nothing (`Event: ~`), the adapter still depends on the
port and the layout, and it is the composition layer — the one place
that already owned the frozen configuration — that joins the two.
The adapter lane never learns that a configuration exists; the Event
lane never learns that a filesystem does.

## KDD-EQ9 — The gate's own lessons: the ceiling split the reading family, and the covers doctrine named the drift pin's home

The archeology gate refused the first spelling of the declaration
fold (difficulty 28 against the house ceiling of 20), and the split
it forced is the one the HTTP families already spell: a knob block
dense enough to read its own census, its vocabulary and its integer
seam stops being one class's question and becomes a family of folds
-- the family tree (`EventFamilies`, the `HttpFamilies` seam
mirrored), the knob census (`EventDeliveryKnobs`, the `StrictKnobs`
lineage), the store vocabulary (`EventDeliveryStores`, the
`SessionStores` lineage, the environment's spelling folded) and the
integer seam (`EventDeliveryCapacity`, the `StrictIntegers`
lineage) -- each under the ceiling on its own terms (12.00, 7.73,
11.00 against the declaration's 16-ish after the split). The
question split, never a metric dodge: each fold owns exactly one
question the family's grammar asks.

The mutation ledger taught the second lesson, and the SonarCloud
round taught its twin. The drift pin that holds the Default layer's
`events` block and the shipped `events.php` to the same knobs
ESCAPED two `ArrayItemRemoval` mutants on its first run, and the
manual reproduction proved the test kills them -- the pin was
simply invisible to the ledger, because the suite runs with
`requireCoverageMetadata` and the pin's home carried
`CoversClass(EventDeliveryDeclaration)` alone. The SonarCloud
new-code gate then failed the SAME slice at 54.4% coverage for the
same reason wearing the same costume: the four reading folds
(`EventFamilies`, `EventDeliveryKnobs`, `EventDeliveryStores`,
`EventDeliveryCapacity`) executed under the declaration's tests but
read at 0% in the clover report, because their attribution was
filtered to the class the metadata named -- #589's lesson, replayed
on a family that should have known it by heart. The
covers-attribution doctrine KDD-DU13 spelled first (the folds that
executed under `FileDeliveryStoreTest` read at 0%) has two faces:
a pin whose kill depends on lines OUTSIDE its named coverage is a
pin the ledger cannot see, however hard it kills by hand; and a
fold whose reading home does not name it is a fold NO coverage
consumer can see. The fix is the `AotMapTest` shape both times --
the test's home names every class its tests actually exercise: the
declaration's own family of folds and, for the drift pin,
`FrameworkDefaults::class` beside them. Two mutants died, the
ledger read 225/225, the new-code gate went green, and the lesson
stays here for the next family that ships a reading fold or a
profile drift pin.
