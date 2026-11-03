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
`feat/view-engine-port`. The `views` configuration wiring family
and the future Twig adapter are the lane's named follow-up slices,
deliberately not mixed into this one.

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

## The named follow-ups

- The `views` configuration wiring family (the composition seam that
  constructs the engine from configuration, `FrameworkDefaults`' own
  `views` knob) -- the lane's own next slice, the Database lane's
  own port-then-wiring order; the psalm baseline carries the
  unwired engine's three pins until that fold retires them.
- The Twig optional adapter over `class_exists` (KDD-VW1's recorded
  direction) once the host side is ready to carry it.
