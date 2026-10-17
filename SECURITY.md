# Security Policy

How to report a vulnerability in ZEF, and what happens after you do.

## Supported versions

| Version | Supported |
| ------- | --------- |
| `main` (pre-release development) | Yes |
| tagged releases | none published yet |

ZEF has not tagged a release. Every commit on `main` is the supported
version — report against the exact commit you run (`git rev-parse HEAD`),
not against a version number.

## Reporting a vulnerability

**Do not open a public issue, pull request, or discussion for anything you
believe is a security defect.** Public reports give attackers the timeline
for free.

- **Preferred:** GitHub's private vulnerability reporting — the *Report a
  vulnerability* button on the repository's
  [Security tab](https://github.com/Zeflous/zeflous/security). The report,
  the discussion, and any advisory stay private until coordinated release.
- **Fallback:** email the maintainer declared in `composer.json`
  (`mbetixz93@gmail.com`), with the subject prefixed `[security]`.

Include, where you can:

1. the commit SHA the defect was observed on;
2. the class, method, or configuration entry involved;
3. a reproduction — request shape, payload, configuration values — reduced
   to the minimum that triggers it;
4. your assessment of impact and the conditions required to exploit it.

Well-formed reports get better fixes. Partial reports are still welcome —
say what you know.

## Scope

In scope:

- `src/Zef/Framework/` and `src/Contracts/` — the runtime framework and the
  in-repo PSR shims;
- `app/configs/` — the sample configuration shipped with the repository;
- the CI pipeline and the pinned dev toolchain as a supply-chain surface
  (workflow definitions, `composer.lock` dev pins, SBOM/attestation steps).

Out of scope, with the way-out:

- **Dev-only dependency CVEs.** ZEF ships zero runtime Composer dependencies
  (`CONTRIBUTING.md`, rule 1): `composer.json` `require` is
  `{ "php": "^8.4" }` and production code never loads `vendor/`. A CVE in a
  dev tool matters to the build, not to a deployed framework — report it as
  a regular issue, unless the defect leaks into `src/`, which would be a
  rule violation and very much in scope.
- **Runtime-host defects.** RoadRunner, Swoole, PHP engine, or extension
  bugs belong upstream; report them there. We track them and mitigate what
  our integration surface exposes.
- **Resource exhaustion inside documented budgets.** Rate limits,
  request-body budgets, correlation budgets and their refusal behaviour are
  tunable configuration with intended semantics (`docs/` and
  `ROADMAP-STATUS.md` carry the pointers). Exhausting a budget you
  configured is working software, not a vulnerability — the budget
  arithmetic being wrong would be.

## What happens next

- **Acknowledgement target: 7 days** from the report.
- Assessment on the reported commit; where confirmed, the fix is developed
  on a private branch and the reporter stays in the conversation.
- **Coordinated disclosure target: 90 days** from acknowledgement — sooner
  where the fix is straightforward. The fix lands together with an advisory
  (GitHub Security Advisory, CVE requested where warranted), crediting the
  reporter unless they prefer anonymity.
- Details of an unpatched defect stay private until the fix releases.

## Posture context, for reporters

The findings a report produces will be judged against the gates the
repository already runs every day: Progpilot static security analysis,
SonarCloud, strict `composer audit`, GitHub dependency review, CycloneDX
SBOM with keyless attestation, and the PhpCodeArcheology SecuritySmell gate
— `CONTRIBUTING.md` references them all. Gates catch classes, humans catch
cases: a finding that duplicates a gate's blind spot is still wanted.
