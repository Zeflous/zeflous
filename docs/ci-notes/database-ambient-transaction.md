# Database ambient transaction — the decision record

**Status:** the design record landed first (draft #706, codeless, so the
implementation arrived against a written contract); the KDD numbering
below is the implementation's refinement of that record, arrived with
the code — the record's own OPEN question (the container identity)
answered as KDD-AT5 spells.

## The slice in one paragraph

The open half of the transaction story: code deep in a call stack asking
"run me in a transaction if none is open, else join the one that is"
WITHOUT threading the connection through every signature. The tree
already owns the explicit half whole (`TransactionManager::withTransaction()`
with its rollback-on-throw, the savepoint machine for nesting, the
after-commit hook drain); this slice lands the HOLDER that owns the
question — `AmbientTransaction`, two verbs over the manager, plus the
wiring answer the record left open: the transient `database.ambient`
registration the boot catalogue now carries.

## KDD-AT1: the verdict — a holder, never a second machine

The ambient question is an ORCHESTRATION question, so the answer is
engine-side over `TransactionManager` and the port stays ambient-blind:
the connection never learns a scope exists, exactly as it never learned
the slow-hook guard or the UnitOfWork's retry policy existed. The
holder owns exactly two verbs and NO state of its own: `isOpen()`
delegates to the manager's own `inTransaction()` — the verb the
orchestration family already answers — and `around()` is the manager's
managed scope under the connection-free closure. A second bookkeeping
the holder could drift from is refused by construction: there is one
owner of the scope truth (the manager), and the holder is vocabulary,
not machinery. `inTransaction()` already existed on the manager — the
holder delegates, never re-bookkeeps; the design record spelled this
and the code is its exact shape.

## KDD-AT2: the closure receives NOTHING — the arity is the vocabulary

`around(callable $fn)` hands the body a zero-argument closure: the
connection is the noise the ambient question exists to remove, so the
body is spelled connection-free and the deep frame's writes ride its
own references (gateway, repository, unit of work). The flush owner
keeps the connection-bearing `withTransaction()` spelling — the tree
never loses it, it stays one ask away. At the adapter seam the
bracket's one argument (the connection the manager's contract hands
every closure) is discarded through a seam that stays TYPED to the
manager's current contract: `static fn (ConnectionInterface
$connection): mixed => $fn()` — a contract drift breaks loudly at this
seam rather than silently at an untyped edge, and the deliberate
discard carries its own inline psalm suppression with the argument
spelled (the one `UnusedClosureParam` in src; receiving nothing IS the
vocabulary). The arity is pinned by the tests themselves: a closure
that demanded a connection would TypeError against
`@param callable(): T`.

## KDD-AT3: joining IS nesting — one commit for the whole ambient tree

An inner `around()` inside an outer one brackets at depth+1 as the
savepoint the nesting machine already spells (`zef_sp2`, one SAVEPOINT
per depth) — never a second transaction. The verb-stream pin: outer +
inner around() answers exactly `BEGIN, SAVEPOINT zef_sp2, RELEASE
SAVEPOINT zef_sp2, COMMIT` — one BEGIN and one COMMIT. The inner throw
rolls back only its savepoint span (`ROLLBACK TO SAVEPOINT zef_sp2`,
the outer scope still committing its own writes); the outer throw rolls
back the whole scope including every nested span; either way the
throwable propagates untouched, the SAME instance — rollback-on-throw
is the port's own doctrine, inherited not renegotiated. The ambient
half joins the explicit half by the same spelling: an `around()`
inside a `withTransaction()` body brackets as the savepoint too — both
halves over the one machine, the two-case behaviour is the savepoint
machine's own nesting, never re-spelled here.

## KDD-AT4: the once-only hook drain — inherited, not re-drained

After-commit hooks registered inside the INNER scope drain at the
OUTERMOST commit, exactly once, in FIFO order — the drain discipline
the orchestration slice pinned, inherited whole: an inner boundary
releases a savepoint, never the transaction, so no hook drains there
(the inner-registered hook waits), and the outermost commit drains
every hook of the whole tree in one pass. The holder adds no second
drain — it never touches `TransactionHooks` at all; the once-only
behaviour is the manager's own, pinned by test over the holder
because the holder is the vocabulary the next reader will reach for.

## KDD-AT5: the container identity — TRANSIENT, and why that is safe

The record left the question OPEN (transient registration versus
hand-composition); the wiring fold answers it: the `database.ambient`
registration carries the TRANSIENT lifetime the `database.unit_of_work`
registration spelled — a fresh holder every resolution, never a
singleton, never static. A shared holder would be the hidden
request-global coupling the never-at-the-boot doctrine refuses (the
same refusal the record spelled). The transient posture is SAFE BY
CONSTRUCTION here, not by discipline: because the holder owns no state
and delegates to the manager (KDD-AT1), two holders resolved in one
ask are observably the same ambient scope — one holder's `around()`
opens the scope and a second holder's `isOpen()` inside it answers
true, one manager owning the truth. The composition seam's fold
(`DatabaseWiring::ambient()`) takes the manager the registration hands
in, the same hand-in shape every database fold answers.

## KDD-AT6: no knob until the consumer asks

The census-moment doctrine (KDD-DW7's own spell): the holder ships as
a service identity the host composes, with no knob of its own — a knob
without a live reader is a dead key the tree refuses. The named
follow-up consumer is the transaction-per-request HTTP middleware: the
day a deployment asks for it, that slice arrives with its own knob
block (membership = declaration, the slow-request family's shape, the
compression family's doctrine) over this holder. `isOpen()` carries no
isolation widening either: the outermost declaration wins, spelled and
pinned by the port's own contract, not a knob here.

## KDD-AT7: what this slice deliberately does not carry

The transaction-per-request middleware (the named follow-up consumer,
its own slice with its own knob block); isolation-level propagation
from the ambient scope (the outermost declaration wins — spelled,
pinned, not a knob); any REQUIRES-NEW suspension semantics (a second
independent transaction inside an open one is a
connection-per-transaction question the port's own shape refuses —
named, not scheduled); distributed/two-phase transactions (out of
scope for this tree, permanently); and any ambient bookkeeping beside
the manager's own (refused in KDD-AT1).

## Verification

- `AmbientTransactionTest` — nine tests over the real SQLite socket
  (the orchestration family's own harness): the outermost bracket
  (body's value answered, writes applied at commit, scope closed),
  the nesting pins (the verb stream `BEGIN/SAVEPOINT/RELEASE/COMMIT`
  over `RecordingPdo`; the inner throw rolling back only its savepoint
  span with the outer writes surviving; the outer throw rolling back
  the whole scope with the SAME throwable instance propagating), the
  explicit-half join (an `around()` inside a `withTransaction()` body
  bracketing as the savepoint), the hook drain (inner-registered
  hooks waiting past the inner boundary, draining at the outermost
  commit once, FIFO), and the `isOpen()` delegation (managed scopes
  only — a raw connection transaction stays invisible, the
  orchestration contract's own truth).
- `DatabaseWiringTest` — the fold pin (the holder over the handed
  manager carrying the closure's write into the one commit) and the
  booted-identity pin (the `database.ambient` resolution answering the
  holder, a fresh holder every resolution, and the delegation safety
  pin: one holder's scope open, the second holder seeing it, the scope
  closing at the outermost boundary). `ApplicationBootstrapperTest`
  pins the registration in the boot catalogue audit.
- Scoped mutation rounds over the two touched source files: the holder
  **2 mutants, 2 killed** and the wiring **27 mutants, 27 killed** —
  MSI 100/100, zero escapes, zero new infection ignores.
- The psalm ledger moves with the landing: the holder's two verbs
  enter the baseline (consumers in tests, the position every
  service-identity class holds), while `inTransaction` +
  `withTransaction` LEAVE `TransactionManagerInterface`'s block — the
  holder's delegation and bracket are the first src-side consumers of
  the two verbs, so the stale entries came out (the
  `UnusedBaselineEntry` gate's own instruction); the one inline
  `@psalm-suppress UnusedClosureParam` in src carries the adapter-seam
  argument (KDD-AT2).
- Full battery on the branch head: 7188 tests / 17487 assertions;
  phpstan 0 errors; psalm 0 errors; deptrac 0 violations (the holder
  sits inside the Database layer, no new edge); duplication + mess
  gates green; archeology 0 error-level findings; zero-deps, smoke
  (2760 bytes / 20000 iterations), workflow-concurrency and the
  roadmap gate green.
