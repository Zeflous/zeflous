# Configuration System — decision record

The implementation record for the Configuration Layer (layer 1 of "The Triad":
Configuration + DSL + Radix Tree, `ROADMAP.md`). Seven merged PRs, executed
serially with auto-merge and zero gate weakening:

| PR | Scope |
|----|-------|
| #150 | Zero-dependency gate, layer scaffold |
| #153 | Immutable repository: `Config`, `DotKey`, `ConfigInterface`, deptrac `Config` layer |
| #159 | Copy-on-write `with*` mutations: `ConfigMutations`, `DotWriter`, `DotRemover`, `DotListWriter`, `DotListLocator` |
| #167 | PHP file & directory loaders: `ConfigLoaderInterface`, `PhpFileLoader`, `DirectoryLoader`, `ConfigLoader` director |
| #176 | Allowlist hardening of the file adapter (basename inventory + `realpath()` canonicalisation) |
| #184 | Layered merge: `ArrayMerger`, `EnvSource`, `LayeredConfigBuilder` (Default < App < Env) |
| #195 | Strict schema validation: `ConfigSchema` engine, `SchemaValueRules`, `SchemaViolation`, `RouteSchema` |
| #202 | Compiled cache: `ConfigCache` (dump/load, mtime-free) |
| (this PR) | Kernel bootstrap: `ConfigBootstrapper`, `Kernel::config()`, `ConfigBench` |

## Key design decisions (KDD)

1. **`phlak/config` is an API inspiration, not a dependency.** `require`
   stays `{ "php": "^8.4" }` (the zero-library doctrine). What was adopted:
   the dot-notation ergonomics (`get`/`has`/`split`/`toArray`) and the
   loader/prefix shape. What was deliberately *not* adopted: runtime
   mutability (`set`/`unset`/`append`/`prepend` moved to the build phase),
   the mixed constructor I/O, and the multi-format splitters (our format
   adapters are in-repo seams per `ConfigLoaderInterface`).
   API design inspired by [PHLAK/Config](https://github.com/PHLAK/Config)
   (MIT, Chris Kankiewicz).

2. **Runtime immutability.** `Config` is `final readonly`; every mutation is
   a copy-on-write `with*` that returns a new repository. A repository built
   once can be shared across a persistent worker's requests — the same
   doctrine as `Kernel` and `Container`.

3. **Dot-notation is the address language.** `get('database.mysql.host')`,
   `has`, `split`, and the violation paths of the schema layer
   (`method.0`, `constraints.id`) all share `DotKey`'s semantics: malformed
   keys throw (fail closed), numeric segments address list offsets.

4. **The layer hierarchy is explicit: Default < App < Env.** Later layers
   win; string-keyed maps recurse, lists are full declarations (never
   concatenated — Symfony-style, documented and pinned by test); `[]`
   clears a key. `EnvSource` folds `APP_DATABASE__HOST` →
   `database.mysql.host` with raw string values — casting is the schema
   layer's job, never a silent coercion.

5. **Validation is fail-closed with path context.** `ConfigSchema` is a
   generic field-rule engine (`[required, kind]`), `RouteSchema` is the
   Standard Route Schema of roadmap step 1 (`path`/`method`/`handler`
   required; `name`/`middleware`/`constraints`/`meta` optional, typed;
   `meta` values free-form). Violations are complete and ordered, every one
   carries its path, and an unknown key is a violation that names the
   schema's fields — a typo cannot produce a route that half-exists.

6. **The cache is deterministic and mtime-free.** `ConfigCache` dumps
   `var_export()` output behind a generated-file header and loads it with
   `require`. Freshness is never probed at runtime: a persistent worker
   loads once per process, and a rebuild is an explicit tooling act. The
   bootstrapper validates *before* compiling, so an invalid configuration
   never reaches the artifact, and validates on the cache path too — the
   cache is trusted to be fast, not to be right.

7. **Format adapters are separate seams.** PHP files came first
   (`PhpFileLoader` + `DirectoryLoader` + the `ConfigLoader` director);
   JSON/YAML arrive as new `ConfigLoaderInterface` implementations without
   touching any consumer. The file adapter executes only allowlisted
   basenames on `realpath()`-canonicalised paths (#176) — a declared
   inventory, not whatever a caller assembles.

## The bootstrapper pipeline

`ConfigBootstrapper::boot()` is a thin orchestrator over the units above:
**load + merge** (`LayeredConfigBuilder`) → **validate** (`RouteSchema` over
the `routes` key; a route-less repository is valid zero-routing) → **cache**
(`ConfigCache`: hit = the builder is never consulted; miss = compile after
validation) → one frozen `Config`, handed to `Kernel::config()`.

## Measurement notes (battle-earned)

- `implode()` casts `Stringable` on its own: an identity `array_map()` in
  front of it is an unkillable `UnwrapArrayMap` mutant — render directly
  (#195).
- SonarCloud's new-coverage condition is computed on *changed lines only*:
  an error branch that is "killable via mutant inversion" but never executed
  by a real test fails the per-PR condition even while the aggregate gate
  stays green — cover I/O failure branches for real (#202: the failed-write
  branch, tested by targeting the test directory itself with a scoped error
  handler).
- Halstead difficulty (php-code-archeology, threshold 20) aggregates per
  file/class: split validators by responsibility (orchestration / value
  rules / violation data) and prefer partial-scope renames — only those add
  unique operands (#195: 40.26 → 19.09 without touching any threshold).
- `php:S1142` caps methods at 3 returns; `php:S2003` (require vs
  require_once) was disabled owner-side after the re-loadability contract
  was documented and pinned by test (#167/#176).
- Psalm accepts `require $var` only when the variable is proven by
  `is_file()`/`file_exists()` in the same scope — mirror that pattern in
  every loader (#176, #202).
