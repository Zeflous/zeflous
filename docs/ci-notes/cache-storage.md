# The Compiled Caches' Storage Wiring — KDD

**Scope:** the storage layout's last named family integration (the
follow-up both KDD-SL6 and KDD-ES7 reserved, the one that remained
open after the error family's #653 and the sessions lane's #656
landed): the boot's two acceleration artifacts — the configuration
cache and the container AOT cache — now derive their addresses from
the `files/**` route map's `cache` lane when the host declares a root
at the composition seam. One wiring fold (`CacheStorageWiring`) at
the Kernel lane, the `ApplicationBootstrapper`'s one new
construction-time word (`storageRoot`), deptrac's `Kernel += Storage`
widening, and the shipped profile's `storage.php` comment widened to
name the two seams, `feat/cache-storage-lane`.

**Verified locally before the PR:** `ci:static` EXIT-0 (6096 tests /
14692 assertions; smoke 2760B/20000 iterations; concurrency 23
workflows; roadmap:gate pass; audit 0 advisories) · coverage 98.49%
against the 90% floor · psalm EXIT-0 with the baseline NOT growing —
the fold gained its in-src consumer on the day it was born (the
orchestrator calls it), so unlike the CLI-consumed renderers it never
needed an `UnusedClass` entry at all · progpilot `[]` · archeology 0
error-level findings (the fold's two verbs sat under the difficulty
ceiling without a split — the wiring question is two straight lines)
· scoped infection over the two touched source files, 35 mutants (28
over the fold, 7 over the orchestrator's new composition lines), MSI
100/100, 0 escapes, 0 ignores, no new ignore entry · deptrac 0
violations over the `Kernel += Storage` widening · zero-deps PASSED
(`require` still `{"php": "^8.4"}`, lock `[]`).

## The map

| Unit | Question it owns |
|---|---|
| `CacheStorageWiring::configCache()` | the configuration cache seam: the explicit instance verbatim, else the `cache` lane + the canonical name, else null |
| `CacheStorageWiring::containerAotCache()` | the AOT cache seam: the same precedence over its own artifact |
| `CONFIG_ARTIFACT_NAME` / `CONTAINER_AOT_ARTIFACT_NAME` | the two canonical spellings inside the `cache` lane (`zef-config.php`, `zef-container-aot.php`) — public consts the `zef-errors.jsonl` precedent shapes |
| `ApplicationBootstrapper::$storageRoot` | the composition-time declaration: the host glue's one word, spelled before the repository exists |
| `app/configs/storage.php` | the shipped profile's own comment, now naming the two seams so a deployer knows which word provisions what |

## KDD-CS1 — the declaration is a construction-time word, not a knob: the circularity is structural

The configuration cache short-circuits the very repository a knob
would be read from: a cache address derived from the merged
configuration would demand loading the whole configuration — the
load the cache exists to skip — to learn where its own fast path
lives. The root must therefore be spelled before the repository
exists, in the host glue's own hand, exactly where the two cache
instances themselves are already spelled
(`ApplicationBootstrapper`'s constructor). KDD-ES7 framed this
precisely: the caches' "callers' paths are construction-time
addresses owned by the boot seams, not error-family knobs" — and the
`storage.root` knob IS the error family's word (it feeds
`ErrorStorageWiring` and `SessionStorageWiring` at request time,
from the frozen repository). Two seams because the two moments are
genuinely different: the request-time families read the root AFTER
the repository froze, the boot-time artifacts need it BEFORE. A
deployment wanting one word behind both seams spells the knob from
its own environment in its own `storage.php` — the host's
composition freedom, never the framework's hidden env read (the boot
seam reads no environment of its own; `AppEnvironment`'s canonical
`APP_ENV` word stays the only unconditional one).

## KDD-CS2 — the precedence is one-way, and the root is the derivation's input, never a promise

An explicitly handed cache instance answers verbatim, and derivation
only fills the seams the host left unset — the doctrine every family
integration in this cross-cut carries. The consequence this slice
adds: a verb whose seam is already answered NEVER TOUCHES THE
FILESYSTEM. A host handing both instances over an unusable root
boots exactly as it did before the layout existed, because the root
is the derivation's input, not a promise to validate a tree nobody
consumes — refusing that boot would be refusing over an unused word.
The boot test pins the composition: both caches explicit, the root
pointing at a directory that does not exist, the boot green and the
explicit artifact written at its own address.

## KDD-CS3 — the silent default is the honest no-op the migration story demands

A null or empty root derives nothing: the boot answers no cache and
no artifact, every semantic exactly as it stood before the layout
existed. The empty string is the config knob's own silent spelling
(`StorageRootDeclaration`'s `''`-declares-no-layout posture) replayed
at the composition seam, so a host glue writing
`getenv('ZEF_STORAGE_ROOT') ?: null` lands on the honest no-op when
the environment stays silent. The shipped profile's `storage.root`
stays the empty string — a deployment that upgrades and declares
nothing at the composition seam observes nothing change, and the
ad-hoc posture (a host constructing its own cache instances with its
own paths) stays a first-class citizen rather than a deprecation to
be flushed.

## KDD-CS4 — the walk is the layout's own whole five-lane walk, and it answers BEFORE the configuration is read

Each verb resolves its own walk inline over the declared root —
`StorageLayout::resolve()`, the whole tree, fail-closed with the
refusing seam named, never a one-lane shortcut that could drift from
the map the inspector prints (KDD-ES4's doctrine, the two-verb
inline shape KDD-ES3 spelled). The ordering is deliberate and
pinned: the derivation sits at the TOP of `boot()`, so a declared
root whose `cache/` lane is missing refuses the boot before a single
configuration file is read — the boot test pins this by pointing the
boot at a configuration directory that does not exist and asserting
the refusal that answers is the walk's `InvalidArgumentException`
naming the lane, not the loader's missing-directory `ConfigException`.
A misprovisioned tree is the operator's verdict to receive at the
first moment, not a mid-flight artifact-write failure to debug after
the boot did half its work.

## KDD-CS5 — WHERE, never WHETHER: the knob still governs what the boot accelerates

The derived container AOT cache is handed to the container seam
exactly as an explicit one would be, and `aot.enabled` still governs
its use — a deployment that declares a root and leaves AOT off gets
the configuration artifact and no container artifact, because the
knob, not the layout, decides what the boot accelerates (the boot
test asserts the absent `zef-container-aot.php` beside the present
`zef-config.php`). The derivation answers WHERE the artifacts land;
the compile/dump lane's own mode and toggle knobs answer WHETHER the
lane runs — two questions the existing seams already own, and this
fold deliberately re-negotiates neither.

## KDD-CS6 — the canonical names are public consts, the CLI symmetry the log file's own precedent spelled

`zef-config.php` and `zef-container-aot.php` ride the `zef-` prefix +
the thing + the extension the error family's `zef-errors.jsonl`
established, and they are public consts on the wiring fold because
`bin/zef`'s errors arm already reads `ErrorStorageWiring::
LOG_FILE_NAME` for its own default — the day a CLI arm wants to
report or clear the compiled caches, the spelling it reads is the
spelling the boot writes, and the two can never drift apart. One
spelling per artifact, one place it lives.

## KDD-CS7 — the cross-cut closes; what stays out stays out by name

With the `cache` lane's first writer landed, every integration
KDD-SL6's named list reserved is answered (the error family's log
address, the replay directory, the sessions lane and its file
driver, the configuration and container AOT caches) — the storage
layout's own story is complete. Three things this slice deliberately
does not touch, each its own named future: the route tree cache
(`RouteTreeCache` is a real artifact-writing unit, but no boot seam
wires it today — `RoutingRuntime` is the memoised in-memory engine,
and an artifact lane for it belongs to the routing family's own
decision, not to this fold); the inspection boots' throwaway AOT
artifacts (`RouteInspectionBoot`/`ContainerInventoryReport`/
`HttpInspectionBoot` unlink their temp artifacts deliberately — a
doctor never writes the lane it diagnoses); and the `queue/` lane
with the module `events` knob (the Adapters lane's own recorded
reservation — the file delivery store deliberately keeps its own
directory posture until the wired default lands with its consumer).
