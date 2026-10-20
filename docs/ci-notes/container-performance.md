# Container — The performance mode and the analysis CLI (ROADMAP area 11 + area 12's command)

KDD for the container roadmap's closing lane: area 11's three items
(reflection caching, the development/production mode, the
zero-overhead compiled container posture) and area 12's CLI analysis
command, integrated with the error-handling campaign's own doctrine.
Campaign line: `feat/container-performance-cli`, the container
roadmap's twelfth and final area.

## Attribution

- **ROADMAP area 11 + area 12**: the requirement — "Reflection
  caching: hasil refleksi disimpan agar introspeksi tidak mahal";
  "Mode development vs production: Cache & pelaporan error disesuaikan
  lingkungan"; "Zero-overhead compiled container: untuk produksi
  berperforma tinggi"; "Perintah CLI analisis: melihat daftar
  service, dependensi, dan status tag".
- **The error lane's own doctrine** (`DebugToolbarFactory`, merged in
  #444): the environment verdict's shape — a configuration knob plus
  the `APP_ENV` allowlist (`local`, `development`, `dev`, `test`,
  `testing`), fail-closed to production when the variable is absent.
  The container's `ContainerMode` mirrors it exactly, the vocabulary
  re-declared rather than shared because the layer graph keeps the
  middleware seam unreachable from the container lane — the same
  re-declaration trade `AotToggles` carries (its own KDD records the
  decision), and the one-environment-doctrine integration this lane
  owes the error campaign.
- **Symfony's kernel.debug**: studied and *diverged from*. Symfony's
  debug mode tracks resources (file mtimes) to prove container-cache
  freshness. zeflous refuses filesystem heuristics on principle
  (`ContainerAotCache`'s KDD: "a deterministic digest over the
  in-memory configuration, never a filesystem heuristic the runtime
  guesses at"), so the development mode's honest spelling is stronger:
  development *never trusts the artifact at all* (see divergence 1).
- **The error lane's CLI family** (`bin/zef errors:report` /
  `errors:clear` / `errors:replay`, #444): the guarded-CLI doctrine
  the `container:inventory` command joins — usage and path defects
  exit 2, operational refusals exit 1, the answer exits 0; `realpath`
  canonicalisation; no suppressed failures.
- **The AOT lane** (#476's compile/dump lane): already the
  zero-overhead compiled container — the artifact's baked closures
  inline the literal `new \Class(...)` construction with no
  reflection. Area 11's contribution is the mode wiring that makes
  the production posture explicit and the memo that covers the lanes
  the artifact does not.

## D.1 — What "cache disesuaikan lingkungan" honestly means

The AOT artifact's digest proves *configuration* freshness — the
nine source maps plus the framework version. It cannot prove *source*
freshness: a class file edited after the dump leaves the baked
closures pointing at the old spelling, and the digest still matches.
The original KDD framed that gap as "the build pipeline's concern" —
correct for production, where deploys are atomic and the artifact is
rebuilt.

Development is the environment where that framing inverts: source
churn is the *normal state*, not the exception, and a dev boot that
installs a digest-matching artifact is serving closures the live
source may already contradict. Symfony answers this with resource
tracking; zeflous's honest answer is cheaper and stronger:

**Divergence 1 — development trusts the artifact with nothing.** A
development boot neither probes nor dumps. The probe skip is
correctness (the digest cannot speak to the question development
asks); the dump skip is economy (a churned artifact a next dev boot
would only distrust again). Production keeps the full contract —
install-if-fresh, dump-if-absent-or-stale. The boot answer names the
disarm (`AotOutcome`'s development note), so the posture is
introspectable, never silent. The explicit mode beat: spelling
`container.mode` wins over the environment, both directions.

**Divergence 2 — no fold at boot for "pelaporan error".** The
roadmap's other half ("error reporting adjusted to environment") is
*not* answered by tersifying boot-time refusals: an operator reading a
failed production deploy needs the full walked chain, and hiding it
would trade honesty for theatre. The honest answer is where the
environment actually meets the report: the runtime boundary (a
container refusal mid-request flows through `ErrorMappingMiddleware`
— production answers the generic RFC 9457 envelope, development the
redacted message, the log keeps the full refusal on both sides —
`ContainerRefusalBoundaryIntegrationTest` pins the composition) and
the CLI (an operator's window that always carries the full chain).

## D.2 — What "reflection caching" honestly costs

The audit of `new ReflectionClass` across the tree found two lanes
that pay reflection *per resolution*, not per boot: the definition
compiler's class-shape closure (`newInstanceArgs` ran a fresh
reflection inside the resolution closure — a transient service
reflected on *every* `get()`) and the lazy lane's deferral answer
(`newLazyProxy` rode a fresh reflection per transient proxy). Every
other reflection site is a boot-time once-per-boot read each unit
owns for itself (`ReflectionMetadataExtractor`'s "reflection lives
HERE and only here" discipline) — those stay, by design.

`ReflectionMemo` carries one `ReflectionClass` per class name for the
unit that owns it. The two per-resolution lanes are threaded: the
compiler through its constructor, the deferral rider through
`ServiceStore`'s construction chain. The census (`count()`) is the
observability the tests pin the reuse with; it rides the psalm
baseline as a deferred consumer under the inventory's own precedent
(`entry`/`ids`/`lazy` sat baselined from #487 until this lane's CLI
consumed them — the baseline shrank by more than this lane added).

**Divergence 3 — the memo is not shared between the two lanes.** The
container's public constructor stays parameter-free (the
minimal-surface rule), so the compiler's memo and the resolution
lane's memo are two instances and a class both eagerly compiled and
lazily proxied reflects twice per boot — twice, not twice per
resolution. The AOT artifact needs no memo at all: its closures inline
the construction.

**Divergence 4 — `LazyConstructions::of` takes the caller's memo.**
The programmatic deferral spelling grew a first parameter rather than
keeping its own reflection: the resolution lane hands its per-container
memo (a transient's every resolution reuses the one carried
reflection), a host embedding the spelling passes their own.

## D.3 — The analysis CLI

`bin/zef container:inventory <config-directory> [--id=<service>]`
boots the host's configuration directory through the same
`ApplicationBootstrapper` the runtime rides — the analysis answers
the declarations the configuration actually spells, never a parallel
parse, and the mode doctrine rides along for free (a development
environment compiles the live source afresh; production keeps the
artifact posture). The rendering is plain text over the inventory's
own data: one line per declared identifier, the boot answer's note,
the unreferenced report, the live lazy marks, or one identifier's
full entry.

**Divergence 5 — the throwaway artifact seam.** The host's real boot
wires their own `ContainerAotCache` location, which the CLI cannot
know — but `aot.enabled` without a cache is the seam's own refusal.
The analysis wires a temporary cache file (never pre-created, so no
torn-artifact probe), which exists only to satisfy the seam: the full
lane compiles the same declarations whichever artifact the host's own
boot would have answered from. A bare install without `vendor/` loads
the framework through a minimal PSR-4 registration — the CLI keeps
the zero-dependency runtime's own promise.

**Divergence 6 — the tooling layer widens to the container.** The
report needs the inventory's typed surface, so `deptrac.yaml`'s
Tooling ruleset gains `Container` — the additive-widening pattern the
middleware lane's engine widenings established, documented in the
ruleset's own comment.

## Lessons

1. **The digest's honest scope.** A digest over the configuration
   proves the configuration — never the source. Each environment's
   trust in the artifact must follow from what its source's stability
   actually is, not from what the digest wishes it proved.
2. **Audit before caching.** "Reflection caching" as a roadmap phrase
   could have been read as caching *everywhere*; the audit found two
   per-resolution sites that mattered and a dozen per-boot sites that
   did not. Caching what is paid once per boot buys nothing but risk.
3. **The observability belongs to the test.** The census made the
   payoff pinnable (`count()` answering one, not two, after two
   resolutions) — a cache without an observable census is a cache
   whose reuse nobody can prove.
4. **Anonymous classes tug the tools.** php-cs-fixer and PHP_CodeSniffer
   disagree on the anonymous-class-with-arguments spelling; the repo's
   named-support-fixture pattern (`ThrowingHandler`'s lineage) resolves
   it without a skip on either side.
5. **The baseline shrinks when the consumer lands.** The inventory
   surface's deferred-consumer entries left the psalm baseline the
   moment the CLI consumed them — the deferral pattern works only when
   each entry is a promise the next lane keeps.
