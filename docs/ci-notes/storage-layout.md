# The Storage Layout — KDD

**Scope:** the `files/**` route map — the cross-cutting substrate the
framework's file-writing families (the error and slow-request lanes'
JSONL sink, the replay snapshot store, the configuration and container
AOT caches, the uploaded-file moves) resolve their on-disk addresses
through, and the repository's own `files/` skeleton plus the
`bin/zef storage:map` inspector that keeps the tree honest in every
environment. Five source classes under `Zef\Framework\Storage`, two
rendering folds under the Tooling lane, one CLI arm,
`feat/storage-layout`.

**Verified locally before the PR:** `ci:static` EXIT-0 · psalm 0 new
errors (three baseline entries for the CLI-consumed classes — the
layout's own orchestrator and the two renderers — the route
inspector's own posture: consumed by `bin/zef`, which sits outside
psalm's project files) · progpilot `[]` · scoped infection over the
family's source files, 81 mutants (67 over the five Storage classes,
14 over the two renderers), MSI 100/100, 0 escapes, one
method-scoped `FalseValue` ignore (the document-root resolvers' own
doctrine entry, the third method joining the two the JSON log and
replay folds already carry) · archeology 0 error-level findings (the
root's first spelling answered the difficulty ceiling the house way:
the document-root geometry folded out, 20.89 falling to 10.71 with
the fold at 14.08).

## The map

| Unit | Question it owns |
|---|---|
| `StorageLane` | the lane vocabulary: the five canonical spellings, their order, their one-line purposes |
| `StorageRoot` | the root's own geometry: existing, writable, non-symlink, resolvable, outside the public document root |
| `StorageDocumentRoot` | the document-root geometry itself: the equal-or-inside judgement, failing closed on an unresolvable declared root |
| `StorageLaneDirectory` | one lane's directory under a resolved root, every refusal naming the lane |
| `StorageLayout` | the walk: root fold, then every lane in declaration order, fail-closed at the first refusal |
| `StoragePaths` | the answered map: the root, the typed per-lane path, the spelling-keyed whole |
| `StorageMapText` / `StorageMapJson` | the inspector's two renderings over the answered map |

## KDD-SL1 — the route map owns WHERE, never HOW PRIVATE

The layout deliberately does not duplicate the privacy posture its
consumers already own: the replay directory's owner-only permission
requirement stays in `ReplayStorageDirectory`, the log file's
private-file check stays in `JsonLogFilePrivacy`, and the layout's own
folds judge only the geometry every lane shares — existing, writable,
non-symlink, resolvable, outside the document root. Two questions, two
seams: consolidating the permission bits into the root would either
force the whole tree to the replay lane's strictness (a cache lane
that must be 0700 buys nothing) or silently relax the replay lane's
guarantee by moving it somewhere the replay store no longer reads. The
lane that needs the stricter posture keeps owning it.

## KDD-SL2 — the tree is validated, never created

The standing doctrine the error lane's log directory already spelled —
"the directory is never created for you" — is the layout's own too.
`StorageLayout::resolve()` refuses a missing root or a missing lane
with the defect named; it never `mkdir`s its way past an operator's
mount points. Provisioning is deployment tooling (a provisioning
script, a container image, a hand-run `mkdir` once), and the
inspector exists so the operator can verify the provisioning without
the framework having taken the decision for them. A framework that
silently materialises its own directories has decided where state
lives on a host that never answered.

## KDD-SL3 — a lane joins the vocabulary with its first writer

The vocabulary ships five lanes — `logs`, `cache`, `temp`, `uploads`,
`replay` — each with a live writer or a named reservation. It
deliberately carries no `sessions` lane: the session file driver is
the storage port's named reservation, and a lane without a writer is
a directory nobody provisions, nobody checks, and nobody notices
drifting. The census-moment doctrine (the cookie-attribute fold's own
spelling): the vocabulary grows in the pull request that lands the
consumer, and the skeleton pin (`StorageSkeletonTest`) reddens the
moment the tree and the vocabulary disagree.

## KDD-SL4 — the doctor's exit-code semantics: a missing tree is a verdict, not a usage defect

The route inspection commands answer a non-existent configuration
directory with exit 2 — a usage defect, because the directory is an
input to a parse the operator invoked. The storage inspector inverts
that judgement deliberately: the tree IS the subject of the
diagnosis, and a missing or misprovisioned tree is the primary
failure the doctor exists to report. `bin/zef storage:map` therefore
answers a missing root, a missing lane, a symlinked lane, an
unwritable lane and a root inside the document root all the same way
— STDERR naming the defect, exit 1 — while only the argument grammar
(unknown flags, a surplus positional) answers exit 2. An operator
scripting the doctor in CI provisions the tree, runs the command, and
reads a single honest channel: 0 means the map answered, 1 means the
tree is wrong and STDERR says where.

## KDD-SL5 — the consistency instrument is the map plus the doctor, not absolute paths

The doctrine the slice exists for: the lane names and their structure
are the contract (`files/logs`, `files/cache`, `files/temp`,
`files/uploads`, `files/replay`), and the only per-environment
variable is the root the deployment declares — the sandbox points at
a scratch tree, development at the checkout's own `files/`, production
at the provisioned volume. The same `bin/zef storage:map` run in all
three prints the same five lanes in the same order with the same
purposes, differing only in the addresses; `--json` gives a
provisioning script or a CI check the same shape to read. The
`ZEF_STORAGE_ROOT` environment variable rides the same doctrine the
replay arm's own `ZEF_ERROR_REPLAY_DIR` spelled: a declared root
without touching the invocation.

## KDD-SL6 — the ad-hoc conventions consolidate in their own slices, by name

The slice deliberately does not rewire the existing path surfaces: the
CLI's own `DEFAULT_ERROR_LOG` (`var/log/zef-errors.jsonl`), the
`http.error_handling.log_path` and `replay_path` knobs, and the
config/AOT cache paths all keep their current semantics. Each
integration is a named follow-up in its owning family's lane — the
error lane's log address deriving from the layout, the replay
directory defaulting to the `replay` lane, the configuration cache
defaulting to the `cache` lane — because each integration is a knob
semantics change its family's own tests must renegotiate, and a
mid-flight queue holding another lane's fix is not the place to
renegotiate them. The named reservations are the contract; the
consolidation arrives with the consumers.

## KDD-SL7 — the answered map is a snapshot, never a live view

`StoragePaths` carries no filesystem calls: the addresses it hands
out are the ones the walk judged. A tree that changes after the walk
is a new walk — the caller who needs a fresh verdict takes one, and a
long-lived process (a persistent worker holding a boot-time map) is
documented by this decision as holding a snapshot. The alternative —
a map that re-stats on every read — would put filesystem latency on
every lane lookup and make two consecutive reads disagreeable, which
is a worse contract than an honestly-stale one.
