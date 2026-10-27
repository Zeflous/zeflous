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
