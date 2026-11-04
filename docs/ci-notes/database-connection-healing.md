# Database connection healing and the pooling question — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record, refining it
where the verdicts below name their open edges; the KDD numbering
arrives with the implementation, not here.

## The slice in one paragraph

The open ledger's item — "the pooling posture the lazy-handle truth
documents" — is TWO questions bundled under one word, and this record
splits them apart before any code exists: **(a) mid-flight
lost-connection healing**, the recommended landing scope, and
**(b) socket pooling**, parked with the opening condition that would
justify it named. The lazy handle's own truth (`PdoHandle`, the lazy
singleton KDD-DW1 spelled: one socket per connection identity,
materialised at first use) is the honest baseline both questions are
asked against.

## Question (a): the socket dies mid-flight — who heals it?

The taxonomy already classifies the death: `RetryCandidates` carries
the lost-connection codes (2006, 1040 beside the deadlock and lock
family) and `RetryMessages` the Unicode-folded containment over the
message lane; the UnitOfWork already owns disciplined replay
(`flushRetrying`, the snapshot replay the orchestration slice pinned).
What the tree lacks is the adapter-side fact: a socket lost mid-flight
stays lost — every later ask on the same connection identity fails on
a dead handle nobody drops.

The reference's answer (Laravel's `DetectsLostConnections`: classify
the loss, disconnect, let the caller's retry land on a fresh
connection) translates into the tree's vocabulary as
**heal-and-propagate, never blind replay**:

- On a classified lost-connection failure, the lazy handle is
  **dropped** — the next ask re-materialises the socket, the lazy
  singleton's own doctrine replayed at heal time.
- The classified exception **still propagates untouched**. The caller's
  retry policy (the UnitOfWork's, or the host's own) decides whether
  to retry — because an adapter-side blind replay of an `INSERT` can
  double-write, and the tree never answers a question that dangerous
  by default. Healing is about the NEXT ask landing fresh, not about
  pretending the failed one succeeded.
- **The mid-transaction edge is the pin the implementation owes:** a
  socket lost inside a transaction means the transaction is GONE (the
  engine's own rollback-on-disconnect — there is nothing to roll back
  to), so the savepoint machine's depth state must RESET with the
  handle, or the connection answers a phantom depth every later
  `beginTransaction` refuses.

## Question (b): pooling — parked, with the opening condition named

PHP's shared-nothing request model gives a web request nothing to
pool: one connection per identity per request is already the honest
shape, and the persistent worker (the event drain lane) already holds
its connection through the same lazy singleton. A pool answers
CONCURRENT use of one identity from one process — a question no lane
currently spells. The parking rests on the doctrine every knob
carries, extended to sockets: **a pool with no concurrent consumer is
dead weight the tree would maintain for nobody.** The opening
condition is named, not scheduled: a parallel-consumer lane (a worker
pool over the process-parallel runtime the dev tree already carries)
arriving with a real concurrency requirement re-opens question (b) as
its own slice, over its own record.

## The units sketch (question (a) only)

| Unit | Question it owns |
|---|---|
| the healing fold (adapter side, over `PdoHandle`) | on a classified lost-connection failure: drop the handle, reset the transaction depth, propagate the exception untouched |
| the classification seam | the engine-side taxonomy (`RetryTaxonomy`/`RetryCandidates`) reached one-way from the adapter — the same one-way reach the audit emission's meter contract carries |

## What the implementation must answer

- The `RecordingPdo` double (already in the test support tree) armed
  to throw the classified codes: the first ask fails, the handle
  heals, the second ask lands on the fresh socket — pinned.
- The depth-reset pin: a socket lost at depth 2, healed, then a new
  `beginTransaction` answers level 1, not a phantom 3.
- The propagate-untouched pin: the classified exception the caller
  catches is the exception the adapter saw — never wrapped, never
  swallowed, never converted into a success.
- The full battery, archeology under the ceiling, scoped infection
  MSI 100/100, the roadmap row at landing.

## What this slice deliberately does not carry

Blind replay (refused above, permanently); a ping/keepalive verb (a
socket proven alive one millisecond ago can still die — the honest
answer to a stale socket is the heal, not the prophecy); pooling
itself (question (b), parked); per-ask connection factories (the
transient-forbidden doctrine KDD-DW1 already spelled for connections).
