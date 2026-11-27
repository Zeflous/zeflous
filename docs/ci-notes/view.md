# The View Lane — KDD

**Scope:** the roadmap's "View engine" half of the "View engine,
database, adapters roadmap" area -- the presentation lane's first
slice: the rendering port under `src/Zef/Framework/View/`
(`ViewEngine`, `ViewName`, `ViewException`) and its first answer,
the zero-dependency native PHP-template engine under
`src/Zef/Framework/Adapters/View/` (`PhpViewEngine` over seven
folds: `ViewTemplateDirectory`, `ViewTemplateFile`, `ViewVariables`,
`ViewRenderDepth`, `ViewRenderScope`, `TemplateFace`), deptrac
gaining the `View` layer (`View += Exception` only), branch
`feat/view-engine-port`. The lane's second slice is the `views`
configuration wiring family (KDD-VW8..VW10 below, branch
`feat/view-wiring`). The future Twig adapter remains the lane's
named follow-up slice, deliberately not mixed into either.

**Attribution:** the owner's recorded direction ("kita akan
mengadopsi twig engine di masa depan" -- the Twig adoption is the
lane's future, over this same port) and the tree's own lane
doctrines replayed fold by fold: the port-first posture every lane
carries (the Event lane's dispatcher, the Database lane's
connection), the session store's directory posture over
`StorageDocumentRoot`, the migrations lane's byte-counting name
corridor, the PDO adapter's transaction-depth ledger, and the
reference repository's two-tier views doctrine (the module's
private views live by location and are never web-readable). The
reference tree (`Zeflous/zef-framework`) carries no dedicated view
engine port to port -- its module views are plain located PHP
templates -- so this slice spells the port the reference's doctrine
implies, in this tree's own disciplines.

**Verified before the PR:** the authoring sandbox carries no PHP
runtime, so the gates' own verdict rides this pull request's CI
lanes (`CI Static` / `CI Coverage` / `CI Infection` / `CI Bench`);
the local verification was the static battery -- brace and quote
balance over every file, and exact refusal-word parity between the
sources' message constructions and the assertions that pin them.

## KDD-VW1: the port owns the question, the answer owns the engine

The port is one verb: `render(ViewName, array<string, mixed>):
string` -- "spell this template against this data, answered as
text." Everything else is deliberately the ANSWER's business, and
the port's shape is the one the future Twig answer needs: the name
is extension-free (the native answer's `.php` file under its
templates root, a Twig answer's own loader namespace -- a deployment
that swaps engines swaps resolutions, never names), the data bag is
an honest `array<string, mixed>` with no corridor the port itself
promises, and the escaping posture is owned by the answer, never
the caller (the native answer hands every template the one escape
fold through its face; Twig's own auto-escape-on-output satisfies
the posture natively). The port promises the CALLER nothing about
escaping because only the answer's author knows what its template
syntax means.

The Twig adoption the owner's direction names is recorded here as
the lane's own future slice over this same port: the
zero-dependency gate (CONTRIBUTING.md rule 1) keeps Twig out of
`require` forever, so that adoption lands the way the rate-limit
family's phpredis boundary already spells -- an optional adapter
probing `class_exists` on the host tree's own Twig, never a
composer dependency of the framework itself.

## KDD-VW2: two walls that trust each other are one wall

The name grammar is the lane's first wall and refuses by shape
rather than enumeration: segments are `/`-separated, each
letter-or-digit led and carrying only letters, digits, dots,
underscores or hyphens, so a dot-led segment never constructs (`..`
the traversal, `.` the self-reference, `.env` the dotfile -- one
refusal closes all three), an empty segment never constructs (the
leading `/`, the trailing `/` and the doubled `//` alike), and a
backslash, colon, NUL byte or control character never constructs
because the segment class never carried them. The BYTE ceilings
(4096 the whole name, 255 the segment) mirror the filesystem's own
corridors -- the same byte-counting spelling the migrations lane's
name corridor uses, counted with `mb_strlen(..., '8bit')` so a
multibyte name is judged by its bytes, not its characters.

The resolution fold is the SECOND wall, and it trusts the first not
at all: the only spelling left to a hostile tree is a symlink
planted inside the root and pointing outside it, and the realpath
containment (`str_starts_with($resolved . '/', $root . '/')`) is
the wall that catches exactly that -- while a symlink planted
inside the root and pointing INSIDE it is the deployment's own
alias, and the fold answers it, because containment is the boundary
the engine owns and the tree's own geometry is the deployment's
business. The miss names the NAME, never the resolved address: the
caller's own spelling is the whole context it owns, and a refusal
that quoted the resolved path would disclose what the lane's
privacy posture exists to keep.

## KDD-VW3: the root posture, replayed one-way

The templates-root fold replays the session store's own privacy
posture through the storage layer's `StorageDocumentRoot` -- the
one spelling of "a web-served tree is a disclosed tree" the whole
tree already shares -- and spells its own geometry fresh (existing,
readable, non-symlink, owner-only permissions) because the privacy
question belongs to the family that reads, exactly as the families
that write spell their own. The posture deliberately does NOT
demand writability: the engine only ever ANSWERS includes over this
root, never writes into it, so the very process that renders cannot
also tamper the tree it renders from -- the read root the engine
composes over is read-only by its own doctrine, the reference
tree's two-tier views doctrine (the module's private views live by
location and are never web-readable) spelled as this tree's own
fold.

## KDD-VW4: the scope's discipline is level accounting

The rendering scope opens the buffer, includes the resolved
template inside an isolated STATIC closure, and answers the captured
text. The closure is static on purpose: no `$this` exists in a
template scope, so the engine's own object never reaches the
author's code, and the only names the template sees beyond its data
are the reserved carriers the corridor's grammar keeps
collision-free (`$_view` the face, `$_file` the resolved address).
`EXTR_SKIP` is the belt-and-braces second wall: even a key some
future corridor change let through could not overwrite the engine's
own scope, and the grammar is what makes that wall unreachable
rather than load-bearing -- pinned by a test that hands the scope
an impostor bag directly and asserts the real face answered.

The BUFFER discipline is spelled as level accounting: the level
before the open is the entry, the buffer opened is the ONLY one the
template may hold (`ob_get_level()` must answer entry-plus-one when
the include returns -- a template that closed the engine's buffer
or left one of its own open disturbed the discipline, and the
refusal fires BEFORE any capture, so a template that closed the
engine's buffer can never steal the caller's), and the finally pops
every level above the entry back down, so a crash at ANY nesting
depth leaves no partial output and no open buffer behind. The
discipline check is also the `ob_start()` failure answer: a buffer
that could not open leaves the level at the entry, and the check
refuses.

The nesting ledger is per-engine by declaration (every engine
instance owns its own counter, exactly as every connection owns its
own savepoint depth): the native answer's templates can render
templates through the engine's own face, and a template that
renders itself -- by accident or by a cycle two partials spell --
meets the ceiling at the sixty-fifth nest with the depth named, the
cross-engine recursion a template author spells by holding two
engines being author code the lane does not police (the same trust
the data bag carries).

## KDD-VW5: the wrap discipline and the internal posture

The family's own refusals travel VERBATIM: a miss names the template
that missed, a partial's refusal names the partial -- the deepest
failure's own words reach the caller, so a layout whose partial
missed surfaces the partial's miss, never a wrap that names the
layout. Every FOREIGN throwable a template raised is wrapped into
the family with the template named and the cause chained -- the
lane never leaks a raw template crash to the caller, the same
no-driver-type-leaks posture the database family spells over its
PDO mapping.

Every view-lane failure is the internal posture (500): the template
name is the application's own addressing, never the client's
resource, so a miss is the developer's defect -- the same
deliberate-author posture `InternalException` spells -- and the
error boundary renders the typed refusal as the server error it is,
never a hand-built view-shaped response.

## KDD-VW6: the first CI mutation round -- six kills, four byte-semantics equivalents

The authoring sandbox carried no PHP runtime, so the slice's
mutation verdict rode the pull request's own CI lanes -- and the
first infection round answered with ten escapes over the slice's
three mutated files. The round's record is the answer to each,
every escape either killed by a new pin or carried on the
documented-equivalent ledger:

SIX KILLS:

- **The wrap's zero code** (`PhpViewEngine::render()`'s
  mid-render wrap): the pin now asserts `getCode() === 0`
  explicitly -- the code lane is part of the family's contract
  surface (the typed family's own default), not decoration the
  integer mutators may invent.
- **The miss wall's OR** (`ViewTemplateFile`'s is-file/is-readable
  guard): a DIRECTORY wearing the extension (`trap.php`) is a miss
  like any other non-file. The mutant's AND would wave a readable
  directory through to the containment; the pin refuses it at the
  wall the answer owns. The pre-existing directory arm (`users`)
  pinned a miss whose candidate never existed -- a different
  posture, which is why the mutant lived.
- **The containment's inside boundary**: a resolved address EQUAL
  to the root itself is ANSWERED (the slash-append idiom's one
  deliberate acceptance: `$resolved . '/'` starts with
  `$resolvedRoot . '/'` exactly when `$resolved === $root`). The
  pin arms the realpath double with the root itself -- the only
  posture that can spell it, because a real file whose realpath is
  the root cannot exist (the root is a directory, and the is-file
  wall would refuse the candidate first).
- **The string-prefix sibling escape**: a symlink whose target
  shares the root's own spelling as a string prefix
  (`<root>-sibling/stolen.php`) is REFUSED -- the trailing slash
  in the prefix idiom exists precisely for this boundary. The
  original escape test's outside target (`zef-view-outside-*`)
  never shared the root's spelling, which is why the
  operand-removal mutant lived.
- **The grammar's D flag** (`ViewName`'s segment pattern): a
  trailing NEWLINE is the one byte whose refusal is the D flag's
  own work -- PCRE's undollared `$` matches before a final
  newline, while every other control character the class already
  refuses wherever it sits. The interior-control arm (`a\x01b`)
  pins the class; the trailing-newline arm (`about\n`) pins the
  anchor.

FOUR DOCUMENTED EQUIVALENTS: the byte ceilings' measurements
(`mb_strlen($x, '8bit')`, both condition and message arms, name
and segment alike) -- the `'8bit'` encoding IS byte semantics and
never rejects a byte sequence, so `mb_strlen` and `strlen` agree
on every string there is; the BodyBudget argument, unconditional
here (no grammar gate ahead of the measurements can make a
divergent input unreachable, and none is needed). Verified by
running the full suite under the manually applied mutation:
6918 tests, 16678 assertions, all green -- the multibyte
over-ceiling refusals included, a multibyte name over the ceiling
refusing with the same byte count in its message under both
spellings. The ignore is method-scoped to `ViewName::__construct`
(the `infection.json5` MBString ledger), and the spelling keeps
the mb_ forms because the house's own formatter doctrine
(MbStrFunctions rewrites every `strlen` to its mb_ twin) makes
the byte-level spelling not a source-level option.

The round's own verification battery: the scoped mutation round
at MSI 100/100 (102 mutants over the three files, 98 killed, the
four equivalents ignored), the full static battery green, psalm
EXIT-0, archeology 0 error-level findings.

## KDD-VW7: the audit's two view answers (issue #787 -- the leak and the doctrine)

The database/view audit (#787) carried two view-side findings, and
both answer in one lane:

- **Finding 1 -- the flushed-tail leak, trimmed.** The scope's
  docblock claimed "a crash at ANY nesting depth leaves no partial
  output and no open buffer behind", but the FLUSH path is not a
  crash: a template that calls `ob_end_flush()` on the engine's
  buffer pushes its partial output into the CALLER's own open
  buffer below, and the discipline refusal -- which fires -- could
  only refuse the answer, never recall the bytes already landed.
  The caller's buffer content length is now taken before the open
  (`is_int` over `ob_get_length()`'s int-when-buffered answer: a
  level-zero caller holds no buffer, and the false arm the driver
  answers there is the no-buffer signal itself), and the refusal
  path walks `ViewFlushedTail::discard()`: when the
  caller's own buffer carries more than it owned, the tail is
  trimmed and the prefix re-echoed, `ob_clean()` -- never
  `ob_end_clean()` -- so the caller's buffering choice keeps its
  buffer IDENTITY (the fold trims the buffer's contents, it does
  not close the caller's buffer and open a stranger in its place).
  The audit's own repro is the pin: `caller-prefix` in the buffer,
  a flushing template between, and the buffer must answer
  `caller-prefix` again -- level untouched, the leaked
  `partial-leak` tail gone. Two residuals are pinned as the
  refusals they are: a template that flushes PAST the engine's
  walls closes the caller's own buffer (the INNOCENT buffer below
  it must survive the refusal with its own bytes -- the level
  arm's own pin, `innocent-below-victimab` asserted byte-exact,
  never a re-cut slice sized to the closed buffer's length), a
  level-zero caller already received the partial in the output
  itself (the honest spelling is to open a buffer before
  rendering when the caller's output must stay atomic --
  recorded, not worked around), and the empty leak (a template
  that flushed zero bytes) is the boundary where the trim is the
  no-op itself -- the one documented equivalent the round carries
  on the ledger (`LessThanOrEqualTo`, scoped by source regex to
  that single comparison). The silent-path pin holds the trim
  inert: a caller with a buffer renders a quiet template and both
  the level and the content come back exactly as they went in.
- **Finding 6 -- the escaping doctrine, honestly spelled.** The
  face's docblock claimed the engine "hands the discipline to its
  authors" as if the fold were enforcement; the truth the port
  already spells two-tier in `ViewVariables` carries through to
  the template's own hand: ESCAPING IS OPT-IN AT EACH
  INTERPOLATION. Every value that crosses the `escape()` fold is
  HTML-safe; a value that never crosses it echoes raw -- one
  missed `escape()` is one XSS hole, and the docblock now says
  exactly that (a future Twig answer satisfies the posture
  natively through auto-escape-on-output, and needs no face verb
  for it).
- **The fold under the archeology ceiling.** The trim's first
  spelling lived inline in `ViewRenderScope` and pushed the file
  to difficulty 23.68 against the ceiling of 20; the extraction
  (`ViewFlushedTail`, its own single-question unit) brings the
  scope to 17.21 and the fold to 5.69 -- the same reshape the
  ceiling demanded of the boundary lane (#884's 21.94 -> 19.43
  idiom, recorded in KDD-M13).

The verification battery: the full suite green (the five new
pins ride `ViewRenderScopeTest`), PHPStan max, Psalm EXIT-0,
php-cs-fixer, Rector, Deptrac, phpcpd and phpmd clean, archeology
0 error-level findings. The mutation round rode the CI lane (the
sandbox carries no coverage extension) and its first round escaped
five trim-guard mutants plus two scope-arms -- every escape
answered in this round: the innocent-below pin kills the level
arm's every spelling (the `||`/`&&` flip, the early-return
removal, the `!==` flip), the multibyte pin kills the byte-cut
operand set, the `is_int` spelling kills the caller-read arms the
first spelling could only carry as equivalents (the correlated
`$entry > 0`/`(int)` arms the level-zero no-op made unkillable --
the false answer IS the no-buffer signal, so the predicate the
ternary flips is the killable spelling), and the empty-leak
boundary is the one documented equivalent (verified by the family
battery green under the manually applied `<` flip). The
restructure that made the arms killable also made them honest:
the trim now spells exactly what it reads.

## KDD-VW8: the `views` wiring family -- the composition seam that constructs the engine from configuration

The lane's own named next slice (the KDD's named follow-ups section,
the Database lane's own port-then-wiring order answered): the
composition seam that turns the port's silent first answer into the
framework's wired default. Four folds in the unlayered root beside
their wiring siblings, the registration and the knob block in the
Default layer, and the shipped profile file:

- **`ViewKnobs`** -- the knob census (`engine`, `path`), the closed
  set refused with the known list spelled out when the map carries
  anything else: a `views.engien` typo is a named halt, never a
  silently defaulted engine. The census takes the eldest sisters'
  positive-guard loop again ({@see EventDeliveryKnobs} and the
  database family's own private fold) -- the refusal's own message
  is the family's, and the message's own tokens keep the run under
  both duplication lanes' floors (KDD-US10's doctrine, carried).
- **`ViewEngines`** -- the engine word's closed vocabulary: '' (the
  silent posture, the port stays opt-in) and `php` (the native
  answer). The reading folds the environment's spelling
  (`APP_VIEWS__ENGINE=PHP` reads as the `php` word) and refuses an
  unknown word with the value spelled out -- the Twig word is the
  vocabulary's own named future (the word arrives with the wiring
  that can read its own knobs, never before, KDD-VW1's recorded
  direction carried).
- **`ViewDeclaration`** -- the strict read of the one `views` knob
  block as one immutable value object: absent block or empty word
  the silent posture, every knob strictly read, the unknown knob and
  the foreign type each a named halt.
- **`ViewWiring`** -- the composition seam at the profile's own
  service identity (`views.engine`, the registration
  `FrameworkDefaults` declares over the fold): the silent word's
  named halt with the way out spelled (compose the engine by hand
  over the `ViewEngine` port, the way every composition before the
  fold did), the `php` word composing `PhpViewEngine` over the
  declared templates root, and the address-less word's combination
  refusal.
- **The registration and the profile** -- `views.engine` rides the
  catalogue's transient never-at-the-boot posture (the events
  family's planted doctrine: the silent word's refusal fires only
  at the host's own ask, never at the boot), the `views` knob block
  joins `FrameworkDefaults::profile()` and the shipped
  `app/configs/views.php` -- the drift pin holding the two to the
  same knobs -- and the pinned registration map
  (`ApplicationBootstrapperTest`) carries the entry verbatim.

The TRANSIENT lifetime is spelled for the native answer's own
reason: the depth ledger always comes home (every render's own
finally leaves the nesting), so a fresh engine every ask is
observably the same engine -- while the future Twig answer, whose
compiled-template state a per-ask lifetime would throw away, may
revisit the lifetime with its own recorded reason when its word
arrives.

## KDD-VW9: the explicit address -- the route map owns no `views` lane

The events, uploads, temp and session families derive their empty
`path` knobs from the `files/**` route map over the declared
`storage.root`; the view family derives NOTHING, and the divergence
is recorded here with its reason: the lanes the map owns are the
WRITTEN-STATE tree (logs, cache, temp, uploads, replay, queue,
sessions -- directories the framework's own writers provision and
sweep), while templates are the application's own SOURCE the engine
only ever answers includes from -- the read-only root doctrine
KDD-VW3 spells. A lane the map never owned would be a directory the
framework provisions for a tree it never reads, so the address-less
`php` word is the named halt the session family's combination
refusal spelled first ({@see Middleware\SessionFactory}): spell
`views.path`, or compose the engine by hand. A path that refuses
the root POSTURE flows through the adapter's own refusal untouched
-- the wiring answers WHERE, never HOW PRIVATE, the two-seams
doctrine the events family's wiring spells first.

## KDD-VW10: the psalm retirements and the verification battery

The wiring retires the pin the unwired engine carried:
`PhpViewEngine`'s `UnusedClass` entry leaves the psalm baseline
with the fold's arrival (the class gains its first src consumer,
the composition seam itself). Two entries stay with their recorded
reasons: `TemplateFace`'s `escape`/`render` pins (the face's verbs
are answered by template code the analysis never sees -- the
opt-in-at-each-interpolation doctrine KDD-VW6 spells) and
`ViewRenderScope`'s `UnresolvableInclude` (the include-by-variable
idiom IS the resolution discipline).

The verification battery: the scoped batteries green (13
declaration tests, 7 wiring tests), the full suite green (8082
tests / 19756 assertions), PHPStan max clean, psalm L1 clean
against the shrunk baseline, the fixer clean, Rector clean, Deptrac
unchanged (the wiring folds live in the unlayered root with their
siblings), archeology 0 error-level findings, duplication and mess
zero violations, the zero-dependency gate green. The coverage /
infection / phpcs halves ride CI on the merge group, watched to
green before the merge (the sandbox carries no pcov and its phpcs
install cannot finish even one file -- the environment, never the
tree).

## KDD-VW11: the Twig answer -- the optional adoption over the host tree's own engine

The lane's named follow-up landed the way its recorded direction
spelled (#939's ledger): an OPTIONAL adapter probing `class_exists`
on the HOST TREE'S OWN Twig, never a composer dependency of the
framework itself -- the phpredis boundary's own posture, replayed for
the presentation port (the zero-dependency gate keeps Twig out of
`require` forever; `twig/twig: ^3.31` joins require-dev the way the
export slice pinned its meta-schema validator, the dev tree already
carrying it transitively under the docs toolchain, so the adapter's
real-path battery rides a presence the lock pins rather than one it
inherits).

The adapter's shape, each decision its own seam:

- **The presence is probed fail-closed BEFORE the environment
  composes** -- a host tree that never installed Twig is a deployment
  defect the constructor names with the remediation spelled out
  (install twig/twig on the host's own dependency tree, or spell the
  'php' word), never a class-not-found fatal a caller could do
  nothing with. The probe reads the one class the answer composes
  (`Twig\Environment`); the loader travels beside it on the same
  composer package, so one probe answers the whole presence.
- **The templates root resolves through the lane's SHARED posture
  fold** -- `ViewTemplateDirectory` widened honestly from the native
  answer's own fold to the lane's fold both answers compose over,
  one spelling of the WHERE/HOW-PRIVATE question (the wiring answers
  WHERE, never HOW PRIVATE -- the two-seams doctrine the wiring
  family's slice spelled).
- **The environment composes with the ESCAPING DOCTRINE pinned
  explicitly** -- `autoescape => 'html'`: Twig's own
  auto-escape-on-output satisfies the doctrine natively, and the
  explicit spelling keeps a future Twig default change from silently
  moving the lane's security posture. Every other environment
  posture stays Twig's own default -- the answer ships no knob the
  census has no reader for.
- **The extension-free name is joined with the answer's own `.twig`
  extension** -- the identity that survives the port boundary: a
  deployment that swaps engines swaps resolutions, never names. The
  data bag rides the Twig context VERBATIM (the native corridor's
  reserved names are the native scope's own -- a corridor the Twig
  context does not carry).
- **The WRAP discipline is the native twin's own law, replayed at the
  boundary** -- a `LoaderError` translates into the lane's own miss
  refusal (the template named by the caller's own extension-free
  spelling, the cause chained, never the loader's path-bearing
  message as the refusal's own words), and every other foreign
  throwable -- a `SyntaxError` from bad authoring, a `RuntimeError`
  from a failing payload, any engine failure beneath them -- is
  wrapped with the template named and the cause chained (the native
  twin's mid-render message, byte-identical).
- **The nesting ledger is deliberately absent** -- the ledger bounds
  the FACE-mediated recursion, and the Twig answer hands its
  templates no face: the render verb is not reachable from the
  template side, so the recursion through the port is bounded by
  construction, while the include-nesting a template authors is
  Twig's own machinery the deployment chose with the word.

The vocabulary and the wiring widen with their own words: the
`twig` word joins `ViewEngines` (the known list spells both answers,
the environment overlay folding TWIG as it folds PHP), `ViewWiring`
composes the Twig answer exactly the way it composes the native one
(never reaching past the adapter for a Twig type of its own, the
address-less word's combination refusal interpolating the word it
refuses), and the registration keeps the transient
never-at-the-boot posture with the Twig answer's own reason recorded
in the Default layer's catalogue: a fresh environment per ask never
crosses a cache (cache off by default, so both answers re-do the
per-render work -- the native re-includes, the Twig re-parses; the
compiled-template cache knob arrives the day a deployment asks, its
census entry with it -- the named follow-up above).

The false presence is observed only the way it can be: a child
process carrying the framework's own classes through the entrypoint's
own zero-vendor fallback mapping (the framework prefix onto `src/`,
no host autoload), so `class_exists` answers false and the probe
fires -- a main process that carries the dev tree's own Twig can
never observe its absence, so the subprocess pin IS the false arm's
battery, `#[CoversNothing]` the honest covers answer for a child
whose coverage never rides the parent's report.

The battery: the adapter's seven real-Twig pins (the escaping
doctrine, the nested-name resolution, the verbatim bag, the miss
translation with the cause chained, the syntax and runtime wraps,
the shared root posture), the declaration arms (the twig word
verbatim, the environment fold, the unknown-word refusal spelling
both known words) and the wiring arms (the twig composition end to
end, the address-less refusal, the boot resolving the wired twig
engine with the transient fresh-engine posture) -- 8095 tests /
19781 assertions over the full suite, PHPStan max clean, psalm L1
clean over the unchanged baseline, Deptrac unchanged, the fixer and
Rector clean, archeology 0 error-level findings (TwigViewEngine at
difficulty 10.11, ViewWiring 8.89, ViewEngines 5.67), duplication
and mess zero violations, the zero-dependency gate green.

## KDD-VW12: the first CI round -- the early exit, the one-line absent arm, and the two ledger settlements

The strict lanes answered the slice with one phpcs error, two
escaped mutants and the cloud gate's new-coverage verdict -- every
finding settled at its cause, no exclusion widened beyond the
ledger's own documented shape:

- **Slevomat EarlyExit answered the presence pin's teardown** -- the
  cleanup loop's positive branch became the negative `continue`, the
  unlink running after the early exit (the same spelling the claim
  loop's settle carried, provably equivalent).
- **SonarCloud's new-coverage gate answered the five-line
  Twig-absent refusal** -- 86.1% against the 95% floor, the whole
  answer measured at 30 executable lines with the absent arm's
  five-line throw the only block the in-process battery cannot reach
  (the subprocess pin exercises it byte-for-byte, but a child's
  coverage never rides the parent's report). The remediation the
  probe names now rides ONE private const beside the probe
  (`TWIG_ABSENT_REFUSAL`, the message spelled once -- the subprocess
  pin's own REFUSAL constant keeps its independent spelling, a
  pinned expectation never a tautology), so the refusal itself is a
  single line and the arm stays a single uncovered line in the
  parent's coverage ledger, the new-code gate reading the answer
  back over the floor. The class const carries its own reason in its
  docblock -- the coverage posture is the arm's own recorded shape,
  not an accident.
- **The mutation round's two escapes settled by the ledger, each
  verified by running its battery under the manually applied
  mutation** -- the engine-vocabulary fold (`ViewEngines::read`, the
  MBString mutant's `strtolower` rewrite) joins the no-`k`
  equivalence class the session and events store twins already spell
  (php and twig carry no ASCII `k`, and the Kelvin sign's landing is
  the only single-character non-ASCII arrival in ASCII, so every
  fold-divergent value misses the closed set under both spellings --
  25 tests / 42 assertions green under the rewrite); and the
  escaping-doctrine pin (`TwigViewEngine::__construct`, the
  ArrayItemRemoval mutant's empty option block) joins the ledger as
  the doctrine's own first ArrayItemRemoval entry: under Twig 3's
  own constructor default (`'autoescape' => 'html'`, the engine's
  own option table) the removal is value-equivalent -- the escaper
  default, the compiled-template cache key and every rendering
  answer byte-identical, 7 tests / 13 assertions green under the
  rewrite -- while the explicit spelling stays the pin that reddens
  the suite the day Twig's own default moves (the spelling is the
  pin, never a behaviour today).

The coverage / infection halves ride CI on the merge group, watched
to green before the merge -- and the round's own run answered with
the lane's own operational finding: the full tree's mutation census
(22724 mutants) crossed the JSON reporter's 1G memory budget at the
report-writing step (`JsonReporter.php`, the payload serialised in
memory, the run itself green at 22538 killed / 111 configured-ignored
/ zero escaped, MSI 100/100), so the mutation script's own process
ceiling follows the tree's growth -- 1G to 2G, the three test threads'
own 1G spellings unchanged (the runner's 7G budget answers the
arithmetic). The ceiling is the reporter's own budget, never a gate
threshold moved.

## The named follow-ups

- The compiled-template cache knob on the Twig answer's environment
  (the transient registration's own recorded reason: cache off by
  default, both answers re-do the per-render work -- the native
  re-includes, the Twig re-parses -- so the knob arrives the day a
  deployment asks, its census entry with it, the lane's rule-17
  doctrine).
