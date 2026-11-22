# The Error Family's Query-Count Feeder — KDD

**Scope:** the roadmap-vs-code audit's R2 first slice (#905's narrowed
phrase): the toolbar's query counting waited on the PDO adapter's feeder
call -- the `ErrorRequestContext::recordQueryFor()` hook and the toolbar
display were live, the vendored adapter alone never called. The feeder
now lands: `Adapters\Database\PdoQueryCount` calls the hook once per
executed statement of the vendored PDO connection, and
`DatabaseWiring::connection()` carries the composition's optional
request-provider seam that arms it, branch `feat/error-query-feeder`.

**Verified locally before the PR:** the authoring sandbox carries no PHP
runtime -- the full verdict (phpunit battery, php-cs-fixer, phpstan,
psalm, deptrac, the scoped infection round) rides CI on the merge group,
watched to green, the same honest posture the uploads and temp lanes'
PRs carried (#908, #911).

## The map

| Unit | Question it owns |
|---|---|
| `PdoQueryCount::feed()` | the feeder call itself -- one executed statement, one increment of the active request's counter, contained best-effort |
| `PdoQueryCount::whenCarried()` | the arming fold -- the counter when the composition carries a provider, null (the honest void) when it spells none |
| `PdoQueryRun::execute()` / `::fetchAll()` / `PdoFetchOne::fetch()` | the finally seam the feed shares with the measured window's closing edge -- the throw path feeds too |
| `DatabaseWiring::connection()` | the composition's request-provider seam -- the third optional act beside the shared meter and the audit logger |
| `PdoConnection` | the feeder's position in the connection's own collaborator list -- after the measured bracket, beside it, never inside it |

## KDD-QF1 — the request reaches the adapter through the composition, never a framework holder

The hook's own docblock spelled the doctrine years before this slice:
the context is "shared by reference through PSR-7 request attributes,
never stored on a persistent worker service," and the hook exists so an
adapter increments "without storing mutable request state on a
persistent-worker singleton." The kernel's own docblock pins the same
line one lane over ("built once per worker process ... must stay free of
per-request state"), and the routing layer's handler contract is
deliberately request-free -- the terminal handler receives the captured
parameter map alone. So no framework-tree site can honestly hold "the
active request" for the connection to consult: the honest carrier is the
deployment's own composition root, the one place that owns the runtime's
request window. The provider closure (`null|Closure(): ?ServerRequestInterface`)
is that composition act, spelled exactly where the meter and the audit
logger already are -- `DatabaseWiring::connection()`'s own parameter list.
The framework's own registration spells no provider: the default wiring
keeps the documented host-adapter hook, and a deployment that wants the
toolbar's count armed hands the wiring the provider its runtime already
owns. A provider answering `null` (a console run, a warm-up walk, a
boot-time health probe) feeds nothing -- the no-active-request truth,
never an error.

## KDD-QF2 — the feeder rides the finally seam the measured window owns

The count is an emission, and the family's own doctrine is a page old:
"an emission is never statement work." The feed runs in the SAME finally
seam `PdoQueryLog::end()` runs in -- after the window's closing edge,
shared by the throw path -- so a statement that answered feeds the
request's counter, and a statement that threw feeds it and then
propagates its throw untouched: a query the request paid is a query the
toolbar counts, whichever way the statement ended. A healed statement
(the lost-connection fold's re-materialised socket) answers one window
per attempt, and the count follows the per-attempt truth the measured
family's own windows answer. The feeder is deliberately NOT the
bracket's own field: the count must not ride the `database.query_log`
arming -- a deployment wanting the toolbar's query count does not owe
the query log an arm -- so the feeder is the connection's own optional
collaborator, the null answer the inert posture (no provider held, no
question asked, no cost paid).

## KDD-QF3 — the observation is never worth the query

`feed()` is best-effort the whole error lane already is: a provider that
throws, or a request surface whose attribute read refuses, can neither
replace the statement's own answer nor mask the exception a failing
statement already threw -- the catch is the containment, the
query-log family's own closing-edge doctrine carried one seam over. The
count answers no metric of its own (the request's context alone holds
it), so a contained feed costs nothing the meters would miss.

## KDD-QF4 — the migrator takes no provider

`DatabaseWiring::migrator()` deliberately does not carry the seam: the
migration runner is the console lane's own surface (`bin/zef
migrations:run`), and a console run owns no request window -- a provider
there would be a seam no honest caller could ever arm, the dead-knob
question the family's own census doctrine refuses. The per-purpose
connection the migrator composes rides the same private fold with the
provider's default null; the CLI's statements feed nothing, which is the
truth of where they ran.
