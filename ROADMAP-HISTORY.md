# ROADMAP History

A chronological record of **what has actually been done**, grouped by the area of
[`ROADMAP.md`](ROADMAP.md) it advances — a changelog mapped onto the roadmap.
Every row is a **merged pull request**, with its merge commit; nothing here is aspirational.
For the *current* implementation state of each area, see [`ROADMAP-STATUS.md`](ROADMAP-STATUS.md).

**Last updated:** 2026-10-11 · `main` @ `ca6ce131b`

**How to read this file**

| Column | Meaning |
|--------|---------|
| Work item | The merged PR's title |
| PR | Pull-request number |
| Merged | Merge date (UTC) |
| Commit | Merge commit SHA (short) |

---

## Summary

| Roadmap area | Completed items |
|---|---|
| Container | 2 |
| HTTP Layer | 1 |
| Error Handling | 1 |
| Kernel & Platform | 1 |
| Quality Gates & Tooling | 7 |
| CI / Supply chain | 34 (incl. auto-merge + Sonar lanes) |
| Changelog automation | 37 (4 human + 33 bot regenerations) |
| Roadmap docs | 3 |
| Configuration System | 18 |
| Probes / no-op | 6 (superseded, kept for the audit trail) |
| **Total merged PRs** | **95** |

---

## 1. Container

`ROADMAP.md` → **CONTAINER** (registration, scope, PSR-11).

| Work item | PR | Merged | Commit |
|---|---|---|---|
| Composer scaffold + strict audit engine (PHPStan/PHPCS/Fixer/Rector/Deptrac/Infection) — introduces `src/Contracts/Psr/Container/*`, `Container`, `ServiceStore` | #1 | 2026-10-07 | `f6aa0dbeb8` |
| refactor(quality): fix archeology error and add SARIF code-scanning gate (touches `Container.php`, kills escaped mutants) | #38 | 2026-10-08 | `7d5239e3a8` |
| test(container): assert the exact not-found message (mutant kill, folded into #38) | #38 | 2026-10-08 | `7d5239e3a8` |

## 2. HTTP Layer

`ROADMAP.md` → **HTTP Layer**.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| Composer scaffold — introduces the immutable `Message` envelope (`src/Zef/Framework/Message/Message.php`) | #1 | 2026-10-07 | `f6aa0dbeb8` |

## 3. Error Handling

`ROADMAP.md` → **Error Handling System**.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| fix(sonar): green Quality Gate — testable tooling, >90% coverage (touches `src/Zef/Framework/Exception/*`) | #2 | 2026-10-07 | `fbb1c2cdc9` |

## 4. Kernel & Platform

`ROADMAP.md` → cross-cutting kernel / version rules.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| Composer scaffold — `Kernel`, `Version`, `Contracts/ServiceProviderInterface`, `Health/*`, `src/autoload.php` | #1 | 2026-10-07 | `f6aa0dbeb8` |

## 5. Quality Gates & Tooling

The in-repo gate engine (`src/Zef/Framework/Tooling/*`, `scripts/ci/*`) that every other lane runs.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| Composer scaffold + strict audit engine (PHPStan/PHPCS/Fixer/Rector/Deptrac/Infection) + SonarCloud CI | #1 | 2026-10-07 | `f6aa0dbeb8` |
| fix(sonar): green Quality Gate — testable tooling, >90% coverage, no new exclusions | #2 | 2026-10-07 | `fbb1c2cdc9` |
| fix(ci): restore SonarCloud PR decoration + scope parity with reference | #3 | 2026-10-07 | `2b77f4f080` |
| refactor(quality): fix archeology error and add SARIF code-scanning gate | #38 | 2026-10-08 | `7d5239e3a8` |
| refactor: replace exec with Symfony Process for command execution | #40 | 2026-10-08 | `0fdc01829a` |
| fix(ci): run gate commands through the shell in the process runner | #42 | 2026-10-08 | `98fe592a22` |
| chore(ci): remove unreferenced mutation:ci composer script (dead configuration removal — the CI Infection lane runs `ci:infection` → `mutation` + `mutation:zones`; the `mutation:ci` variant carried the same flags unreferenced by any workflow, script, Makefile or doc) | #236 | 2026-10-11 | `952f6cfd5` |

## 6. CI / Supply chain

Every workflow, gate, and supply-chain lane. This is where the bulk of the recent work lives.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| Composer scaffold + strict audit engine (PHPStan/PHPCS/Fixer/Rector/Deptrac/Infection) + SonarCloud CI | #1 | 2026-10-07 | `f6aa0dbeb8` |
| fix(sonar): green Quality Gate — testable tooling, >90% coverage, no new exclusions | #2 | 2026-10-07 | `fbb1c2cdc9` |
| fix(ci): restore SonarCloud PR decoration + scope parity with reference | #3 | 2026-10-07 | `2b77f4f080` |
| chore(ci): ZEF_TOKEN for auto-merge queue chain + adopt dependency-review/docs-check/phpbench | #4 | 2026-10-07 | `ca671fd626` |
| ci(reproducible): release-drafter + SBOM lanes; GITHUB_TOKEN for full repo access | #5 | 2026-10-07 | `0269737cb3` |
| ci(changelog): reuse one update branch/PR so re-runs never duplicate | #9 | 2026-10-07 | `ca02722b06` |
| ci(changelog): dispatch required checks on the update branch | #12 | 2026-10-07 | `9035e76066` |
| ci(changelog): grant actions:write so the update branch can dispatch checks | #13 | 2026-10-07 | `82200460ff` |
| ci(changelog): attach SonarCloud analysis to the update PR | #14 | 2026-10-07 | `b93e4b7070` |
| fix(ci): make CI Strict run on bot-authored PRs | #24 | 2026-10-07 | `aae6e3a757` |
| fix(ci): make CI Strict run on bot-authored PRs | #25 | 2026-10-07 | `0df6c75fbd` |
| fix(changelog): merged-only PR selection + split pull_request trigger | #27 | 2026-10-07 | `32a59d9ca0` |
| fix(auto-merge): approve runs on post-sync head | #31 | 2026-10-07 | `ffee19b21e` |
| feat(ci): add PR Validator gate and psalm/progpilot static analysis | #33 | 2026-10-07 | `a01438ba7d` |
| feat(ci): add PhpCodeArcheology strict architecture gate | #36 | 2026-10-08 | `dea184780e` |
| fix(ci): make the archeology gate deterministic (drop the non-portable baseline) | #37 | 2026-10-08 | `6a7ee2865c` |
| refactor(quality): fix archeology error and add SARIF code-scanning gate | #38 | 2026-10-08 | `7d5239e3a8` |
| refactor(ci): split heavy jobs and add roadmap status | #39 | 2026-10-08 | `4744045781` |
| fix(ci): make CI Strict aggregator fail when lanes fail | #46 | 2026-10-08 | `66119a5e00` |
| fix(ci): approve bot runs on every sweep via non-gated workflow_run trigger | #49 | 2026-10-08 | `47d27aeaed` |
| chore(ci): rename bot PAT secret to ZEF_TOKEN in auto-merge | #50 | 2026-10-08 | `0aea47dd75` |
| ci(sonar): drop unnecessary composer install from analysis job | #53 | 2026-10-08 | `851d05010b` |
| ci(sonar): drop broken composer install step and bridge coverage on every event | #55 | 2026-10-08 | `361d520d57` |
| ci(sonar): use full-history checkout for better analysis relevancy | #57 | 2026-10-08 | `35c72874b0` |
| ci: bump sonar checkout to v4.4.0 and split heavy jobs | #59 | 2026-10-08 | `94f246061e` |
| ci(sonar): pin checkout to v7.0.1 to actually drop the Node.js 20 warning | #61 | 2026-10-08 | `dd121ba531` |
| fix(ci): fall back to the built-in token when the bot PAT is under-scoped | #62 | 2026-10-08 | `d3c5102700` |
| ci(auto-merge): correct the PAT caveat comment (update-branch does not support fine-grained PATs) | #67 | 2026-10-08 | `0bf4d04440` |
| ci(auto-merge): correct ZEF_TOKEN caveat comment | #71 | 2026-10-08 | `6532ec027a` |

## 7. Changelog automation

### 7.1 Human-authored

| Work item | PR | Merged | Commit |
|---|---|---|---|
| feat(changelog): per-PR API changelog (CHANGELOG.md + docs/api/changelog.json) | #6 | 2026-10-07 | `67bd707bed` |
| feat(changelog): split changelog into one file per release version | #16 | 2026-10-07 | `cca86c2985` |
| feat(changelog): adopt official github-changelog-generator as a complementary lane | #17 | 2026-10-07 | `3ea2b5d516` |
| feat(changelog): regex category mapping + release-based versioning | #28 | 2026-10-07 | `4ca5c999ad` |

### 7.2 Bot regenerations (`github-actions[bot]`)

Each of these is the automation re-writing `CHANGELOG.md`, `CHANGELOG-unreleased.md`, `docs/api/changelog.json` and `docs/api/changelog-official.md` after a merge — no hand edits.

| PR | Merged | Commit |
|---|---|---|
| #7 | 2026-10-07 | `b1f39348f9` |
| #15 | 2026-10-07 | `c5b59fd659` |
| #18 | 2026-10-07 | `b1386a3256` |
| #20 | 2026-10-07 | `aab4a71bd5` |
| #26 | 2026-10-07 | `756a868456` |
| #29 | 2026-10-07 | `d5dc5b5315` |
| #30 | 2026-10-07 | `4f0ebd1049` |
| #32 | 2026-10-07 | `27a49272df` |
| #43 | 2026-10-08 | `15c9b91568` |
| #44 | 2026-10-08 | `677446ce47` |
| #47 | 2026-10-08 | `5373b67a35` |
| #48 | 2026-10-08 | `d48b6268f9` |
| #51 | 2026-10-08 | `1c5c8e6003` |
| #52 | 2026-10-08 | `be4cfe38ee` |
| #54 | 2026-10-08 | `042180b7af` |
| #56 | 2026-10-08 | `b6a94b99be` |
| #58 | 2026-10-08 | `d96957f733` |
| #60 | 2026-10-08 | `0c3d6d4ce1` |
| #63 | 2026-10-08 | `8485c62e69` |
| #64 | 2026-10-08 | `f7ed76823d` |
| #68 | 2026-10-08 | `09b1a329e4` |
| #69 | 2026-10-08 | `6003f92050` |
| #72 | 2026-10-08 | `6df83957c6` |
| #73 | 2026-10-08 | `ffc0cdd04c` |
| #75 | 2026-10-08 | `499a482e3c` |

## 8. Roadmap documentation

| Work item | PR | Merged | Commit |
|---|---|---|---|
| refactor(ci): split heavy jobs and add roadmap status | #39 | 2026-10-08 | `4744045781` |
| ci: bump sonar checkout to v4.4.0 and split heavy jobs | #59 | 2026-10-08 | `94f246061e` |
| docs(roadmap): refresh ROADMAP-STATUS header to current main | #74 | 2026-10-08 | `c6c4734241` |

## 9. Probes & no-ops (audit trail)

Superseded or investigative runs, kept so the history is complete and verifiable.

| Work item | PR | Merged | Commit |
|---|---|---|---|
| No changes made: insufficient quota to complete coverage setup | #19 | 2026-10-07 | `3136a93bb6` |
| probe: update-branch trigger test | #21 | 2026-10-07 | `03c9b4b04a` |
| probe: update-branch trigger v2 | #22 | 2026-10-07 | `710ba91ffe` |
| probe: dispatch required check | #23 | 2026-10-07 | `017e4a988c` |
| refactor: consolidate list initialization | #41 | 2026-10-08 | `84fcb5b6a3` |
| refactor: validate url scheme before urllib calls | #45 | 2026-10-08 | `e131320af2` |

---

## 10. Configuration System + DSL + Radix Tree

`ROADMAP.md` → **Configuration System + DSL + Radix Tree** (The Triad, layer 1).

| Work item | PR | Merged | Commit |
|---|---|---|---|
| feat(ci): assert the zero-dependency invariant in the static gate (`ZeroDependencyGate`, `ComposerManifest`, zero-deps lane) | #150 | 2026-10-10 | `c86c4da082` |
| feat(config): immutable config repository with dot-notation access (`Config`, `DotKey`, `ConfigInterface`, deptrac layer) | #153 | 2026-10-10 | `a2667659cb` |
| feat(config): immutable with-mutations for config entries (`ConfigMutations` trait, `DotWriter`, `DotRemover`, `DotListWriter`, `DotListLocator`) | #159 | 2026-10-10 | `b1c46748e5` |
| feat(config): php file and directory config loaders (`ConfigLoaderInterface`, `PhpFileLoader`, `DirectoryLoader`, `ConfigLoader` director with prefix nesting) | #167 | 2026-10-10 | `9a1d20c5fd` |
| fix(config): restrict php file loading to an explicit allowlist (basename inventory + `realpath()` canonicalisation before `require`; inventory threaded through `DirectoryLoader` and the `ConfigLoader` director) | #176 | 2026-10-10 | `98cfe19191` |
| feat(config): layered default-app-env configuration merge (`ArrayMerger` deep merge with list-replace, `EnvSource` prefix/`__` fold with raw string values, `LayeredConfigBuilder` Default < App < Env → frozen `Config` — the director's first in-repo consumer) | #184 | 2026-10-11 | `7c771b88f` |
| feat(config): strict schema validation for route configuration (Standard Route Schema — `SchemaViolation` + `EXPECTATIONS` vocabulary with path/expected/actual data and factories, generic fail-closed `ConfigSchema` engine with complete ordered path-carrying violations, `SchemaValueRules` checks for the four value kinds, `RouteSchema` with `path`/`method`/`handler` required and `name`/`middleware`/`constraints`/`meta` optional; unknown keys are violations) | #195 | 2026-10-11 | `e3829b6d2` |
| feat(config): compiled configuration cache (`ConfigCache` — `dump()` compiles a configuration array into a plain PHP file behind a generated-file header via `var_export`, `load()` executes it back with `require`; identity round trip, mtime-free by decision (KDD-6), fail-closed on missing directory / failed write / missing cache / non-array corruption) | #202 | 2026-10-11 | `fe6dc188d` |
| feat(kernel): bootstrap the config repository into the kernel (`ConfigBootstrapper` — load+merge via the layered builder, validate via `RouteSchema::assertList()` over the routes key, cache hit short-circuits the builder entirely / miss compiles after validation; `ConfigCache::exists()`; `Kernel::__construct(?Container, ?Config)` + `config()` backward-compatible; deptrac Kernel += Config; `ConfigBench` dot-notation vs direct access; `docs/ci-notes/config-system.md` decision record KDD-1..7 with PHLAK attribution) | #205 | 2026-10-11 | `64bb213ad` |
| feat(router): parse route paths into static and parameter segments (route path grammar — `RoutePath::parse()` shape rules, `RoutePathGrammar` + `PathBraceScanner` brace/segment/name rules, `PathSegment` value object, `RouterException`; landed under a mis-scoped Router namespace) | #211 | 2026-10-10 | `97d6b110f` |
| refactor(config): move the radix route grammar under the configuration system (relocates the #211 slice `Zef\\Framework\\Router` → `Zef\\Framework\\Config\\Routing`; `RouterException` folded into `ConfigException`; deptrac Router layer removed — the Config collector covers the Routing sub-namespace) | #223 | 2026-10-11 | `71c3d1784` |
| feat(config): compile route entries into validated route records (`CompiledRoute::fromArray(mixed)` — the engine's consumption view of the Standard Route Schema: `RouteEntryShape` known-fields + required-field shape checks, `RouteEntryCollections` string/list/constraints-map value rules with the `CONSTRAINTS` field-name constant, `RouteEntryMeta` free-form meta map, `RouteConstraints` name-validated fully-anchored patterns with a compilability probe, method upper-case + de-duplicate + sort, `RoutePath::pattern()` verbatim accessor) | #217 | 2026-10-11 | `4c34cd6e9` |
| feat(config): compile routes into an immutable radix tree (`RadixTree::compile(array)`/`match(method, path)` — the Triad's engine: `RoutePatternTokens` tokenises a parsed path into static runs and parameter edges, `RouteTreeBuilder` + `RouteNodeInserter`/`StaticRunInserter`/`SharedPrefixLength` build the prefix-compressed tree (immutable after compile), `RouteMatcher`/`PrefixConsumption`/`StaticDescent`/`SegmentCapture`/`LeafRoute` walk it depth-first with full backtracking (static child first, then the parameter edge), `RouteTableRegistry` rejects duplicate canonical patterns and names, `MatchResult` found/notFound/methodNotAllowed with sorted allowed methods; `CompiledRoute::answersMethod()`/`accepts()` judge method case and constraints) | #229 | 2026-10-11 | `90916958e` |
| feat(config): reverse routing url generation (`RadixTree::route(name)` fail-closed + compile-time name index via `RouteTableRegistry::namedRoutes()`; `RouteUrlGenerator::generate(name, params)` — `RouteUrlSegments` walk, `RouteParameterRenderer` (given + string-form + constraint + `rawurlencode`), `RouteParameterString` narrowing, `RouteParameterSet` excess rejection; round-trip self-validation; `RouterBench` tree-vs-linear flat-cost evidence; `docs/ci-notes/radix-engine.md` decision record KDD-R1..R9 with the scope correction) | #232 | 2026-10-11 | `2d45dc48f` |
| feat(config): route dsl fluent compiler (`RouteDsl::define(Closure)` — the fluent compiler of roadmap step 4: seven verbs plus `match()` draft a chainable `PendingRoute` (`name`/`where`/`middleware`/`meta`, declare-once except middleware), `group()` composes a validated `RoutePrefix` and restores prefix+middleware state on close (a throwing closure included), scope middleware flows forward only — stacking global → group → route; archeology-sized collaborators `RouteDslScope`/`RouteDslSnapshot`/`RouteDslMethods`/`RouteDslConstraints`/`RouteDslFields`/`RouteDslMiddleware`/`RoutePrefix`; Triad-closure integration test declare → assert → compile → match → reverse-route; `LintReport` hardened with a `CallbackFilterIterator` so its skip-behaviour mutation kill no longer depends on directory iteration order) | #240 | 2026-10-11 | `9237f4672` |
| feat(config): allow method-disjoint routes on one pattern (amends the engine's duplicate rule for the schema's one-entry-per-method model: `RouteMethodClaims` rejects only a method claimed twice on one canonical pattern — REST's `GET /photos` and `POST /photos` compile; `RadixNode` carries the method-disjoint routes, `LeafRoute` returns every constraint-accepting candidate (a constraint failure narrows candidacy route by route), `RouteMethodAnswer::resolve()` selects the method answer and unions the 405 methods sorted, the parameter zipper lives in `RouteParameters`; KDD-R5 amended in `docs/ci-notes/radix-engine.md`) | #243 | 2026-10-11 | `aa7001a83` |
| feat(config): route dsl resource macro (`RouteDsl::resource()` — one declaration expands into the seven-route CRUD table: index/create/store/show/edit/update (one PUT+PATCH entry)/destroy with `Controller@action` handlers and `<base>.<action>` names derived by `RouteResourceName` from the path's static segments, only `{id}` constrainable, the expansion registering through the façade's public surface so prefix, middleware and every declaration-time validation apply; `RouteDslBench` records the build economics — define() linear, paid once at build/cache time; `docs/ci-notes/dsl-builder.md` decision record KDD-D1..D8 with the cross-lane engine discovery and the local-vs-CI archeology yardstick) | #247 | 2026-10-11 | `4266b088d` |
| feat(config): the routing runtime over the frozen configuration (`RoutingRuntime::fromConfig()` — the Triad's fifth seam, roadmap step 5 Loader -> Engine: reads the route table the bootstrapper pipeline already loaded, merged, validated and cached; the engine's radix tree is compiled AT MOST ONCE per instance, lazily and memoised — compile-once-per-worker economics, a fresh instance is the hot-reload path; a missing routes key is a valid zero-routing table, a non-array fails closed, a map-addressed table is normalised to a list with the keys discarded — the entry's name field is the single source of truth; `tree()`/`match()`/`route()`/`url()` answer from the one immutable tree, the raw method travels to the engine's single upper-casing policy point; `RoutingRuntimeBench` records the economics — memoised matching flat at 4.1-5.8us across 15-300 routes vs 185-4,070us when the runtime is rebuilt per request) | next | 2026-10-11 | `d2557c0ea` |

## Not yet done

These roadmap areas have **no merged work** yet (see `ROADMAP-STATUS.md` for the live meter):

- **Container** — autowiring, autoconfiguration, injectors, tags, decorator/proxy, compiler passes, DI extension points, PSR-11 service locator.
- **Router (Radix Tree)** — not started.
- **Middleware Pipeline** — not started.
- **HTTP Layer (Zero-Library Shim)** — not started beyond the PSR container shim.
- **Event Source System** — not started.
- **Module Integration System** — not started beyond the `ServiceProviderInterface` contract.
- **Adapters Layer** (view engine, database) — not started.
- **Error Handling** — handler chain, debug/production modes, observability not started.

---

## How to maintain this file

1. When a PR merges that advances a roadmap area, add a row to that area's table (Work item = PR title, plus PR number, merge date and merge commit).
2. Update the **Summary** counts and the **Last updated** line (date + `main` commit).
3. Do not restate the roadmap prose — link to `ROADMAP.md` and record only what shipped.

