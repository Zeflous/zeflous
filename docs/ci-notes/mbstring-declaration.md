# KDD — The mbstring declaration (the platform block's honesty)

The decision record for `fix(packaging): ext-mbstring is declared as the
platform block's second entry (issue #774)` — the audit's A-E3 finding
(area packaging/composer): the manifest's posture, the gate that pins it,
and why the honest answer is a declaration, not a polyfill.

## The finding

`composer.json` required `{"php": "^8.4"}` alone while `src/` carried ~330
`mb_*` call sites (`EventName`'s grammar, `CliCommandName`'s length bound,
the radix tree's labels, the env overlay's cut — the multibyte spine the
grammar layers ride). `symfony/polyfill-mbstring` entered only as a dev
transitive, so a production install never carried it: on a PHP 8.4 build
without the extension, `composer install` succeeded clean and the first
request fataled — `Call to undefined function mb_strlen()` — with the
grammar, the route compile and the env overlay all dying at once. The
failure waited for production precisely because the install-time surface
(the manifest) said nothing was needed.

The zero-dependency posture was conditional on an undeclared extension:
"zero-dep" was honest about Composer packages and silent about the
environment it actually ran on.

## The decision: declare, do not polyfill

The issue spelled three answers; the lane owns the first:

1. **Declare `ext-mbstring: "*"` in `require`** — the platform block.
   A platform requirement is not a Composer package: it moves nothing
   into `vendor/`, locks nothing, and keeps the zero-package posture
   exactly as it was. What it changes is the failure's timing: a build
   without the extension now refuses at `composer install`, on the
   operator's terminal, instead of fating the first request in
   production. The refusal you can read is worth more than the silence
   you cannot.
2. *Ship a runtime polyfill* — rejected: it would guard the zero-dep
   posture in name while carrying a vendored implementation's weight,
   its coverage debt (every polyfilled site a mutation equivalent
   zone) and its divergence risk against the extension it shadows.
3. *Rewrite the hot `mb_*` sites byte-based* — rejected: the multibyte
   grammar is the point of those sites (UTF-8 boundaries, length
   bounds over characters, not bytes); rewriting them to byte
   arithmetic is the most expensive way to be wrong.

## The invariant re-spelling (the gate moved in the same diff)

Rule 16's invariant is now stated over the pair it always owned, in the
honest spelling:

- `composer.json` `require` equals the platform block
  `{ "php": "^8.4", "ext-mbstring": "*" }` — the environment contract;
- `composer.lock` `packages` equals `[]` — zero Composer **packages**.

`ZeroDependencyGate::EXPECTED_REQUIRE` carries the block; the gate's
messages spell it; the corpus (`ZeroDependencyScopeCorpusTest`) moved in
the same diff exactly the way its own doctrine demands — the widening's
refusal arm (php alone, the extension undeclared — the audit's posture)
entered the decision matrix in the same diff, the fence JSONs ride the
block, and the odometer counts the family at seven decision cases. The
narrowing back to undeclared is now a red gate, not a silent drift.

## What the declaration deliberately does not carry

No runtime extension guard: the manifest is the single declaration
surface (a boot-time `extension_loaded` check would duplicate what
Composer's platform check already answers at install — the second
opinion is the second maintenance surface). No `suggest` entry:
mbstring is required, and suggestions are for optional capability. The
`zero-deps` script description re-spells the invariant so the gate's
own words teach the block.
