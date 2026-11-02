# Database wiring — the knob block, the registration, the per-purpose connections, and the audit emission

Slice: `feat(database): the wiring knob block` — the recorded follow-up of
KDD-DO8 (`docs/ci-notes/database-orchestration.md`), the census moment the
database family's rule-17 integration was always waiting for: the
configuration knob block, the `FrameworkDefaults` registration set, the
per-purpose connections, and the unbounded-statement audit emission, landing
together with the family's first live reader.

## The slice in one paragraph

The `database` config family ships as three knobs under the family's own key
(`default` the default connection's name, `connections` the named connection
maps, `migrations` the migration runner's per-purpose name) read through the
root-namespace census folds the events family planted (`DatabaseKnobs` the
family's own census and map normalisation, `DatabaseConnections` the
map-of-maps grammar and the one-name lookup, `DatabaseConnectionKnobs` one
connection's own word-key narrowing, `DatabaseDeclaration` the value object
the wiring composes over), and `DatabaseWiring` is the composition fold the
framework profile's own registrations name: `database.connection` (the
`PdoConnection` over `ConnectionConfig::fromArray()` with the audit emission's
two optional seams — the shared `observability.meter` counter the
registration hands in, and the WARN record over the deployment's ONE
structured log address), `database.transactions` (the managed-scope manager
over the shared connection identity), `database.unit_of_work` (the
per-ask queue, transient), `database.migrator` (the runner over the
per-purpose connection: the `database.migrations` name the host spelled, else
the default), and the `repository()` fold answering `TableGateway` -- the
family's generic per-table gateway, the constructible spelling of the abstract
`Repository` base. The reference never wired this family through any
configuration system (its consumers composed by hand); the knob grammar and
the registration set are this tree's own, spelled inside the doctrines the
events, session and slow-request families already established.

## KDD-DW1: the lazy singleton — the posture a connection demands

The events family's wired default planted the never-at-the-boot doctrine
with a TRANSIENT registration: the singleton warmer eagerly resolves shared
lifetimes at boot, so a silent knob whose resolution refuses would redden
every boot of every host that never declared the family. The database
connection carries the same silent knob but cannot borrow the same lifetime:
a transient answers a FRESH instance per resolution, and a connection is the
one service in the whole catalogue whose semantics forbid that -- two
`PdoConnection` instances over one declaration are two PDO handles, two
sockets, two transaction boundaries that no host could ever join. The
container's lazy lane answers the exact posture the connection demands:
`'lazy' => true` on a singleton registration defers construction behind a
native proxy, the boot's warmer walk answers the proxy without running the
factory (verified empirically before a line of wiring was written: a probe
container whose lazy factory throws boots green and throws only at the first
member access), and the one materialised instance answers every later ask --
"a singleton materialises once, through the one proxy it keeps". The silent
refusal therefore fires at the host's own first USE of the connection, with
the knob grammar spelled in the message, and the declared connection answers
as one shared instance whose queries, migrator, transaction manager and audit
records all ride the same socket. The migrator registration carries the same
lazy posture for the same reason (its factory reads the per-purpose knob);
the transaction manager composes over the connection PROXY eagerly (its
factory reads no config, so the warm constructs a cheap wrapper over a proxy
and never refuses); the unit-of-work queue is the per-ask artifact its
transient lifetime spells. `ContainerInventoryReport`'s lazy-marks count pin
moved 1 -> 3 with the two new marks, and the inventory test now spells the
`database.connection -> PdoConnection` line with the doctrine in its failure
message.

## KDD-DW2: the per-purpose connection — the purpose stands alone

`database.migrations` is the first per-purpose connection knob, and its
one-way fallback is the whole design: the purpose's own name when the host
spelled one, else the default connection's name -- and the purpose NEVER
requires the default to be declared. A deployment may run its migrations on
a privileged connection of its own naming (`migrations => 'ops'` over
`connections.ops`) without ever spelling `database.default`, because the
runner and the runtime connection are two different deployments' decisions
that a grammar should never couple. The refusal posture follows the same
line: neither a purpose nor a default spelled is the migrator's own
named halt (its message spells BOTH ways out, the purpose knob and the
default knob), and a purpose naming an undeclared connection halts with the
asking knob attributed correctly -- the `migrations` word when the purpose
spelled it, the `default` word when the fallback did. The purpose vocabulary
is closed by construction: each future purpose the roadmap names (each its
own recorded slice) joins the family as its own knob with its own census
entry, exactly the family-additive doctrine the knob census carries.

## KDD-DW3: the audit emission — the reference's seams, the tree's sinks

The reference's `PdoConnection` carried the unbounded-statement audit
emission over two optional constructor seams (a `MeterInterface` counter, a
`LogExporterInterface` WARN record); the tree's adapter deliberately deferred
both to this slice (the deptrac Database layer's own comment named the
follow-up), and this slice ports the seams with the tree's own sink
doctrines. The counter is the metrics lane's own contract
(`Telemetry\MeterInterface`, the shared `observability.meter` engine the
registration hands in as a service argument -- the `http.errors` precedent's
spelling), counting the `zef.db.unbounded_statement` series with the
operation word as its dimension. The record is a PSR-3 WARN entry over the
deployment's ONE structured log address (`ErrorStorageWiring::logAddress`,
the slow-request family's own derivation -- the explicit
`http.error_handling.log_path` or the storage layout's `logs` lane), so the
audit records and the error and slow-request records land in the one sink
and stay greppable together: two knobs for one sink is a fragmentation an
operator must then reconcile, and the error lane already named the address.
The `PdoUnboundedAudit` fold owns the emission (the operation word folded
from the statement's own head, the full SQL travelling in the record's
context), both seams independently null (the honest void: no observability,
no cost), and `SqlQuery::$unbounded` -- whose `PossiblyUnusedProperty`
ledger entry has travelled since the core slice -- finally has its live
reader. deptrac widens `Adapters += Telemetry` for the meter contract, the
essay beside the widening spelling the one-way direction (the adapter -- the
only place the affected-row count the audit records is visible -- reaches
the contract; the engine never learns a statement audited).

## KDD-DW4: TableGateway — the constructible spelling of the abstract base

`Repository` is abstract because every real gateway is the host's own: their
table, their finders, their composition. The wiring still needed a
constructible answer for the psalm census (the five "consumer-arrives"
entries the orchestration slice recorded were all leaving THIS slice, and an
abstract class no wiring constructs keeps its entry), and the reference's
own demonstration of the base's out-of-the-box value was the anonymous
extension (`new class($conn, 'users') extends Repository {}`) -- a spelling
this tree bans in source (the anonymous-class-with-arguments lesson the
style trio taught). `TableGateway` is that demonstration's named spelling:
a final concrete class extending the base, no code of its own, the base's
constructor doing what it already did (the eager table-name grammar). The
host's own services declare per-table gateways over the wiring fold
(`DatabaseWiring::repository($connection, 'users')`) or the class shape
directly -- their choice, both grammars documented -- and a host who later
grows finders still extends the base directly; the generic never stands in
the way.

## KDD-DW5: the psalm ledger dance — eight leave, twenty-two arrive

The census moment's psalm story, recorded in full because the ledger's shape
IS the slice's honesty. EIGHT entries left the baseline: the five
`UnusedClass` entries the orchestration slice recorded (`PdoConnection`,
`Migrator`, `Repository`, `TransactionManager`, `UnitOfWork` -- DO8's
verbatim promise), plus three the wiring's live readers reached
(`SqlQuery::$unbounded` the audit reads, `ConnectionConfig::fromArray` the
wiring composes through, and the vendored PSR shim's `LoggerInterface::warning`
the audit record calls). TWENTY-TWO arrived: the verb surfaces the wiring's
construction REVEALED -- a class that was wholly unused collapses to one
`UnusedClass` entry, and the moment a reference exists its uncalled methods
surface individually (the cold-cache fix `3f1587f` had already shown the
collapse's mechanics in reverse) -- Migrator's runner verbs, Repository's
gateway verbs, UnitOfWork's queue verbs, none of them called from inside the
framework because their live callers are the HOST's code, plus three
`MixedAssignment` entries in the connection-map walk (the exact
`ModuleConfigSchema` posture for config-map values: the mixed the frozen
repository carries flows into a typed accumulator, and the tree has always
carried that finding in the baseline rather than lying about the value's
type). The debt did not grow: it SHRANK, from whole classes the tree could
not reach to individual verbs awaiting their host's call -- the same
"consumer-arrives" wave every opt-in family's interface verbs already ride,
one level shallower.

## KDD-DW6: the settle and the mutation round

Two archeology settle rounds over the ceiling (the #665 curriculum replayed
once more): the declaration fold first shed its map normalisation to the
census (`DatabaseKnobs::familyMap()`, 22.00 -> 19.00), then the connections
fold shed the per-connection key narrowing to its own fold
(`DatabaseConnectionKnobs`, 23.83 -> 19.07 + 12.38) -- the
difficulty-is-aggregate-volume lesson holding: every method stayed under the
ceiling both times; only the class totals moved. The mutation round answered
two escapes to zero over 325 killed: the census `continue` -> `break` escape
died to its kill test (an unknown knob BEHIND a known one still halts -- the
walk must visit every child), and the audit fold's `mb_strtoupper` MBString
mutant joined the ledger as the tree's thirteenth documented equivalent (the
comparison target 'UPDATE' is pure ASCII and no non-ASCII character
upper-cases into any of its letters, so a byte-map-divergent head misses the
prefix under both spellings and audits as 'delete' either way -- verified by
the complete suite passing under the escaped mutation, the essay beside the
entry). Scoped infection over the slice's nine files: MSI 100/100, 0
escapes, 0 timeouts. Coverage: the new folds at 100% lines, the tree at
98.45% against the 90% floor.

## KDD-DW7: what this slice deliberately does not carry

The named follow-ups, each its own slice: the **condition-object API** the
where-family's exotic forms return with (still the core lane's question);
the reference's v2.30.0 `PdoJobQueue` family (no Job lane exists in this
tree); a **CLI migration runner** (`bin/zef migrations:run`-shaped) -- the
wiring answers the runner as a service identity, and the command that walks
it is the operator lane's own future slice, landing with its own KDD (it is
also the first live CALLER of the Migrator verbs the ledger now carries);
**per-purpose knobs beyond migrations** (each future purpose its own knob,
its own census entry, its own slice); and the **transaction manager's
slow-hook threshold** -- deliberately no knob of its own, because a
threshold no deployment asked for is a log stream no operator reads; the
guard's seam stays available for a host's own composition, and the day a
deployment asks, the knob arrives with its census entry the way every opt-in
family's did.

## KDD-DW8: the CI round -- two skipped gates, one attribution miss

The first push reddened CI Static twice, and both roots were gates the local
round had not actually run -- the exact lesson KDD-DB9 recorded for the core
slice, replayed: a green local battery is only the battery you RAN. PHPStan
at max level found 23 errors in the wiring pins (mixed reads off
`Container::get()` and `json_decode`, three redundant type asserts on the
wiring folds' own return types -- `alreadyNarrowedType`, the honest fix
being behavioural asserts, never deletions), and deptrac found the one
widening the local round had missed entirely: `FrameworkDefaults` -- the
Kernel layer's own collected class -- names `UnitOfWork` in its
`database.unit_of_work` registration, so `Kernel += Database` rides beside
the adapter's `Adapters += Telemetry` (the registration-to-engine join the
events family's wired default spelled first). The third finding was
SonarCloud's new-coverage gate at 91.3% against 95: the settle round's
`DatabaseConnectionKnobs` extraction had left the fold out of every covers
list, so its fourteen lines -- fully exercised at runtime -- were filtered
out of the clover by the suite's `requireCoverageMetadata`, invisible to
exactly the consumers the covers-attribution doctrine names (KDD-DU13,
#589's lesson, the third replay). The fix is one attribute on the
declaration test that already walks every branch. The round also re-learned
the pin dance the queue teaches: a fix commit above the docs commit leaves
the pin naming a commit the walk can no longer see as its tip, so the
refresh rides with every substantive follow-up -- the #674 incident's
mechanics, applied pre-emptively this time.

## Verification

The full local battery: `ci:static` core green (6674 tests / 16165
assertions, smoke 2760B budget 20000, concurrency 23, roadmap gate, audit 0,
duplication and mess clean -- the one local `StreamTest` failure is the
sandbox's file-redirected stdout, green under CI's pipe), PHPStan max clean,
Psalm level 1 EXIT-0 over the regenerated ledger, deptrac 0 violations over
the one widening, archeology 0 error-level findings after the two settle
rounds, scoped infection MSI 100/100 (325 killed, 0 escapes, the one
documented equivalent in the ledger), coverage 98.45% (gate 90%), and the
end-to-end rule-17 path pinned twice: the fold level (declaration ->
wiring -> adapter -> real SQLite socket) and the boot level (the silent
profile boots without running a wiring factory; the declared profile answers
`database.connection` as one shared instance through the container).
