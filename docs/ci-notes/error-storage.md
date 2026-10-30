# The Error Family's Storage Wiring — KDD

**Scope:** the storage layout's first named consumer integration (the
follow-up KDD-SL6 reserved): the error family's on-disk addresses — the
one structured log address the error, slow-request and process-boundary
lanes share, and the replay directory the snapshots land in — now derive
from the `files/**` route map when the host declares a storage root, and
the CLI inspector's own `var/log` default migrates onto the same map. One
wiring fold (`ErrorStorageWiring`) plus its declaration fold
(`StorageRootDeclaration`) under the Middleware lane, one shipped
profile knob (`app/configs/storage.php` → `storage.root`), the three
factories' and the replay fold's reads rerouted through it, and the
`bin/zef errors:report` / `errors:clear` default arm,
`feat/error-storage-lane`.

**Verified locally before the PR:** `ci:static` EXIT-0 (6011 tests /
14552 assertions; smoke 2760B/20000 iterations; roadmap:gate pass;
audit 0 advisories) · coverage 98.50% against the 90% floor · psalm
EXIT-0 with the baseline SHRINKING one entry — `StorageLayout`'s
`UnusedClass` line left the baseline the moment this slice consumed it
from inside `src/`, the fold's whole point made measurable · progpilot
`[]` · scoped infection over the six touched Middleware files, 114
mutants MSI 100/100, 0 escapes, no new ignore entry · archeology 0
error-level findings — the wiring's first single-class spelling answered
the difficulty ceiling at 26.50 against the house limit of 20, and the
fold split answered it the house way: the strict census moved out into
`StorageRootDeclaration` (difficulty 17.50) and the wiring fell to
18.60, the same fold-split discipline `StorageDocumentRoot` and
`EventBook` already carry.

## The map

| Unit | Question it owns |
|---|---|
| `ErrorStorageWiring::logAddress()` | the deployment's ONE structured log address: explicit knob verbatim, else the `logs` lane + the canonical file name, else the empty no-sink posture |
| `ErrorStorageWiring::replayDirectory()` | the replay lane's offer: the `replay` lane's address, or the empty string when no root is declared |
| `StorageRootDeclaration::root()` | the storage map's strict census: the declared root, or null when the map stays silent, every malformed shape refused by name |
| `ErrorStorageWiring::paths()` (private) | the walk over the declared root — the layout's own whole five-lane resolve |
| `app/configs/storage.php` | the shipped profile's declaration of the family's one knob, silent by default |
| `bin/zef` errors arm | the CLI's default log address: positional path, then `ZEF_ERROR_LOG`, then the layout's `logs` lane |

## KDD-ES1 — the precedence is one-way: an explicitly spelled address always wins

The derivation never overrides the deployment's own words. An explicit
`http.error_handling.log_path` answers verbatim whatever the storage map
declares; the `logs`-lane derivation only fills the knob the host left
EMPTY; and a declared root with no storage map at all is the same silent
posture as before the layout existed. The same one-way rule holds on the
replay side, with one deliberate asymmetry: `logAddress()` answers the
EFFECTIVE address (the three factories' single seam — they never see the
knob again), while `replayDirectory()` answers only the layout's OFFER
and leaves the explicit-knob precedence to the replay lane's own grammar
fold (`ErrorReplayWiring`), because that fold already reads, validates
and combines the knob it owns (the debug/APP_ENV gate) — two folds
rewriting one knob's precedence would be two places the security posture
could drift apart. The widening is honest in exactly one direction: more
ways to SAY where the artifacts land, never a lower bar for what lands
there.

## KDD-ES2 — an empty root declares no layout: the silent default is an honest no-op

The shipped profile's `storage.root` is the empty string, and an empty
root is indistinguishable from an absent `storage` map: no walk, no
derivation, every knob's semantics exactly as it stood before the slice.
This is the migration story — a deployment that upgrades and provisions
nothing observes nothing change, and the ad-hoc posture stays a first
class citizen rather than a deprecation to be flushed. It also keeps the
"every knob must be live" doctrine intact: `storage.root` is never a
dead key, because the moment it carries an address it provisions two
defaults at once (the log address and the replay directory) — the knob
is either silent or load-bearing, never decorative.

## KDD-ES3 — the storage map's census is strict: one knob, `root`, and a typo never falls through

The declaration fold (`StorageRootDeclaration`) reads the `storage` map
the way every family's knob map is read in this repository: a map that
is not a map, a key that is not `root`, or a `root` that is not a string
each refuses with its own name and the known list spelled — because a
`storage.rooot` typo must never fall through to the ad-hoc posture
unnoticed, which would be the worst failure shape available: a
deployment that believes it declared the map and silently did not. The
shipped profile's own declaration is pinned by test against the same
census, so the `storage.php` file and the fold's strict reading cannot
drift apart. The census lives in its own fold rather than inside the
wiring because the two questions are different seams — WHAT the host
declared (a pure configuration read, the fold the whole family and any
future family can share) versus WHERE the family's artifacts land (the
wiring's precedence and walk) — and because the single-class spelling
answered the architecture gate's difficulty ceiling at 26.50 against
the house limit of 20: the split leaves the declaration at 17.50 and
the wiring at 18.60, the same fold-split answer `StorageDocumentRoot`
and `EventBook` already carry.

## KDD-ES4 — the walk is the layout's own whole five-lane walk, never a two-lane mini-walk

The error family needs exactly two lanes (`logs` and `replay`), but the
fold rides `StorageLayout::resolve()` — the full walk over all five
lanes, fail-closed at the first refusal, each defect named. A
two-lane mini-walk would be faster and wrong: it would let a deployment
whose `cache/` or `uploads/` lane is missing or misprovisioned boot
green and discover the defect only when the next family integrates,
splitting one provisioning verdict into as many partial answers as there
are consumers. The error family answers to the same map the inspector
prints (`bin/zef storage:map`), which is the whole consistency mandate:
one declared root, one walk, one honest verdict, in the sandbox, in
development and in production.

## KDD-ES5 — the fold's seam is the Middleware composition lane; the map stays blind to who asks

`ErrorStorageWiring` lives under `Middleware`, and the deptrac widening
is the one the lane's comment records: the composition lane already owns
the factories' config-reading posture, and the derivation is exactly
that — a read of the frozen configuration answered through a pure value
component — so the fold's face lives where the factories live, and
`Storage` stays the innermost layer it was designed to be (empty
ruleset, no knowledge of any consumer). The alternative placements each
fail a standing doctrine: a `Storage`-layer placement would drag
configuration reading into a layer whose KDD-SL record says it owns
none; a `Config`-layer placement would make the configuration system
know about lanes and file names that belong to the error family's
vocabulary. WHERE the family's artifacts land is the family's fold over
the map, not the map's business.

## KDD-ES6 — the CLI's var/log migration rides the same precedence, and a refused layout is a verdict, not a usage defect

`bin/zef`'s error arm drops its private `DEFAULT_ERROR_LOG` (`var/log/`
— the ad-hoc convention KDD-SL6 named) and derives its default from the
same map: a positional path still wins (the operator's explicit word),
then `ZEF_ERROR_LOG` (the env doctrine the replay arm's
`ZEF_ERROR_REPLAY_DIR` already spelled), then the storage root —
`ZEF_STORAGE_ROOT` when the environment declares one, else the
checkout's own `files/`, exactly the `storage:map` inspector's own
default. The refusal posture follows the inspector's, not the route
commands': a tree that fails the walk answers STDERR naming the seam and
exit 1 — the tree IS the subject of the diagnosis — while the argument
grammar keeps exit 2. An operator script that pinned the old `var/log`
address keeps working by passing the path positionally or through the
env knob; nothing about the migration is silent.

## KDD-ES7 — WHERE and HOW PRIVATE stay two questions in two seams, and the remaining integrations stay named follow-ups

The fold answers WHERE and nothing else: the JSON log destination's own
private-file check and the replay directory's owner-only requirement
stay exactly where they already live (the consumers' own validators),
because the route map's record already draws that boundary (KDD-SL1)
and a wiring fold is not a place to redraw it. Two integrations from
KDD-SL6's named list remain open, each its own slice with its own knob
renegotiation: the configuration and container AOT caches' default to
the `cache` lane (their callers' paths are construction-time addresses
owned by the boot seams, not error-family knobs), and the `sessions`
lane, which joins when the session file driver lands (the
census-moment doctrine: a lane without a writer is a directory nobody
provisions). This slice deliberately touches neither.
