# KDD — Module CLI Registry (the CLI command extension point)

The decision record for `feat(modules): the CLI command extension point
over the standard CLI schema` — the Fase 2 extension surface's third
fold (`ROADMAP.md`, "Module Integration System", §2.4): a module
declares its commands, the kernel joins them under the one
`console.commands` key, `bin/zef list` renders the joined table and the
dispatch resolves the one handler the operator's verb declared.

## KDD-CR1: the declaration rides the manifest's own `cli` path, the Standard CLI Schema judges the file

The manifest's optional `cli` field is a path resolved against the
manifest's own directory — the exact rule the `config_schema`, `routes`
and `events` paths ride (`ModuleManifestPaths`, the fold the manifest's
own grammar delegates to). The file it points at is a list of
declarations `{name, description, service}`, judged by the
configuration layer's own `CliSchema` — a thin rules map over the
generic `ConfigSchema` engine exactly as `EventSchema` and `RouteSchema`
are, so a module CLI file validates through the same engine the host's
own `console.php` rides. The schema validates shape; the name's grammar
(`CliCommandName`, the `route:list` convention), the fields' display
bounds (`ConsoleCommandEntry`) and the two conflict judgements
(`ConsoleTableEntries`) are the console lane's own folds.

## KDD-CR2: one `console.commands` key — the host's table and every module's file ride the same source of truth

`ModuleCliTable` joins every declaring module's declarations under the
repository's `console.commands` key, appended after the host's own
entries, at the same `ApplicationBootstrapper` hinge the routes and
events folds ride — so a configuration-cached boot and a freshly loaded
one answer the same merged table, and `ConfigBootstrapper` asserts the
key at the boot (the cache-artifact guard the route and listen tables
already carry: an invalid table never reaches the artifact). The
registry's reserved registration (`ConsoleWiring::COMMANDS_SERVICE`,
`console.commands` in the container) is installed ahead of the
definitions and answered at the host's first resolution — the
never-at-the-boot discipline, the reserved-services precedence, a
host's same-identifier rebind winning.

## KDD-CR3: the framework-verb collision is refused, and the vocabulary is live in three places

A declaration whose verb collides with one of the framework's own
(`route:list`, `errors:report`, ...) is refused naming the verb: the
CLI's builtin branches match first, so a shadowed declaration would be
unreachable code wearing a registry entry. `FrameworkVerbs` is the one
vocabulary three consumers read — `bin/zef list` renders it, the
registry validates against it, the dispatch branch routes around it (a
framework verb never dispatches through the registry; the positional-
path builtins among them must not be intercepted) — and the
correspondence test pins every vocabulary verb to a literal `bin/zef`
actually answers, so removal from the CLI fails the pin. The
maintenance contract runs the other way: a builtin ADDED to `bin/zef`
without the vocabulary grows a silent shadow surface the next
declaration collides with — the pin cannot see it, so the verb's
addition to `FrameworkVerbs` is the maintainer's own recorded step.

## KDD-CR4: `list` answers from the DECLARED table, dispatch resolves the ONE handler — the CLI's own laziness posture

The events wiring composes its dispatcher eagerly at the first
resolution because a request-scoped process resolves it once and
requests repeat; a CLI process runs one command and exits, so the
registry answers from the declared table (`bin/zef list` renders the
framework verbs and the declared commands without instantiating a
single service) and the handler resolution happens for the one command
the operator asked for: a service that is not registered, or does not
answer the `CommandHandler` contract, aborts the dispatch — a dispatch
that silently skipped a declared command would be the same lie a
dispatcher that silently skipped a listener would be, with an exit code
instead of a delivery count. The duplicate-verb and collision refusals
ride the same seam: they surface at the registry's first resolution,
never at the boot (the wired dispatcher's own already-defined refusal
posture, replayed).

## KDD-CR5: the dispatch grammar is config-directory-first, the house's own convention

`bin/zef <command> <config-directory> [args...]` — the same grammar
`route:list`, `migrations:status` and `container:inventory` carry, not
an environment variable and not a trailing flag: the composition root
needs the host's configuration directory to boot the module roster the
declarations ride. The handler receives the raw arguments after its own
name and the directory, parses what IT declared, and answers its own
exit code (0 the answer, 1 the operational refusal, 2 the usage defect
— the vocabulary documented on the `CommandHandler` contract); the
dispatch passes the code through verbatim. The framework owns the boot
and the resolution; the handler owns everything after.

## KDD-CR6: the unknown verb's answer names the directory command

A verb no builtin answers and no declaration carries answers `Unknown
command '<verb>'. Run 'bin/zef list <config-directory>' ...` on STDERR
with exit 2 — the usage family's own posture, and the operator's one
step from the mistake to the directory that would have named it. A verb
without a config directory at all falls through to the CLI's existing
final usage (the inherited posture every builtin verb carries too);
a config directory that does not exist is the usage refusal naming it.

## KDD-CR7: what the slice deliberately does not carry

The per-module migrations fold (the Fase 2 fold beside this one), the
Fase 3 governance (sandboxing, health aggregation, feature flags, the
audit trail) and the Fase 4 DX (the generator, `module:list`/
`module:doctor`, hot reload) — each its own slice. No command-level
argument grammar declaration: the handler owns its own argv parsing
(`run(list<string>)` is the whole surface), exactly the way the
framework never interprets a route handler's request body. No
`FrameworkVerbs`-driven restructure of `bin/zef`'s own builtin
branches: they predate the registry, and the vocabulary's
correspondence pin keeps the two in step without a rewrite. The
async arms' module declarations are n/a: the CLI extension point has
no async half.

## KDD-CR8: the settle's own folds, and why they exist

The archeology ceiling asked three times and the slice answered the
same way the migrations and retry-messages settles did — the fold
split: `ConsoleCommands` (the registry surface, difficulty 10.00 after
the split) carries its judgement walk in `ConsoleTableEntries` (the
one fold that owns the schema-bounds-duplicate-collision question);
`ModuleManifest` delegated its path resolution to `ModuleManifestPaths`
(the manifest's grammar staying the shape question); and the
`ApplicationBootstrapper`'s boot folded its builder composition out
(class difficulty 19.73, every method under the ceiling) with the
wired families' reserved registrations moving home — `EventWiring::
register()` and `ConsoleWiring::register()` each own their own
service's installation, the composition root just calling them.

## Verification

The full local battery over the branch: `ci:static`'s lanes green
(6917 tests / 16743 assertions — +95 over the event extension point's
merged base; smoke 2760B budget 20000; concurrency 23; roadmap gate;
audit 0; duplication and mess clean), PHPStan max 0 errors, Psalm
EXIT-0 over five new baseline entries (the `CommandHandler::run` and
`ConsoleCommands::named` "consumer-arrives-with-the-wiring" arms whose
live caller is `bin/zef`, and the three CLI tooling classes the same
`bin/zef` consumes — the migrations CLI family's own ledger posture),
deptrac 0 violations (the `Console` layer with its one `Config`
dependency — the Standard CLI Schema judgement — plus the `Kernel` and
`Tooling` widenings the composition and the renderers carry), progpilot
[] over `src/`, the archeology gate PASSED (0 error-level findings
after the three folds of KDD-CR8), and the scoped mutation round 203
mutants MSI 100/100 with zero escapes — the eight first-round escapes
killed by their own named tests: the cacheless boot path the console
assertion also guards, the multibyte name the character bound judges
(a hundred-and-twenty-three-byte sixty-four-character spelling reaching
the GRAMMAR refusal, never the byte-length one), the multibyte
description the character bound constructs, the whole-sentence
assertions the concat mutants cannot reorder their way past, and the
refusal sentences that must carry the wrapped verdict's own text.
End-to-end over `bin/zef` itself: the text and JSON directories, the
host-and-module dispatch with its exit-code passthrough, the unknown
verb's answer, the missing directory's refusal, and the positional-
path builtins riding unintercepted.
