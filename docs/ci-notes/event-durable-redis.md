# Event Durable Redis — The Wire Shim, The Claim, The Sorted-Set Index

KDD-DR1..DR8 for `src/Zef/Framework/Adapters/Redis/` +
`src/Zef/Framework/Adapters/Event/` — the Adapters lane's third
slice over the Event arm, KDD-DU12's second named follow-up ("the
cross-process bus adapters -- Redis/RabbitMQ/Kafka over the same
`DeferredDeliveryStore` port, with the claim semantics and the
indexes the file adapter's honesty ledger names") and the ROADMAP's
own `RedisEventTransport` sketch: the first cross-process answer to
the persistence port, over a RESP wire this tree owns. The claim is
the exclusivity the file adapter declared absent; the sorted set is
the index its scan refused to be; the record spelling is the Event
lane's own encoder, unchanged — the record is the same JSON either
side of the store choice. `feat/redis-delivery-store`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5950
tests / 14414 assertions; smoke 20000 iterations at 2760 bytes;
concurrency 23 workflows; audit 0 advisories) · psalm EXIT-0 (two
baseline entries, the file adapter's own test-only-consumer
posture: the store class and the connection's constructor) ·
progpilot `[]` · scoped infection over the slice's fifteen source
files, 348 mutants, MSI 100/100, 0 escapes (one timeout kill: the
EOF refusal's throw removed is a spin the suite strangles) ·
archaeology 0 error-level findings (every fold under the 20
difficulty ceiling, the deepest at 19.04) · coverage 98.48%
whole-tree with the new folds at 100%.

## The map

| Unit | Question it owns |
|---|---|
| `RespCommandEncoder` | the request grammar's writing half (binary-safe, the integer cast at one boundary) |
| `RespSocket` | the byte corridor: one stream, the partial write written past, the raw chunk read |
| `RespReader` | the buffered read discipline: lines and measured runs reassembled from whatever the engine delivered |
| `RespBulkReply` | the bulk reply's own decode: the null spelling, the measured payload, the trailer verified |
| `RespReplyDecoder` | the five reply types, pulled one reply at a time, fail-closed in every direction |
| `RespConnection` | the client's one face: the endpoint's own grammar, one command carried, one reply decoded |
| `RespReplyRefusal` | the refusal both shapes are named by (the decoded shape's own spelling) |
| `RespReplyShape` | whether a decoded reply is the SCALAR shape its command answers |
| `RespReplyListShape` | whether a decoded reply is the array shape a range command answers |
| `RespReplyMultiReadShape` | whether a decoded reply is the array shape a multi-read command answers |
| `RedisDeliveryKeys` | the key vocabulary one prefix hangs (entry / pending / claim / dead) |
| `RedisDeliveryAdmission` | the batch's admission: capacity read from the index, record before member, the compensation whole |
| `RedisDeliveryClaim` | which due entries THIS drainer holds exclusively |
| `RedisDeliveryClaimedRecords` | the claimed identities become the deliveries they spell (the zombie completed quietly) |
| `RedisDeliveryStore` | the facade over the port: the six verbs as the command orderings they take |

The fold count is the difficulty ceiling's own demand, the EA11a
precedure replayed five times over: the first spelling held the
whole wire answer on one socket at 31.85, the whole decode at
23.53, and the whole narrowing at 28.56 -- the gate refused all
three, and every seam above is the question a ceiling forced out
(reader, bulk, refusal, and the narrowing's own three-way split by
the command family it answers). The deepest survivors stand at
19.04 and below, the gate answers zero error-level findings, and
the narrowing's static refusal arm is the fold that taught the
cheapest lesson: an instance-per-fold constructor is an operand the
pool did not need -- the refusal as a static verb dropped the
scalar shape from 20.00 to 16.00 without moving a single guard.

## KDD-DR1 — The wire is ours: the zero-library shim doctrine, replayed for the Redis protocol

The HTTP lane vendored the PSR message contracts and implemented
them natively rather than depending on a library; this slice is the
same doctrine on the Redis side of the tree: the RESP2 grammar —
five reply types, one request spelling, CRLF lines, measured bulks —
implemented in-repo over the engine's own streams
(`fsockopen`/`fwrite`/`fread`), so the cross-process answer needs
nothing but the bare PHP 8.4 install the zero-dependency rule
already promises. The rate-limit family's phpredis boundary stays
its own posture — the OPTIONAL extension (suggested in composer.json,
union-typed, engine-checked, stubbed for the analysers) answering
its three commands — and the two postures do not merge: a durable
store that hard-refuses without an optional extension would answer
the cross-process demand with a dependency the durability promise
cannot carry, while a rate limiter is one store among three a
deployment picks. Two families, two honest boundaries, both
zero-dependency.

## KDD-DR2 — The claim is the exclusivity the file adapter declared absent

The file adapter's honesty ledger spelled it: single-drainer by
declaration, two concurrent drainers both take the same due entries,
"the exclusivity the cross-process bus adapter's claim semantics
are named to answer". The answer lands here: one claim key per due
entry, armed by `SET <claim:{id}> <token> NX PX <visibility>` — the
atomic write that only succeeds unclaimed, carrying its own expiry.
A foreign claim is respected (the second store's `takeDue` answers
nothing — pinned over the real mock server by two stores on two
connections, the cross-process story this slice exists to tell); a
claim this drainer already holds is RE-TAKEN, because the port's own
promise is that an entry taken but not settled is re-taken by the
next pass — the `GET`-after-nil arm comparing the holder against the
store's own drainer token (minted from the entry identity grammar at
construction); and an expired claim is the visibility timeout — the
crashed drainer's entries hand themselves to the next one after the
timeout, the crash window that costs a redelivery, never a loss.
DU1's arithmetic survives the exclusivity intact: every window costs
a duplicate.

## KDD-DR3 — The sorted set is the index, and the index is the DU9 ordering for free

The file adapter's scan reads EVERY pending record and names the
index as "the Redis and PDO adapters' own business". The Redis
answer: the pending book is one sorted set — members ARE the entry
identities, scores ARE their ready-at moments — so the due selection
is one `ZRANGEBYSCORE <pending> -inf <now>`, no record read at all
for the not-yet-due, and the answer's order is the set's own
(score, member) order: ready-at first, entry identity second,
bytewise — exactly the DU9 ordering doctrine the file scan had to
sort by hand. The index is also the count (`ZCARD`): admitted,
not-yet-due, and taken-but-not-settled entries all still hold their
membership — the pendingCount question the port spells — because
claims live in their own keys and settle is what removes a member.

## KDD-DR4 — The crash windows are the command orderings, and every one costs a duplicate

Settle deletes the record BEFORE the index member: the crash between
the two leaves a zombie member whose next claim answers no record —
and the records walk completes that settle quietly (a `ZREM`, no
delivery), the self-healing half of the idempotent-settle doctrine,
pinned over the real server by an operator `DEL` behind the store's
back. Requeue writes the retry's record before its index member: the
crash between the two strands an orphan record no verb reads —
inert by construction, the index is the only door — never a
half-admitted entry a drain would have to guess at. The admission's
compensation is OPTIMISTIC: every begun entry is tracked before its
first write, so a refused mid-write batch removes both halves of
every entry it had begun (a refused write may still have reached the
server — the wire's own ambiguity — and the idempotent `DEL`/`ZREM`
removal answers both halves of it), the stranded count spelled when
even the compensation cannot complete. The claim's PX expiry bounds
the one window the file store could only declare: a drainer that
dies mid-delivery leaves its claims to expire, and the entries are
re-taken — redelivered, never lost.

## KDD-DR5 — The refusal family is the port's own, end to end

The persistence port declares `InvalidArgumentException` from every
verb, and the file lane already spells its I/O failures that way —
so the wire refuses in the same vocabulary: the refused connection
naming the address and the engine's own cause, the refused write,
the dead stream, the end-of-stream mid-reply, the corrupt type byte,
the non-numeric length, the negative length that is not the null
spelling, the bulk without its trailer, and the server's own `-ERR`
reply carried out with its message intact. The narrowing fold
(`RespReplyShape`) closes the last gap: a reply that decodes into
the wrong shape for its command is refused with BOTH shapes named —
a reply that coerces past its shape is a reply the command never
sent. One family, one catch, from the port verb to the socket read.

## KDD-DR6 — The record is the Event lane's own spelling, unchanged

`DeliveryEnvelopeEncoder`/`DeliveryEnvelopeDecoder` are reused
verbatim — the same six-key JSON, the same float and `PHP_INT_MAX`
boundaries, the same fail-closed decode — because the record is the
port's data vocabulary, not the adapter's: a deployment that moves
its store from files to Redis moves records that spell identically,
and a decode refusal names the entry identity the way the file scan
names the filename. The dead lane is the same doctrine on the
operator's side: one `SET <dead:{id}> <story>` per spent entry —
the same story rewritten is the same record replaced, never doubled
(pinned by the dead-at moment the second write leaves) — enumerable
by the key pattern an operator types at the server's own console.

## KDD-DR7 — The test tiers, and what each one is for

The scripted tier (the file delivery boundary's own function-double
doctrine, replayed on the socket functions in the wire's namespace):
`fsockopen`/`fwrite`/`fread`/`stream_set_timeout` shadowed,
passthrough by default, armed per test — the reply chunks scripted
one per read (a chunk longer than the requested length is split the
way the engine splits it, so a wrong-length read is observable, and
a reply delivered in pieces reassembles through the buffer the way a
real socket delivers it), the commands recorded as the wire's own
observable, every refusal arm armed. The command-stream assertions
are the adapter's real-world contract: the record-before-member
orderings, the claim's `NX PX` spelling with the visibility timeout
in milliseconds, the `MGET` that never fires when nothing was won.
The real-server tier (`RedisMockServer` — a plain PHP process over
an ephemeral loopback port, the `OtlpMockServer` spawn discipline):
an event-driven RESP server speaking the grammar from the SERVER's
own side, implemented independently of the client's encoder and
decoder on purpose — an oracle, not a mirror, so a grammar mistake
on either side meets its contradiction instead of its echo — with
real string and sorted-set state, the lazy PX expiry against the
wall clock, and the (score, member) order the real index answers.
The cross-process stories live only here: the two stores on two
connections respecting each other's claims, the expired claim
handing the entry to the next drainer, the zombie a crashed settle
left, the operator reading the dead record the store wrote.

The mutation round demanded its own observation tests, and every
one of them is DU13's doctrine replayed: a wrap that names only the
entry lets the carried REASON go unobserved (the decode refusal now
asserts the whole chained message, the code, and the previous link
-- three mutants that lived under a starts-with assertion); a bulk
reassembled from TWO pieces never observes the recursion that a
THIRD piece demands (the re-entrant read is the only spelling that
survives a chunk still short of the measured length); and a reply
of a type the grammar never answers is the only observation that
names the default arm of the decoded shape's own spelling (a bool
through the status guard). The one timeout mutant -- the EOF
refusal's throw removed, the reader spinning on empty chunks -- dies
the way the doctrine spells: the spin itself is the kill.

## KDD-DR8 — Named follow-ups (the honest scope ledger)

- **The RabbitMQ and Kafka answers** — the bus transports after
  Redis, each over the same port with its own claim semantics; the
  ROADMAP's cross-process phase names them beside this slice's
  answer.
- **The PDO answer** — the database family's own store over the
  same port, with the transactional/outbox lane it feeds (DU12's
  fourth bullet, the EntityManager port).
- **A reconnect policy stays deliberately the host's** — one
  connection, one lifetime: a stream that dies is a refusal every
  later verb answers, and the deployment's own glue decides what a
  dead wire means (a fresh store, a fresh process, a circuit
  breaker). The wire never silently reconnects mid-protocol.
- **The `queue/` storage lane + the module `events` knob** — the
  wired default and its configuration reader, landing with the
  module system's own slice (DU10's census-moment doctrine, carried
  verbatim from the file adapter's ledger).
- **Durable quarantine and durable traceability** — DU12's third
  bullet, unchanged by this slice: the process-local books still
  re-spell over a store, whichever store the deployment answers
  with.
