# Router CLI Inspector

**Scope:** the Router roadmap's Developer Experience slice —
`ROADMAP.md` §4.1 ("Route List Command … `bin/zef route:list`",
"JSON Output … integrasi dengan tools lain", "Route Test Command …
simulasi pencocokan URL dari CLI", "Visual Tree Dump … teks/dot
graph untuk debugging kompleks") — the lane audit carried as "CLI
tooling: absent from the audited Router surface" after the
observability slice (#555). Decision record for
`src/Zef/Framework/Config/Routing/RouteTreeStructure.php`, the
inspection seams on `RadixTree`/`RouteTableCompiler`/
`RouteTreeCache`/`RouteTreeCacheHydration`, the command family under
`tests/Support/Tooling/` (`RouteInspectionBoot`, `RouteListLine`,
`RouteListText`, `RouteListJson`, `RouteListJsonRecord`,
`RouteMatchAnswer`, `RouteMatchText`, `RouteMatchJson`,
`RouteTreeText`, `RouteInspectionJson`), and the `bin/zef`
`route:list` / `route:test` / `route:tree` dispatch.

## The posture

The lane's remaining order after #555 was *CLI tooling → ecosystem
export*; this slice is the CLI tooling step. The engine already
answered everything the commands render — the compiled entries
(`RouteTableCompiler`'s own fold), the match pipeline's staged
explanation (the observability lane's `RouteMatchExplanation`), the
graph itself (the builder's own node structure). The commands' job
was to **read those seams** without growing an engine of their own:
the renderers never re-derive what the engine already owns, and the
boot is the runtime's own boot — never a parallel parse.

Three commands, one family:

- **`route:list`** — every route the table declares, in the table's
  own declaration order: the name, the canonical method set, the
  pattern, the handler, and every optional declaration in its own
  tagged tail (`--json` for the tool-integration record schema).
- **`route:test`** — the match simulation: one method and path
  (with the host / accept / version flags the negotiation lanes
  own), answered with the found route and its captured parameters
  or the staged explanation of the refusal (`--json` for the
  outcome schema).
- **`route:tree`** — the compiled graph itself, indented by depth:
  the radix compression made visible, one node per line.

## KDD-CI1 — the inspection seams belong to the tree, not the renderers

`RadixTree` answers two new questions about its own compiled state.
`routes()` answers the compiled entries **in the table's own
declaration order** — the order the host spelled them, deliberately
not the tree's insertion order (a zebra-before-alpha table answers
zebra first), because a route list that silently re-orders what the
host declared is a list the host cannot trust against their own
source. `structure()` answers the node graph as **flat depth-first
rows** — one row per node carrying the depth, the edge kind, the
node's prefix and the patterns settled on it, static children in the
builder's insertion order and the parameter edge last, the same
order `RouteTreeCacheHydration` replays. The flat list over a nested
shape is the recorded choice: a flat list spells the walk as data,
carries the depth the indentation needs, and stays a plain list all
the way down — `RouteTreeStructure` is the internal fold behind the
seam. The shape is a **description, never a cache**: nothing
rehydrates from it.

## KDD-CI2 — the cache round-trip answers the identical list for free

The compiled tree cache already carries the entries table-side —
`RouteTreeCache`'s artifact indexes the compiled routes exactly as
its carried table orders them, and the hydration replays the
registry over the re-validated entries. The inspection seams ride
that existing carriage: the dump path and the hydration path both
hand `RadixTree` its compiled list, so a boot from the cache renders
exactly what a boot from the sources would — pinned by the
round-trip test — with **zero new cache surface** (no new artifact
fields, no version bump, no migration).

## KDD-CI3 — the commands live in the tooling lane, and the renderers never re-derive

The command family lives under `tests/Support/Tooling/`
(`Zef\Test\Support\Tooling`) — the dev-code boundary rule 1 settled
when the tooling lane moved out of `src/`: the inspection commands
are test-support surface, never shipped framework runtime, and they
join the container inventory command's own lineage (the same
deptrac lane widening: `Tooling → Router` + `Config`, read-side
composition over the same boot). Every renderer reads the engine's
own seams only — `routes()` for the list, `structure()` for the
tree, `explain()` through `RouteMatchAnswer` for the simulation — so
a rendering defect can never diverge from the engine's answer; the
list, the tree and the simulation are the engine's own truth
spelled for the terminal.

## KDD-CI4 — the boot is the runtime's own boot, and the warm-up is the judgment

`RouteInspectionBoot` turns a configuration directory into the
router the commands answer from, through the same
`ApplicationBootstrapper` the runtime rides — the layered, validated,
environment-resolved repository is the one the boot answers, and a
route table defect surfaces as the boot's own typed
`ConfigException` refusal (the `bin/zef` catch answers "The route
boot refused:" — the container lane's own family wording). The
router is composed by hand (`Router::fromConfig()` over the frozen
configuration) because the facade is deliberately not a container
service yet — the observability lane's own recorded decision
(KDD-OB6), kept consistent here. The boot's warm-up is one call:
`$router->tree()` — the lazy compilation **is** the judgment, and
touching the memoised tree compiles once, at the boot seam, so the
commands never answer from a table the engine has not judged (a
zero-route table is a valid table the renderers spell honestly; the
boot never judges content). The container AOT artifact is the
throwaway the analysis CLIs share (`ContainerAnalysisArtifact`), and
the boot's `finally` never lets it outlive the boot — including the
AOT-dumping boot, pinned by test.

## KDD-CI5 — the CLI's argument grammar is fail-closed and exit-coded

The three commands share one guarded grammar: the answer rides the
standard output and the refusals the standard error; the exit codes
spell the verdict — **0** the found answer, **1** the refused boot
or the refusing match outcome, **2** the usage or path defect. The
method argument must be a token (`!#$%&'*+.^_`|~` and the
token-safe ASCII the request-line grammar owns), the path must start
with a slash, carry no control bytes, and stay under the 2048-byte
request-target budget; the `--host=` / `--accept=` / `--version=`
flags carry their own bounds (253 / 256 / 256 bytes, no control
bytes, no empty values — an empty flag value is a usage defect, not
a silent default). `--json` is owned by `route:list` and
`route:test` only; `route:tree` refuses the flag it does not own
(the tree is a terminal rendering, and a JSON graph view is a
different, future decision). An unknown flag is a usage defect —
never silently ignored.

## KDD-CI6 — the renderers' honest empties and the stable JSON contracts

A zero-route table answers its honest forms, never an error:
`Route list -- 0 routes`, the bare root's tree, and the match
simulation's staged refusal — the same honesty the engine's own
zero-table answers carry. The JSON forms are the tool-integration
**contracts**: `route:list --json` answers the stable nine-field
record schema (the absent optionals spelled as their own empty
answers — `null` hosts, empty `produces`/`middleware` lists — and
the deprecation instants rendered as RFC 3339 UTC) closed by the
table's own size, and `route:test --json` answers the outcome schema
across every outcome the pipeline can refuse (the route and its
captured parameters for a found match; the staged explanation — the
failing stage, every survivor count, the shared prefix, the
neighborhood, the allowed methods, the producible types — for a
refusal). Both ride `RouteInspectionJson`'s one encoding spelling:
pretty-printed for the operator's terminal, forward slashes
unescaped (route patterns are full of them), throw-on-error so an
encoding failure surfaces as the CLI's own refusal.

## The mutation adversary's rounds

The scoped lane ran twice. The first round answered nine escapes in
kind, no ignores:

- **The boot's `finally`** (`UnwrapFinally`) — the happy-path boot
  never writes the AOT artifact (the fixture carries no `aot`
  config), so the cleanup was unobservable; the AOT-dumping boot
  test (a config that *does* dump, the artifact removed afterwards)
  makes the cleanup load-bearing.
- **Three unpinned JSON fields** (`hostSurvivors`,
  `versionSurvivors`, `sharedPrefix`) — the schema test pinned
  `failedStage`, `pathSurvivors`, `allowedMethods` and the
  producible types but never the other three; the full explanation
  schema is now pinned across every outcome, including the path
  miss's shared prefix and its neighborhood.
- **Four dead-branch concat mutants** in the tree rendering — the
  parameter edge's prefix is **always empty by construction** (the
  builder creates parameter nodes with the empty prefix and never
  splits them; the trailing text after a parameter settles as the
  edge's own child row), so the `' ' . prefix` branch was
  unreachable machinery; the label fold renders the row's own run
  (the empty spellings answer their markers — `(root)` and
  `{parameter}`) and the dead branch is gone by construction.
- **One swap-equivalent concat** — `$indent . self::INDENT` is
  order-independent when both operands are whitespace runs; the
  route line's indent is now one `str_repeat` at its own depth and
  the concat of two whitespace runs no longer exists to mutate.

The settled answer: **294 mutants killed at scoped MSI 100/100,
zero escapes, zero ignores.**

## The architecture gate's settle

The first CI round answered one finding: the class-difficulty
ceiling — the tree's own class crossed it (Halstead difficulty
20.59 against the ceiling of 20), the inspection seams being the
straw that broke an already-laden engine facade. The house answer
is the fold split (#540's own settle): the match orchestration
`RadixTree::match()` carried inline — the walk, the host bucket,
the version narrowing, the empty-set early return, the method
judgment — moved into **`RouteMatchPipeline`**, a single-question
fold that owns the sequence between the stages (the counting twin
of the same sequence being `RouteMatchExplainer`, the development
surface). The tree keeps the seam; the fold owns the pipeline; the
tree's difficulty fell to 14.00 and the new fold answers at 10.18,
both well under the ceiling. The fold is `@internal`, covered by
the engine's own match suite (attributed in `RadixTreeTest`), and
the hot path's one added call hop is inside the benchmark's noise
(match 7.3–7.5μs, before and after).

One operational lesson worth the record: the mutation lane runs the
boot under mutants, and a mutant that breaks the artifact cleanup
leaks `zef-container-inventory-*.php` files into the system
temporary directory — the container lane's own recorded hazard. The
scoped run leaves the temporary directory as it found it only when
the suite's cleanup tests run last; a full `phpunit` pass immediately
after the mutation lane is the honest order, and a stray artifact is
removed before the next gate.
