# The Error Family's Toggle Grammar — KDD

**Scope:** the audit's boolean env-overlay finding (#798) answered for the
whole error family: the six `is_bool` gates the family's four construction
seams carried (`ErrorMappingFactory`, `ProcessErrorBoundaryFactory`,
`DebugToolbarFactory`, `ErrorReplayWiring` -- the `http.error_handling
debug`, `observability.metrics enabled`, `http.error_handling
process_boundary` and `http.error_handling replay_enabled` knobs) demanded a
native bool while every other config-driven family folds the environment
overlay's string spellings through the strict toggle seam -- so the
spellings the observability config's own docblock advertises for the
neighbouring knobs (`APP_HTTP__ERROR_HANDLING__DEBUG=true`, the most
natural spelling a containerized deployment writes) halted the boot with a
type refusal naming neither the vocabulary nor the alternative. All six
gates now read through `StrictToggles::read()` with the knob named in the
family-qualified spelling the seam's refusals carry, branch
`fix/issue-798-toggle-grammar`.

**Verified locally before the PR:** phpunit full battery green (7634 tests
/ 18819 assertions, the four touched suites and the whole tree) ·
php-cs-fixer full-tree check 0 of 2210 files fixable · line-length and
sorted-use rules verified against the touched files · deptrac posture
unchanged (the `Middleware += Http` widening the fold rides is the ruleset's
existing allowance, `SessionMiddleware`'s own precedent) · the scoped
infection round rides CI (the static analysis battery's local runner is not
part of this sandbox).

## The map

| Unit | Question it owns |
|---|---|
| `ErrorMappingFactory::fromConfig()` | the error boundary's debug and metrics knobs, read through the strict toggle seam with the knob named (`http.error_handling debug`, `observability.metrics enabled`) |
| `ProcessErrorBoundaryFactory::fromConfig()` | the process boundary's own twin reads of the same two knobs -- one knob, one answer, whichever boundary's seam asks |
| `ProcessErrorBoundaryFactory::wantsInstallation()` | the installation knob's single validation point (`http.error_handling process_boundary`), the toggle fold replacing the private `is_bool` refusal |
| `DebugToolbarFactory::fromConfig()` | the toolbar's debug read -- the third seam the one knob feeds, folding identically |
| `ErrorReplayWiring::store()` | the replay lane's own toggle (`http.error_handling replay_enabled`), folded where the section's other keys keep their typed refusals |

## KDD-ET1 — one knob, one grammar, whichever seam reads it

The audit's evidence was one factory demanding native bool for
`observability.metrics enabled` while `MetricsPolicy` reads the SAME knob
through the strict toggle seam one lane over -- one knob answering two
grammars, the boot's answer depending on which seam asks first. The fix
is not a third grammar: every boolean read the error family's construction
seams perform now routes through `StrictToggles::read()`, so the knob's
answer is a property of the knob, not of the reader. This is the seam's
own doctrine stated at its extraction (`StrictToggles` docblock: "every
later family reads its toggles through the same seam instead of
re-declaring the vocabulary") -- the error family was the family that
had not, and the audit priced it.

## KDD-ET2 — the refusal changes family, and that is the honest direction

The retired gates threw `ConfigException`; the toggle seam's refusal is an
`HttpException` (the layer root the seam lives under). The fold does not
wrap the seam's refusal back into the old family, for three reasons: (1)
every one of the seam's twenty-plus existing consumers lets the
`HttpException` propagate -- the observability, security and session
families' policies all halt the boot through it, so wrapping here would
make the error family the one family whose nonsense spelling answers a
different exception root; (2) no catch-site distinguishes the two roots on
these seams (the boot halts on both; `ConfigCache`'s catch is the cache
read lane's own, untouched); (3) the messages the refusals carry are the
family vocabulary's own ("The {$knob} knob must be a boolean or one of the
boolean string spellings (true/false, on/off, yes/no, 1/0), {$type}
given."), which is strictly more remediation than the retired gate's
"must be a boolean" -- the operator learns both the spellings accepted
and the type they wrote. The knob NAME inside the refusal changes from
the dotted config address (`http.error_handling.debug`) to the
family-qualified spelling the seam's convention carries
(`http.error_handling debug`), matching every existing consumer's
spelling (`observability.metrics enabled` is character-identical to
`MetricsPolicy`'s) -- one more grammar the fold retires.

## KDD-ET3 — the tests pin the fold, not the old refusal

The retired gates had pins of their own -- tests asserting that
`debug => 'true'` REFUSED the boot, codifying exactly the defect the audit
filed (the string spelling is the environment overlay's own shape:
`EnvSource` passes values through as the environment carries them, so an
`APP_HTTP__ERROR_HANDLING__DEBUG=true` deployment is a `'true'` string
arriving at the frozen repository). Those pins flip shape: the nonsense
spelling (`'maybe'`, the `'ture'` mistype) still fails closed with the
seam's own message, and the overlay spellings now assert the ARMED
posture -- `wantsInstallation()` answering true for `'1'/'true'/'on'/'yes'`
and false for `'0'/'false'/'off'/'no'/''`, the replay lane writing its
snapshot for a `'on'` overlay (the lane's whole observable), and the boot
seam's installation ask observed through the rebind guard (a
`process_boundary => 'on'` boot asks for the boundary exactly as a native
`true` does -- the guard only fires when the knob answered true, so the
rebind refusal is the installation ask's own proof). The overlay test at
the boot seam (`ApplicationBootstrapperTest`) is the issue's exact
repro spelled as the assertion the repro wanted.

## KDD-ET4 — the fold stops at the layer boundary, and says so

`DatabaseQueryLogKnobs` carries the same shape (`database.query_log
enabled must be a boolean`) and is deliberately NOT folded: the Database
layer's deptrac ruleset may depend on nothing but the exception root, and
the toggle seam lives in the Http layer -- folding would either widen the
layer graph for a spelling list or wrap the seam behind a
cross-layer call, both heavier trades than the knob's own posture. The
named precedent is the container lane's own `AotToggles`, which
re-declares the vocabulary behind the same isolation argument (the KDD
the docblock cites); the database knob's fold, if it ever comes, is that
duplicate-vocabulary trade, its own slice with its own decision record.
The audit's umbrella issue already carries the layer-graph fact; this
slice records the boundary so the question does not recur as an
oversight.
