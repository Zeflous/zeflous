# The QA Gates (phpcpd + phpmd) — KDD

**Scope:** the two local quality gates that close the gap the SonarCloud
trial expiry opened — a copy-paste detector (`systemsdk/phpcpd` v9.2.0,
the maintained fork of the abandoned `sebastian/phpcpd`) and a mess
detector (`phpmd/phpmd` 3.0.0), both provisioned through `require-dev`
(the zero-dependency invariant only fences the production `require` and
`packages`, never the tool channels), both owned by the tooling-gate
engine (`tests/Support/Tooling/DuplicationGate` + `DuplicationReport`,
`MessGate` + `MessReport`, two `GateRunner` arms, `composer
qa:duplication` / `qa:mess`, both folded into `composer ci:static`), the
opted-in ruleset `phpmd.xml`, and the one production fold the copy-paste
detector demanded on its first run (`SameSiteVocabulary`, paying down
the `CsrfSameSite`/`SessionSameSite` twin classes), `feat/qa-gates`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5504 tests /
13464 assertions; smoke 2760B over 20000 iterations; concurrency 23
workflows; audit 0 advisories; the two new gates green inside the same
battery) · psalm EXIT-0 at 99.9308% inference with five surgical
baseline lines (the SimpleXML stub disagreement, KDD-QA7) · progpilot
`[]` · scoped infection over the six touched files, 221 mutants, MSI
100/100, 0 escapes · archeology 0 error-level findings (the fold is a
net deletion: two ~60-line vocabulary classes replaced by one ~75-line
shared fold).

## The map

| Unit | Question it owns |
|---|---|
| `DuplicationReport` | the parsed phpcpd PMD payload: clone pairs, spans, line counts, root-relativised paths |
| `DuplicationGate` | the verdict: any clone pair in `src/` fails, naming both spans |
| `MessReport` | the parsed phpmd JSON payload: every violation narrowed onto file/line/rule/description |
| `MessGate` | the verdict: any violation under the opted-in ruleset fails, grouped by rule with every location |
| `GateRunner::duplication()` | the detector's own run: command shape, exit-code fence, payload/exit agreement |
| `GateRunner::mess()` | the detector's own run: command shape, exit-code fence, payload/exit agreement |
| `phpmd.xml` | the ruleset: per-rule opt-in, calibrated green on main @ `0c5a634` |

## KDD-QA1 — why the gates are local, with the contribution measured

The SonarCloud trial expiry prompted the question this slice answers:
which of Sonar's signals actually block a merge here? Two independent
measurements, taken on the live repository, agree on the answer:

* **The required-check set never included Sonar after the 2026-10-23
  ruleset consolidation (#568):** the live `main` ruleset (24654862)
  requires exactly `CI Strict`, `PR Validator` and `PhpCodeArcheology
  SARIF`; `SonarCloud analysis` reports per PR as an advisory context
  only, and the `CI Strict` aggregator folds four lanes (`CI Static`,
  `CI Coverage`, `CI Infection`, `CI Bench`) with no Sonar input. A red
  Sonar check has not been able to block a merge since that
  consolidation.
* **The first-push reds on the two most recent non-changelog slices
  were both local-gate mirrors:** #610 (the debugging utilities) went
  red on `CI Static` — a phpcs line-length fold — and #613 (the event
  dispatcher) on `CI Infection` — a mutation-ledger escape — while
  `SonarCloud analysis` was green at first push on both. The stack that
  actually blocks is the local one.

The conclusion is not "remove Sonar" (it still decorates, still runs
green as of these PRs, and its removal is a one-file follow-up whenever
the trial actually bites — KDD-QA8) but "own the duplication and mess
signals locally, in the battery that demonstrably blocks". That is what
this slice does: both gates live inside `composer ci:static`, so the
`CI Static` lane reddens on them, the `CI Strict` aggregator inherits
the red, and the merge queue refuses — the same path every other local
gate already travels. No ruleset, branch-protection or workflow-trigger
change was needed for the blocking semantics, which is why none was
made.

## KDD-QA2 — the blocking path is the composer script, not a new check

`composer ci:static` gained `@composer qa:duplication` and `@composer
qa:mess` between `rector:check` and `test`. The CI Static lane runs
`composer ci:static` verbatim (`.github/workflows/ci-static.yml`), so
the gates block through the existing aggregation chain with zero
workflow surgery: `ci:static` → the `CI Static` check → the `CI Strict`
required check → the merge queue's ALLGREEN group. Local and CI parity
is the same file. The two lanes' step description comments were amended
to name the new gates so the inventory stays honest.

## KDD-QA3 — the duplication gate and the fold it paid on arrival

The detector runs `phpcpd src --exclude src/Contracts/Psr --log-pmd
php://stdout --quiet` — the same scope Sonar's copy-paste detector
analysed (`sonar.cpd.exclusions=src/Contracts/Psr/**`: the vendored PHP
FIG shim is third-party boilerplate this repository mirrors, not code
it owns), with the PMD XML payload flowing through stdout so the gate
never trusts a stale report file (`--quiet` keeps the human summary off
the stream; the `php://stdout` address is what makes the audit-gate
process-runner shape apply unchanged).

Its calibration run against main @ `0c5a634` found exactly one clone
pair: `CsrfSameSite` 19-57 ↔ `SessionSameSite` 27-65, 38 lines, 110
tokens (0.06% of 65293 lines). The pair paid the fold this slice ships:
`SameSiteVocabulary::read(mixed $value, string $knob)` now serves both
knob surfaces with the rejection naming the surface it was asked about
(`security.csrf` / `http.session`), byte-identical to the two messages
the twin classes spelled separately. The census doctrine had deferred
this seam until a third cookie-writing family landed; the duplication
gate's own arrival answered the deferral, because two byte-identical
38-line bodies are a clone regardless of how many knob surfaces ask the
question. The two `infection.json5` MBString entries the twins carried
collapsed into one (`SameSiteVocabulary::read`), the same no-`k`
argument stated once for both surfaces.

The gate is strict by calibration, not by threshold: any clone pair
fails (phpcpd's default `--max-percentage 0.0` semantics, read from the
payload by the gate rather than trusted from the exit code alone).
Loosening it is a knob that lives in the composer script's arguments
(`--min-lines`, `--min-tokens`, `--fuzzy`, `--max-percentage`) — change
them in the same commit that documents why.

## KDD-QA4 — the mess gate's per-rule opt-in and the census it stands on

`phpmd analyze src --format=json --ruleset=phpmd.xml` with the
`--exclude='*src/Contracts/Psr/*'` pattern mirroring the same vendored
shim fence. The ruleset is **per-rule opt-in, never whole rulesets**:
eleven rules were enabled, every one of them clean against main @
`0c5a634`, so the gate landed green with zero grandfathered violations
and no baseline file. The full census under the six default rulesets
(1547 findings) was dispositioned rule by rule:

| Rule | Findings | Disposition |
|---|---|---|
| `StaticAccess` | 1153 | excluded — the static-factory construction is this architecture's idiom; folding it in would demand rewriting a thousand call sites against the grain |
| `ShortVariable` / `LongVariable` / `ShortMethodName` | 286 | excluded — phpcs + slevomat own naming, and the fold idiom (`$vo`, `$id`) is deliberate |
| `BooleanArgumentFlag` | 43 | excluded — booleans the call sites pass deliberately (strict-reading lineages) |
| `TooManyPublicMethods` | 11 | excluded — the Refusals families ARE public-method vocabularies (one refusal per case); value objects carry PSR-style public getters |
| `CouplingBetweenObjects` | 10 | excluded — orchestration classes wire the application by design; the archeology gate owns the structural ceilings |
| `ElseExpression` | 9 | excluded — honest two-arm answers in validators/transmitters; the early-exit discipline phpcs already enforces is not "no else ever" |
| `Superglobals` | 9 | excluded — the boundary classes (`RequestFactory::fromGlobals`, the document-root resolvers) are the documented, reviewed places superglobals are read |
| `CyclomaticComplexity` / `NPathComplexity` | 14 | excluded — the archeology gate's difficulty ceilings are calibrated per class; these coarse counters double-gate worse |
| `ExcessiveParameterList` / `ExcessiveMethodLength` | 6 | excluded — PSR-7 signatures and declarative census tables, both by contract |
| `NumberOfChildren` | 1 | excluded — `ZefException`'s 17 typed children ARE the error-family doctrine |
| `UnusedFormalParameter` | 3 | excluded — signature contracts (`set_error_handler` callbacks, named constructors); psalm's `findUnusedCode` owns the real leaks |
| `UndefinedVariable` | 2 | excluded — the `apcu_fetch($key, $success)` by-ref out-param false-positive class; phpstan and psalm both own the genuine bug class |
| `CountInLoopExpression`, `DepthOfInheritance`, `DevelopmentCodeFragment`, `EmptyCatchBlock`, `EvalExpression`, `ExitExpression`, `GotoStatement`, `UnusedLocalVariable`, `UnusedPrivateField`, `UnusedPrivateMethod`, `UnusedSuppression` | 0 | **included** — bug-class and debug-code guards the other lanes do not spell: eval/goto/exit, `var_dump`/`print_r`/`error_log`/TODO fragments, empty catch blocks, count() in loop conditions, deep hierarchies, unused locals/fields/methods, and suppressions that suppress nothing |

The included column is the growth surface: each row is a guard that
costs nothing today and reddens honestly the day someone lands the code
it guards against.

## KDD-QA5 — adding and tuning rules (the flexible-rules contract)

* **Enable a stock rule:** one `<rule ref="rulesets/<set>.xml/<Rule>"/>`
  line in `phpmd.xml`, after making main green for it (fix the findings
  it reports, or exclude the rule with the reason written here — never
  silently). A new phpmd release adding rules to a bundled ruleset
  cannot redden this gate: nothing is included by wildcard.
* **Tune a threshold:** nest
  `<properties><property name="..." value="..."/></properties>` inside
  the ref (every bundled rule documents its property names in the
  ruleset XML under `vendor/phpmd/phpmd/rulesets/`).
* **Write a rule of your own:** a class extending `PHPMD\AbstractRule`
  implementing `apply(\PHPMD\Node\Node $node): void`, registered in the
  ruleset by FQCN (`<rule name="Zef\Test\Support\Tooling\MyRule"
  message="..." externalInfoUrl="..."/>`). The class autoloads through
  this repository's own composer autoloader, so `tests/Support/Tooling`
  is a working home for it — the gate then covers it like any other
  Tooling unit (the `class` attribute is how stock rules are registered
  too; copy a `<rule name=... message=...>` block from any bundled
  ruleset for the shape).
* **The detector side:** the phpcpd arguments in the `GateRunner` arm
  are the duplication gate's knobs (`--min-lines`, `--min-tokens`,
  `--fuzzy`, `--max-percentage`, `--exclude`), changed in the same
  commit that documents why in KDD-QA3's terms.

## KDD-QA6 — the runner's fail-closed contract

Both arms follow the `DependencyAudit` shape — the gate runs the tool
itself through the process runner, reads the payload from stdout, and
refuses to trust a run it cannot read completely:

* an exit code outside the tool's clean/violating pair (phpcpd: 0/1,
  phpmd: 0/2 — anything else is a crash, a usage error, or a missing
  binary) throws before any verdict is read;
* a payload that does not parse (garbage XML, invalid JSON, a malformed
  entry anywhere in the tree — a missing file path, a non-integer line,
  a non-string rule) throws rather than silently dropping findings;
* the exit code and the parsed payload must **agree** — a tool that
  reports clones while exiting clean (or violations while exiting
  clean, or the reverse in either direction) is a tool whose verdict
  nobody can read, and the arm refuses to pick the greener half.

The cross-check is the same doctrine the merge-gate audits applied to
the aggregator: never trust one signal where two must agree, and fail
loudly when they do not.

## KDD-QA7 — the SimpleXML stub disagreement, paid in the baseline

phpstan and psalm disagree about `SimpleXMLElement` property access:
phpstan's stub types `$document->duplication` as
`SimpleXMLElement|null` (so a bare `foreach` is a `nonIterable` error),
while psalm's stub resolves the same fetch as never-null (so the
`?? []` appeasement phpstan needs is a `RedundantCondition` psalm
flags). The runtime, measured directly, sides with psalm: a property
fetch for a missing child answers an **empty** `SimpleXMLElement`,
never `null` — so the `?? []` operands are value-equivalent by
construction on every payload the parser can ever see. The spelling
keeps the coalesce (phpstan is a required gate) and records psalm's
four objections in `psalm-baseline.xml` as five surgical lines, the
frozen-baseline doctrine's established answer for exactly this class
of inter-analyser disagreement. The reachable guards — the
`simplexml_load_string` failure, the `duplication` element without
file spans, the libxml error-state restoration — are all carried by
reachable tests, and the scoped infection run confirms none of them
escaped.

## KDD-QA8 — the tools' own defects, paid around

* `systemsdk/phpcpd` v9.2.0's JSON logger divides by zero on the
  zero-clone case (`CodeCloneMap::averageSize()` on an empty clone
  map, exit 255) — the PMD XML logger handles the clean case
  correctly, so the gate reads PMD. The fork is otherwise the healthy
  successor of the abandoned `sebastian/phpcpd`; the bug is worth an
  upstream issue when someone wants the JSON shape back.
* phpmd 3.0's `--ruleset` accepts one ruleset per flag (comma-joined
  names fatal with `RuleSetNotFoundException`) and its ruleset parser
  rejects `--` inside XML comments — both learned the loud way, both
  now encoded in this file so the next reader does not rediscover
  them.

## SonarCloud's standing after this slice

`sonarcloud.yml`, `sonar-project.properties` and
`docs/security/sonarcloud.md` are untouched: SonarCloud still analyses,
decorates, and runs green as of #610/#613. What changed is the
hierarchy of record: the duplication and mess signals it used to be
the only source of are now owned locally by gates that actually block,
and its role here is decoration plus the server-side quality gate it
still holds on its own branch analysis. When the trial expiry
eventually reddens it, the removal is deliberately small — retire the
one workflow, delete the properties file, amend the one docs page —
and nothing about merge blocking changes, because (KDD-QA1) it was
never in the blocking set. That follow-up is a user decision, recorded
here so it is a one-slice job when it comes.

## The standing after the retirement and the same-day return (2026-10-26)

The one-slice removal the section above recorded DID come — as PR #622
(merged 2026-10-26T09:24:47Z): the lane, the properties file, the changelog
dispatch wires, the watchdog entry, the coverage bridge's dead weight and
the ruleset's `code_quality` rule all went, and `docs/security/sonarcloud.md`
became the retirement record. Hours later the owner provisioned a NEW
SonarCloud account (organization `zefl`, project `zefl_zeflous`, quality
gate "ZEF") and asked for the lane back — the cloud analyzer's contribution
to code quality is valued, on top of the local stack rather than instead of
it. The resurrection (the same day) rebuilt the lane's proven machinery and
deliberately kept the retirement's structural lessons:

- the lane is **advisory** — the `code_quality` ruleset rule is NOT
  re-added (a lapsed analyzer must never deadlock the queue; the required
  set stays `CI Strict`, `PR Validator`, `PhpCodeArcheology SARIF`);
- no `merge_group` trigger and no queue-path branches — the watchdog's
  post-merge dispatch keeps `main` analysed;
- no changelog dispatch wires — they existed to feed the `code_quality`
  rule, which stays gone;
- the coverage bridge is LEANER than the retired shape: it consumes the
  gate's own clover + junit artifact, no second suite run, no composer
  indirections.

KDD-QA1's hierarchy of record is therefore unchanged by the return: the
duplication and mess verdicts that block are the LOCAL gates; SonarCloud
adds the cloud analyzer's decoration, trending and server-side gate on top.
The record of both turns lives in `docs/security/sonarcloud.md`.
