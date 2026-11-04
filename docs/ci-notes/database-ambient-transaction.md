# Database ambient transaction — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record, refining it
where the verdicts below name their open edges; the KDD numbering
arrives with the implementation, not here.

## The slice in one paragraph

The ambient scope: code deep in a call stack joining the OUTERMOST open
transaction without threading the connection through every signature —
the "run me in a transaction if none is open, else join the one that
is" question. The tree owns the explicit half already (`TransactionManager::withTransaction()`,
the port's `transaction()` with its rollback-on-throw, the savepoint
machine for nesting, the after-commit hook drain); the open half is
the HOLDER that answers "is a scope already open here?" — the shape
the wider ecosystem spells as Spring's `@Transactional` or .NET's
`TransactionScope`, and the shape this record sketches as a per-ask,
stateful object the composition seam hands to the frames that opt in.

## The question it owns

"Am I already inside one?" — the question every layered service method
asks when its unit of work is a transaction but its caller may have
opened one already. Today the honest answers are threading the
connection through every frame (the noise the ambient question exists
to remove) or bracketing blindly (a nested explicit bracket that
commits the inner scope before the outer one is done — the partial-
commit footgun the savepoint machine exists to prevent, arriving by
accident instead of by declaration).

## The units sketch

| Unit | Question it owns |
|---|---|
| `AmbientTransaction` | the holder: `isOpen()` (delegating to the manager's own `inTransaction()` — the verb the orchestration family already answers) and `around(callable)` — open the managed scope when none is open, join when one is |
| the joining semantics | joining IS nesting: the savepoint machine already spells the depth walk (`level()`, one SAVEPOINT per depth) — an inner `around()` inside an outer one brackets at depth+1, rolls back to its savepoint on throw, and the outermost commit drains the after-commit hooks ONCE (the drain discipline the orchestration slice pinned; the holder adds no second drain) |

## The decisions to carry

- **Engine-side over `TransactionManager`; the port stays
  ambient-blind.** The ambient question is an orchestration question —
  the connection never learns a scope exists, exactly as it never
  learned the slow-hook guard or the UnitOfWork's retry policy
  existed.
- **Rollback-on-throw unchanged.** The port's own doctrine, inherited
  not renegotiated: a throw anywhere inside the scope rolls back what
  the scope owns, then propagates untouched.
- **Lifetime: per-ask, TRANSIENT.** The holder is a stateful object
  scoped to one ask (one request, one job, one command run) — the
  `database.unit_of_work` registration's own posture. Never static,
  never request-global by construction: a global ambient scope is a
  hidden coupling the tree refuses (the same refusal the
  never-at-the-boot doctrine spells for the connection).
- **No knob until the consumer asks.** The census-moment doctrine
  (KDD-DW7's own spell): the holder ships as a service identity the
  host composes; the day a deployment asks for automatic
  transaction-per-request, the HTTP middleware consumer arrives with
  its own knob block — the slow-request family's shape (membership =
  declaration, the compression family's doctrine).
- **OPEN — the container identity.** A transient `database.ambient`
  registration (the composition seam hands the same holder to every
  frame of one ask) versus hand-composition by the bootstrapper: the
  implementation answers with the wiring fold, and the answer rides
  the KDD.

## What the implementation must answer

- The nesting pins: outer `around()` + inner `around()` = ONE commit,
  the inner throw rolling back only its savepoint span; the outer
  throw rolling back the whole scope.
- The once-only hook-drain pin: after-commit hooks registered inside
  the inner scope drain at the OUTERMOST commit, exactly once.
- The `inTransaction()` delegation pin: `isOpen()` is the manager's
  own truth, never a second bookkeeping the holder could drift from.
- The full battery, archeology under the ceiling, scoped infection
  MSI 100/100, the roadmap row at landing.

## What this slice deliberately does not carry

The transaction-per-request middleware (the named follow-up consumer,
its own slice with its own knob block when a deployment asks);
isolation-level propagation from the ambient scope (the outermost
declaration wins — spelled, pinned, not a knob); any REQUIRES-NEW
suspension semantics (a second independent transaction inside an open
one is a connection-per-transaction question the port's own shape
refuses — named, not scheduled); distributed/two-phase transactions
(out of scope for this tree, permanently).
