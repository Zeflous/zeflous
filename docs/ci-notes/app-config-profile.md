# CI Note — Application Config Profile + Boot Orchestrator

Lane: Kernel composition (`ApplicationBootstrapper`, `FrameworkDefaults`, the
shipped `app/configs/` profile). PR #332 lane scope: the one-call boot that
carries a directory of configuration files into a frozen, container-installed
kernel -- closing the gap the roadmap's own boot pipeline diagram had left
implicit ("Load Config -> Validate Schema -> Jika ada cache, baca dari cache
-> Config"), and shipping the default configuration profile the configuration
phase never shipped.

The work log of this lane's PRs:

| PR | Scope |
|----|-------|
| #332 | `ApplicationBootstrapper` (one-call boot + `pipeline()` composition), `FrameworkDefaults` (the code-declared defaults profile), the shipped `app/configs/{services,middleware,routes}.php` profile, `AppBootBench`, deptrac Kernel collector widening, psalm baseline shrunk by two (`ContainerBootstrapper`/`PipelineFactory` `UnusedClass` entries -- the orchestrator now consumes both from `src/`) |

## The decisions (KDD-AC)

**KDD-AC1 -- File-equals-key, and the shipped profile is three files.**
The directory adapter's contract is already the convention the application
layout wants: every direct `*.php` child of the configuration directory
becomes one top-level repository key named after the file minus its
extension. The shipped profile therefore declares exactly the keys the
already-merged consumers read: `app/configs/services.php` -> the `services`
definition map (`ContainerBootstrapper`), `app/configs/middleware.php` -> the
`middleware.stack` pipeline (`PipelineFactory`), `app/configs/routes.php` ->
the `routes` table (`RouteSchema` + `RoutingRuntime`). No re-keying of the
#328 contracts was needed -- `middleware.stack` was already namespaced under
its file's key, which is what made the profile drop in. A `container.php`
or `middlewares.php` spelling was rejected: under this convention a file's
name IS its key, so domain-synonym filenames would silently re-namespace the
whole profile.

**KDD-AC2 -- Defaults live in code, not in a deletable file.** The
framework's own defaults are declared by `FrameworkDefaults::profile()` (the
`Zef\Framework` kernel layer, pure data) and laid down as the layered
builder's Default layer under the app files and the environment -- the
vendor-defaults seam of the Laravel-style contract the host asked for: a host
that never writes a configuration file boots on exactly this profile, and a
host that writes one overrides only the keys it cares about. A shipped
`app/configs/` file carrying the defaults was rejected because a host can
delete or half-edit shipped files; code cannot be edited out from under the
framework. `ArrayMerger`'s semantics carry the override contract: maps merge
(the framework's services survive alongside the host's additions), lists are
full declarations (a host's route table replaces, never concatenates).

**KDD-AC3 -- The profile is empty today, on purpose.** Every key the profile
ships must be a key a live consumer reads; no knob is declared before its
subsystem exists. So the framework ships no services, no middleware and no
routes of its own (the honest content of a framework that owns none), and the
growth plan is the roadmap's own order: the HTTP lane's knobs
(`http.body_budget`, `http.trusted_hosts`, `http.cors.allowed_origins`, ...)
migrate into `FrameworkDefaults` with their wiring, the container's `aot`
switch arrives with the compile/dump area (roadmap area 8), `tags` with the
autoconfiguration area (roadmap area 3), and `app/views`, `app/languages`,
`app/modules` ship with the adapters and module lanes that consume them.
Declaring `container.aot: false` today would be a decorative lie: fail-closed
means the config surface only promises what the boot actually honours.

**KDD-AC4 -- The scan declares inventory; the adapter owns the filter.**
The orchestrator's private scan collects every direct child's basename and
hands it to the file loader as the executable allowlist -- the
`ConfigLoader::directory()` contract wants a declared inventory, and a host
application owns its configuration directory the way it owns any other
source it ships, so the directory's own inventory IS the allowlist. The
narrow filter (extension, hidden entries) stays exactly where it already
lives, in the directory adapter -- the orchestrator never second-guesses it.
An earlier draft repeated the adapter's filter inside the scan; infection
killed that draft honestly: the duplicated `||` was observationally
equivalent to `&&` because the adapter filters again downstream (a true
value-equivalence zone), so the duplication was removed rather than
ignored -- one filter, one owner, no immortal seam.

**KDD-AC5 -- An empty directory is a valid defaults-only boot; a missing
one is not.** A configuration directory that exists but holds no `*.php`
child contributes an empty app layer -- the same reading an empty routes
file gets (valid zero-routing, not an error). A path that is not a directory
fails closed with the path in the message, before any scan.

**KDD-AC6 -- The cache contract replays at the composition seam.** The
optional `ConfigCache` short-circuits the whole load when an artifact is
present; a source file added after the artifact was written is invisible
until the artifact is removed -- KDD-6's rebuild-is-explicit decision,
pinned by a test that boots, mutates a source, boots again (stale, by
design), removes the artifact and boots fresh. `AppBootBench`'s two
subjects (cold boot vs cached boot) keep the honest economics on record:
on the three-file fixture they land in the same band (the artifact IS the
whole merged tree, so a small profile gains nothing -- 87.7μs cold vs
107.1μs cached), and the cached subject's value is that its cost stays flat
as the profile grows while the cold path pays per file.

**KDD-AC7 -- The env overlay is opt-in by prefix.** `boot()` takes an
`EnvSource` rather than capturing the process environment itself: the
production host names its prefix (`EnvSource::fromEnvironment('APP_')`),
tests stay deterministic, and the layering promise (env wins over files,
pinned by `APP_FEATURE__FLAG` over the scan fixture's file value) is
exercised through the same seam the host uses.

**KDD-AC8 -- `app/` sits outside the analysers, on purpose.** The analysis
scopes (phpstan, phpcs, php-cs-fixer, rector, coverage, infection) cover
`src/`, `tests/`, `benchmarks/` -- the shipped profile files are plain
return-array data with no logic to analyse, and they are executed (syntax,
shape and validity) by `ApplicationBootstrapperTest`'s boot of the real
`app/configs/` directory on every CI run, which is the stronger guard: a
broken shipped profile fails the suite, not a style rule.

## Legacy attribution

The reference repository `mbetixz/zeflous-framework` (restored mid-October
2026; see its `docs/ARCHITECTURE.md`) walked this same road until its
structure hardened: a v2.7.0 monolith was dismantled move-only into a
hexagonal layout, Configuration System v2 arrived as a v2.21.0 retrofit
(`PhpFileConfigSource`, `EnvConfigSource` with the `ZEF_DATABASE__HOST`
fold, the `%secret:name%` resolver, schema-carried `ConfigKey` defaults,
`ConfigMigrator` versioning) on top of that entrenched shape -- and the
retrofit debt (migration steps, schema version pins, baselines as big as the
code) is what motivated the fresh restart this repository is. The lane
borrows the reference's *ordering* insight (defaults -> sources -> validate
-> freeze) but not its machinery: the layered builder, the directory adapter
and the compiled cache were already built here as first-class units, and the
orchestrator only composes them. The reference's `ConfigAggregator`
re-entrancy guard (a provider calling `merge()` from inside `getConfig()`
recursed to OOM) is noted as design input for the module lane's future
`app/modules/**` provider registry -- the failure mode is real and worth
guarding before the seam exists, not after.
