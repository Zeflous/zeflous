# The Runner-Dependent Mutation Kills — KDD

**Scope:** the red-main repair that followed #634 — `CI Infection` failed
on the merge commit `002d784` with five escaped mutants while the very
same tree (verified: the merge commit's diff against the validated
branch head is empty) had passed every lane through the merge queue
minutes earlier. None of the five mutants belonged to the slice under
review: four lived in `src/Zef/Framework/Http/ScopedDiagnostics.php`
(the PSR-7 upload slice's #271 capture seam) and one in
`tests/Support/Tooling/WorkflowFileCollector.php` (the concurrency
audit's walk). The branch run had killed them; the main run did not —
on an identical tree. That is the definition of an environment-dependent
kill, and this note records why it is dishonest and what replaced it.
Branch: `fix/mutation-observation`.

**The diagnosis.** A mutant that is only sometimes killed is a mutant
whose killing observation is owned by something other than the suite.
For the four `ScopedDiagnostics` mutants (the severity mask's two
rewrites, the handler's `return true`, the `try/finally` restore), no
test anywhere asserted the behavior they guard: no test offered the
capture a sub-warning-grade severity, no test asserted the swallow kept
the engine's own logger silent, and no test asserted the handler came
back when the engine threw. Their occasional kills rode on runner
configuration the suite never asked for. For the workflow collector's
`ksort`, the only observing test (`testCollectsYmlAndYamlFilesSortedByName`)
asserts sortedness through a directory walk whose iteration order the
filesystem owns — on a filesystem whose readdir answers already
alphabetised, removing the sort changes nothing observable. The kill
belonged to whichever machine happened to sit under the run.

**KDD-MO1: an environment-dependent kill is not an honest kill.** A
mutation the suite kills only when the runner's configuration or the
filesystem's mood cooperates is exactly as dangerous as an escape: it
paints the MSI green today and reddens `main` tomorrow, after the merge,
where the cost of the diagnosis is highest (this incident). The honest
posture is the one this fix lands: every mutant's killer is an assertion
in the tree, not an accident of the host.

**KDD-MO2: a sort the filesystem can answer for you is unobservable
through the walk.** The collector's contract answers a map sorted by
file name, but the walk's iteration order belongs to the filesystem
(`DirectoryIterator` answers readdir). The fix is the fold the repo's
own doctrine spells — two questions, two seams: the walk asks the
filesystem which files exist; `SortedWorkflowMap::over()` asks in what
order the audit reads them back. The fold's test hands it an unsorted
map directly, so the `ksort`-removal mutant dies on every host, and the
collector's own test keeps its end-to-end assertion for the
filesystems that disagree with the sorted order.

**KDD-MO3: the three diagnostics observations, each with its own
deterministic instrument.** (a) The severity mask: a
`DeprecatingStreamWrapper` whose `stream_open()` succeeds while
`trigger_error(..., E_USER_DEPRECATED)` rides through the active handler
— the capture must answer `[resource, null]`, and both mask rewrites
(BitwiseAnd, DecrementInteger) capture the deprecation and are caught
asserting the null. The engine never emits a deprecation from `fopen()`
itself, so the wrapper is the only deterministic source of the
severity the mask must refuse. (b) The swallow: the test owns the
engine's logging destinations for its duration (`error_log` on a
temporary file, `display_errors` off, `log_errors` on, `error_reporting`
pinned) and asserts the log file stays empty across a captured warning —
the `return true` is the difference between the capture owning the
diagnostic and the engine's logger also receiving it, which is the
seam's documented reason to exist (the failure reason belongs to the
caller, not to the error log). (c) The scoped restore: the StreamOpener
twin's own probe pattern — a sentinel handler installed below the
capture, the `ThrowingStreamWrapper`'s exception propagating unchanged,
then a probe `trigger_error()` that only fires if `restore_error_handler()`
ran — pins the `finally` arm against the `UnwrapFinally` rewrite.

**KDD-MO4: the observation tests live at the seam's own file.** The
upload slice had covered `ScopedDiagnostics` only through
`UploadedFileTest`'s composition (`#[CoversClass]` attribution was
present, so the mutants were generated — the attribution lesson of
KDD-DU13 held). Attribution without the seam's own observation file
still leaves the mutants' killers as whichever composed test happens to
collide with the behavior. The dedicated `ScopedDiagnosticsTest` makes
the seam's own contract — capture warning-grade, swallow everything,
restore on throw — the primary witness, with the composition test
remaining as the integration arm.

**Verified locally before the PR:** scoped infection over the two
mutant-bearing files, 31 → 34 mutants, MSI 100/100, 0 escapes (the five
runner-dependent kills replaced by assertion-owned kills) · the five new
`ScopedDiagnostics` observations plus the `SortedWorkflowMap` fold's
three tests green · `ci:static` EXIT-0 · psalm EXIT-0 (no new baseline
entries: the fold's consumer lives in the same audited tree) · progpilot
`[]`.
## The second batch: seven runner-dependent mutants behind the changelog lane's red (2026-11-13)

PR #820 (a one-line changelog regeneration) reddened `CI Infection`
twice with seven escaped mutants on a tree whose `src/` was
byte-identical to the `main` the merge queue had validated minutes
earlier — the same class as the #634/#635 repair, two months later, in
two new shapes. The diagnosis reproduced locally: a scoped run over
`DirectoryLoader` + `UploadedMove` escapes the same seven mutants, and
the passing CI runs turn out to have been passing on noise (below).

**KDD-MO5: the upload staging name's kills were shared-/tmp
contamination, never assertions.** Six of the seven lived on
`UploadedMove::onto()`'s one line — the `.zef-tmp-` marker and the
`bin2hex(random_bytes(8))` entropy of the staging sibling. No test
pinned the sibling's NAME: the only shape-observing assertions were
`assertSame([], glob(dirname($target) . '/*.zef-tmp-*'))` over the
SHARED temporary directory. That glob observes every other process's
leftovers, and under `--threads=3` mutation testing the "other
processes" are the lane's own concurrent mutants — a mutant that breaks
the cleanup leaves a sibling behind, and every later mutant whose
covering set includes such a glob then dies to a file it never created.
The green runs were thread-scheduling luck (a leftover present when the
name mutants ran: the queue's `CI Infection` on the #823 group head,
#822's PR head); the red runs were a clean schedule (#820's two heads,
the local reproduction). The repair is the observation the marker and
the entropy deserve: the cleanup's `unlink` refusal arm
(`ReplaySnapshotDoubleState::$unlinkAnswers = [false]`, the boundary's
own double) makes the staging sibling SURVIVE its own cleanup, and the
survivor's name is pinned by an anchored pattern — the target, the
`.zef-tmp-` marker, exactly sixteen lowercase hex characters — inside
the test's own directory, where no other process's leftovers can answer
for it; the survivor's payload is pinned with it. The existing
whole-payload glob is narrowed to the target's own sibling pattern so
it stops observing the shared directory at all — and the narrowing
immediately exposed the next unowned mutant (KDD-MO7). Verified
contamination-proof directly: the scoped run holds MSI 100/100 with
decoy `.zef-tmp-*` files planted in the shared directory.

**KDD-MO6: the dotfile skip's kill was the host's readdir order, and
the sorted scan it needed brought its own ledger.** `DirectoryLoader`'s
hidden-entry `continue` was killed only when the filesystem's iteration
happened to answer the dotfile before a loadable child (the kill test's
own comment claimed creation order made it independent — it does not;
readdir order is the host's). The class docblock already promised a
"deliberately narrow and deterministic" scan; the scan now sorts the
entry names itself (`SortedDirectoryEntries::inNameOrder()`, a pure
fold over the collected list so its own contract is testable without a
filesystem), a hidden entry always sorts before the loadable children
behind it, and the skip test's skippable entries were renamed so they
sort before the loadable (`a-notes.txt`, not `notes.txt`) — the kill is
now the walk's own order, on every host. Two ledger items the sorted
scan created and closed: the emission `ksort()` became redundant with a
sorted walk (its removal would have been equivalent), so the fixture
pair became one whose NAME order differs from its KEY order
(`x-y.php` delegates before `x.php`, but key `x` sorts before key
`x-y` — the ksort is the only thing standing between the two); and the
walk's fail-closed delegation order is now alphabetical, so the
allowlist tests name the first sorted offender.

**KDD-MO7: the success flag's false arm and the flush before the
close.** With the shared-/tmp noise gone, two more of `onto()`'s
mutants lost their only (noise) kills. The `$success = true` flip runs
the failure cleanup after a successful rename — the cleanup itself is
shielded (the staging sibling is already renamed away, `is_file()`
answers false), so the restore's `seek()` on the payload stream is the
only witness: `CursoredStream` now counts the seeks it answers, and a
successful move must answer exactly zero. The `fflush($dest)` removal
is the honest equivalent of the batch: the staging stream is a plain
engine file whose next line is `fclose()` — the close's own contract
flushes the user-space buffer, so nothing between the two calls can
observe the flush. Verified against the engine by running the complete
suite under the manually applied mutation (7516 tests pass), and
recorded as the `FunctionCallRemoval` lane's second entry — a
source-regex ignore scoped to that single line, the neighbouring calls
of `onto()` staying under the mutator with their own kills.

**Verified locally before the PR:** scoped infection over the three
mutant-bearing files (`DirectoryLoader` + `SortedDirectoryEntries` +
`UploadedMove`, 60 mutants) MSI 100/100 on a cleaned `/tmp`, and still
100/100 with decoy `.zef-tmp-*` files planted in the shared directory
(the batch's own mechanism, observed directly) · the three new
observation tests plus the narrowed glob and the renamed fixtures green
· `ci:static` EXIT-0 · psalm EXIT-0 · progpilot `[]`.
