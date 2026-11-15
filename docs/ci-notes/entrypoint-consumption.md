# KDD — The entrypoint's consumption posture (the autoload's dependency answer)

The decision record for `fix(entrypoint): the autoload answers the
dependency consumption (issue #775)` — the audit's A-E4 finding (area
packaging/CLI): the entrypoint's own autoloading seam, the `bin` the
manifest never registered, and why the honest answer registers the
fallback unconditionally instead of moving the tooling classes into the
runtime tree.

## The finding

`bin/zef` imports twelve `Zef\Test\Support\Tooling` classes — every
verb's whole answer lives there — but the `Zef\Test\` prefix maps only
under `autoload-dev`, which never runs for an install that consumes the
package as a dependency. The entrypoint's vendor-present arm returned
after `require vendor/autoload.php` alone, so a checkout installed
`--no-dev` (the very consumption posture the manifest's library type
advertises) left every verb's classes unmapped: the first invocation
fataled with `Class "Zef\Test\Support\Tooling\CliList" not found`, a
fatal the operator can neither read nor act on. There was no `"bin"`
entry in `composer.json`, so a consuming project's `vendor/bin/zef`
never existed either.

The two consumption shapes answered incoherently: the `--no-dev`
checkout fataled outright, while a vendored package (the entrypoint at
`vendor/zeflous/zeflous/bin/zef`) worked by accident — its one-level-up
autoload lookup misses the consumer's own two-levels-up autoloader and
falls into the hand-spelled vendor-less fallback, which maps the whole
tree from the package's own root. One posture broken, one working by
luck, and the CLI's advertised host surface (`ROADMAP.md` §2.4: modules
declare commands the `bin/zef` registry answers) incoherent between
them.

## The decision: register the fallback unconditionally, never move the classes

The issue spelled two answers; the fold owns the second's spine with one
deliberate strengthening:

1. **Register `"bin": ["bin/zef"]` in `composer.json`** — the surface
   the consuming project's `vendor/bin/` proxies, so the dependency
   consumption has a verb to invoke at all.
2. *Move the CLI tooling classes into the shipped runtime tree*
   (`src/Zef/Framework/Console/Tooling/` or `bin/`) — **rejected**:
   CONTRIBUTING rule 1 pins the boundary from the other side ("dev-only
   code … consumed only by `scripts/ci/*`, `bin/zef` analysis commands
   and the test suite live under `tests/Support/Tooling/` — never under
   `src/`"), and moving the fifty-two CLI-facing classes of the
   dependency closure across it would reclassify dev tooling as runtime
   code, drag the coverage/mutation posture and the generated API
   documentation along, and re-litigate a contract the repository pinned
   deliberately. The classes stay test-support by their own doctrine.
3. *Register the fallback only for the root checkout and refuse
   external consumption* — **rejected as the weaker honest answer**: the
   distribution already ships `tests/` beside `src/` (no
   export-ignore exists), so registering the hand-spelled fallback for
   dependency installs makes the CLI *function* for the advertised
   consumption mode — the refusal would name a constraint the package
   itself does not actually carry.

The seam that changed is one: `requireFrameworkAutoloader()` no longer
returns after Composer's mapping — the hand-spelled fallback registers
unconditionally, answering the one prefix no dependency install maps
(`Zef\Test\` → the shipped `tests/` tree) while Composer's own loader
keeps answering everything it maps (the root checkout's full mapping,
the dependency install's production prefixes — the two loaders never
disagree: they map the same files). An install genuinely narrowed past
the tooling tree is refused honestly — exit 1, the operational
vocabulary, the words naming the install shape and the way out —
instead of the fatal.

## What the fold deliberately does not carry

No production autoload mapping for `Zef\Test\`: rule 1's own words
("none of them may carry test-only code") — the fallback lives in the
entrypoint alone, the one consumer the doctrine names. No `autoload-dev`
poisoning for consumers: impossible by Composer's own semantics, which
the fold simply stops relying on. No move toward classmap-authoritative
consumer autoloaders: a consumer's optimised loader that resolves every
prefix it maps stays first in the chain; the fallback answers only what
that loader cannot, which is the standard spl contract the entrypoint
rides. The refusal arm (exit 1) is what makes any *future* packaging
narrowing loud — a dist that drops `tests/` stops the CLI with words
instead of a fatal, so the narrowing cannot ride silently.

The battery (`tests/Scripts/EntrypointConsumptionTest.php`) pins the
posture matrix: the `--no-dev` checkout (the audit's own reproduction),
the vendored package (the two-levels-up autoloader the entrypoint never
finds), the narrowed distribution's operational refusal, and the
manifest's own `bin` registration — subprocess pins over synthetic
consumption trees, the entrypoint exercised exactly the way a host's
shell invokes it.
