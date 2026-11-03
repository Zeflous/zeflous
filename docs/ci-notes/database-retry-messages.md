# Database retry messages — the lost-connection lane over the code taxonomy

Slice: `feat(database): the lost-connection message lane` — the reference
audit's ONE adoptable item (`Audit-Referensi-Database-Zeflous`, 3 November
2026, the owner's internet references verified against their own sources):
Laravel's `DetectsLostConnections` posture (11.x), adapted as the COMPLEMENT
of the retry taxonomy's code lane, never its replacement. The audit's
verdict on the three patterns the references proposed: the idempotent unit
of work and the transient error taxonomy already live here with contracts
stricter than the references describe, and the one gap both the audit and
the field-proven reference posture agree on is the driver failure that
arrives with nothing but its message — the refused socket, the failed
handshake, the code-0 wrapper no strict comparison can read.

## The slice in one paragraph

`RetryMessages` the containment fold (`matches(message, patterns): bool`,
case-insensitive `mb_stripos` containment); `RetryTaxonomy::matches()`
widened to probe it ONLY after the code lane answers no retryable state
(the class-name gate still upstream, the empty-SQLSTATE-list posture
unchanged — a class-only policy still matches everything of its class);
`UnitOfWorkRetryPolicy` gaining the `retryableMessagePatterns` knob with
`DEFAULT_RETRYABLE_MESSAGE_PATTERNS` — fifteen curated spellings from the
mysqlnd and libpq families (the drivers the default code list already
speaks for), the configuration-defect arms deliberately excluded — and the
corridor guard an empty or non-string pattern refuses at construction (an
empty pattern would contain-match every message and silently retry the
world). `RetryCandidates::carries()` the settle's new membership verb. The
psalm ledger: untouched — the fold has its src consumer at birth
(`RetryTaxonomy` itself), no consumer-arrives entry this time.

## KDD-RM1: the why — the codeless lost connection, the audit's gap

The code taxonomy answers every failure whose SQLSTATE or driver code the
driver fills informatively: `40001`/`40P01`/`55P03` on Postgres,
`1213`/`1205` (and the MySQL integer-code normalisation) on MySQL. The gap
the audit recorded against the reference posture: a lost connection
regularly arrives with neither — the `2002` refused socket rides no default
SQLSTATE, the failed handshake answers code `0`, and the strict
comparisons read nothing. Before this slice such a failure escaped
`flushRetrying()` as non-retryable: the surrounding rollback answered a
problem the field has proven resolves itself (Laravel ships the
message-matching lane precisely because these spellings arrive in
production). The lane's contract is the complement, never the replacement:
the class-name gate (`instanceof PDOException` by default) still applies
UPSTREAM, so a userland throwable whose message happens to spell a pattern
is not the lane's business, and a code the taxonomy lists still wins before
any pattern is probed.

## KDD-RM2: the curated default — fifteen spellings, and the exclusions that name the curation

The default list is grouped by driver family, every entry traceable to the
reference list AND to a spelling the family answers: the mysqlnd family
(`server has gone away`, `lost connection`, `error while sending`,
`broken pipe`), the TCP/socket family — driver code 2002's transient arms
(`connection refused`, `connection timed out`, `network is unreachable`,
`temporary failure in name resolution`, `reset by peer`), the libpq family
(`server closed the connection unexpectedly`, `no connection to the
server`, `ssl connection has been closed unexpectedly`,
`ssl syscall error`, `decryption failed or bad record mac`), and the
generic spelling both drivers answer (`connection lost`). The deliberate
EXCLUSIONS, each pinned by its own test: `no such file or directory` (the
2002 family's unix-socket arm — a missing socket is a configuration
defect a retry cannot heal, finer-grained honesty than the code list could
express, which is exactly why 2002 joins no default SQLSTATE either) and
`name or service not known` (DNS NXDOMAIN — permanent, not transient). The
non-PDO drivers' spellings (`TCP Provider`, `ORA-`, `Adaptive Server`,
`communication link failure`) stay out of the default and arrive through
the knob — the same per-driver posture the default code list takes. And
the concurrency strings (`deadlock found when trying to get lock`,
`database is locked` — the reference's OTHER trait,
`DetectsConcurrencyErrors`) stay out entirely: the code taxonomy already
owns that lane.

## KDD-RM3: the containment — case-insensitive, Unicode-folded, one entry where the reference carries two

A driver's case spelling is not a contract: the reference list carries
`server has gone away` AND `Server has gone away` as two entries for
exactly this reason — the case-insensitive spelling carries them as one.
The `mb_stripos` spelling folds per Unicode, not per the ASCII byte map
`stripos` walks: the Kelvin sign (U+212A, the case-fold spelling of `k`)
and the long s (U+017F, of `s`) are case spellings ASCII-only folding
cannot see — the mutation round's MBString mutant dies to both (KDD-RM7),
pinning the mb_ spelling load-bearing rather than decorative. Driver
messages are ASCII in practice; the lane's containment is honest breadth,
and the breadth is tested as a contract, not assumed.

## KDD-RM4: the policy surface — the knob, the constant, the corridor guard

`retryableMessagePatterns` rides the policy's constructor as the third
list knob beside the class names and the SQLSTATE codes: a host narrows
it, extends it for a driver the default does not speak for, or empties it
to restore the pre-lane behaviour exactly (the code lane alone). The
`DEFAULT_RETRYABLE_MESSAGE_PATTERNS` constant duplicates the constructor
default inline for the same parser-edge-case reason
`DEFAULT_RETRYABLE_SQL_STATES` documents (Doctum's nikic/php-parser).
The corridor guard is new posture the other list knobs never needed:
every pattern must be a non-empty string — an empty pattern would
contain-match EVERY message and silently retry the world, and a
non-string entry would surface as a TypeError at flush time, far from the
policy's construction. The corridor refuses both where the policy is
spelled; the guard itself judged over `array<int, mixed>` (the raw
entries, judged before the `list<string>` contract is trusted — the
`RetryCandidates` slot-guard precedent).

## KDD-RM5: the interplay with the code lane — and the lazy-handle truth

The verdict's composition, read in order: class-name gate; empty
SQLSTATE list = the class-only posture (the message lane is moot —
everything of the class already matches); SQLSTATE membership first; the
message lane second, only when the code lane answered nothing. A Postgres
connection death (`server closed the connection unexpectedly`, SQLSTATE
`08006`) is answered by its message whether or not the driver filled the
code slot — `08006` deliberately joins no default code list, the lane
covers both spellings of the death because the message text is stable
across them. And the lazy-handle truth the lane's retry rides: a
first-connect failure (the handshake refused at the flush that finally
asked for the handle — `PdoHandle`'s `??=` never assigned) genuinely
re-connects on the replayed attempt, while a mid-flight death retries
against the same cached handle and exhausts honestly — bounded attempts,
the last throwable propagating, the queue preserved for the caller's own
replay. The lane cannot heal a dead handle; it can tell the truth about
which failures deserve another attempt.

## KDD-RM6: the settle — the ceiling forced the membership judgement home

The first spelling after the lane measured `RetryTaxonomy` at 21.88 over
the 20 ceiling (Halstead: the widened verb's operator variety). The
settle moved the SQLSTATE membership judgement to its own domain fold —
`RetryCandidates::carries(throwable, states): bool`, the fold's own list
walked against the fold's own candidates — leaving the taxonomy the
class-name gate and the verdict's composition: 18.00 + 16.50, every
method under the ceiling both times, only the class totals moved (the
family's standing difficulty lesson). The settle's side effect the
mutation round caught: with no cross-class caller left, the fold's
`sqlStates` had no direct caller either — the PublicVisibility mutant
rode that until the direct surface walk arrived (KDD-RM7).

## KDD-RM7: the mutation round — 83 mutants, two escapes, two kills

Scoped over the slice's four files: 83 mutants, 81 killed in the first
round, both escapes answered with KILLS, never ignores —
`RetryCandidates::sqlStates`' visibility (killed by the candidate fold's
own direct surface walk: the normalised shape, the membership judgement)
and the MBString containment (killed by the Unicode folds — KDD-RM3's
Kelvin sign and long s). Final round: 83/83 killed, MSI 100/100, no
documented equivalents added — the lane's first mutation round joins the
ledger clean.

## KDD-RM8: the reference-audit decision record — so the question does not recur

The audit's verdicts, recorded here because the references will be read
again: **the idempotent unit of work** — already lived here before the
reference asked (the queue cleared before execution, the flushing flag
disarmed in `finally`, the replay's preserved snapshot; stricter than the
Doctrine the reference cited, whose `finally` closes the manager and whose
identity map survives the flush). **The transient error taxonomy** —
already lived here in full (the class-name gate, the SQLSTATE membership,
the MySQL integer-code normalisation, the backoff with jitter the
reference's own example lacks); the message lane this slice adds is the
one gap, now closed. **The clone-per-verb blueprinting (`return clone
$this;`)** — REJECTED with documented basis: all three libraries the
reference attributed it to (DBAL, Cycle, Aura) build MUTABLE builders
(98× `return $this`, 0× `clone` in DBAL's QueryBuilder), the
query-builder-to-snapshot discipline here already answers the
cross-request pollution the pattern guards against (the facade is
constructed per query, frozen into the readonly `QuerySnapshot`, compiled
to the immutable `SqlQuery`), and clone-per-verb would ADD an allocation
on every link of every loop — against the worker-memory goal the
reference itself states. The audit's four factual corrections, for the
record: DBAL's state tracking is value objects at the part level with a
mutable builder; `spiral/database` is the ARCHIVED predecessor of
`cycle/database`, not a component of it (one team, the RoadRunner
authors — the relevance claim stands); Laravel's detection matches
message STRINGS, not PDO codes; and Doctrine's `finally` is not the
clear-in-finally the reference describes.

## Verification

The full local battery over the branch: `ci:static` core green (6820
tests / 16500 assertions; smoke 2760B budget 20000; concurrency 23;
roadmap gate; audit 0; duplication and mess clean), PHPStan max 0 errors,
Psalm EXIT-0 over an untouched ledger (the fold consumed by
`RetryTaxonomy` at birth — no new baseline entry), deptrac 0 violations
(no layer widened — the lane lives inside the Database family), archeology
0 error-level findings after the settle (`RetryMessages` 3.38,
`RetryTaxonomy` 18.00, `RetryCandidates` 16.50), coverage 98.56% against
the 90% floor with every slice fold at 100% lines, and the scoped
mutation round 83 mutants MSI 100/100 with zero escapes.
