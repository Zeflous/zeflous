# Kernel/Logging Audit — KDD

**Scope:** issue #802's two minor findings -- the logging lane's
mis-spelled byte budget and the kernel's defaults-only pipeline
posture -- inspected against `main` at `c3badaf4` (2026-11-20), the
closing rounds the `#768-#789` audit wave's leftovers named.

## KDD-KL1 — the sanitizer's budget is a byte budget (finding 1)

`LogContextSanitizer::MESSAGE_BOUND` declared itself the string value's
BYTE budget while the clamp spelled `mb_substr` -- a CHARACTERS cut. A
4096-character multibyte string rode through at up to three times the
documented budget, and the lane's own formatter
(`JsonLineFormatter::format()`) already spelled the byte-true clamp
(`mb_strcut`) for its message -- two halves of one sanitisation
posture answering with two different bounds.

The fix takes the formatter's own spelling: `mb_strcut($value, 0,
self::MESSAGE_BOUND)`. The battery pins the byte truth: the 3000-'é'
string (6000 bytes, 3000 characters -- under the old character bound,
twice the new byte one) now cuts at exactly 2048 characters, the
4096-byte boundary landing on a character edge; the exactly-at-budget
twin (2048 'é' = 4096 bytes) passes whole -- a bound, never a shave;
and the ASCII repro keeps its 4096. The hand-applied battery verified
the kills: the reverted spelling (the original defect), the bound
decrement, and the start offset each fail their own pin.

## KDD-KL2 — the defaults-only boot keeps the error boundary (finding 2)

The error boundary (the RFC 9457 mapping) existed only as a SERVICE
registration; the pipeline's `middleware.stack` list lived solely in
the host-deletable `app/configs/middleware.php`, so a defaults-only
boot -- an empty application directory, or a host that deleted one
file -- composed an empty pipeline: no error mapping, no problem
document, a silent posture loss no boot surface announced.

The fix ships the pipeline's own minimum posture in the default
profile: `middleware.stack` carries the error boundary alone
(`http.errors`, priority 1000 -- the shipped application profile's own
outermost placement). The layering's list semantics do the rest: a
host's `middleware.stack` REPLACES the default wholesale
(`ArrayMerger`'s list rule), so the host's explicit choice stays
exactly the winner it always was -- including the deliberate empty
stack, which now must be spelled to be had. The battery pins all
three postures: the defaults-only boot answers a failing handler with
the 500 problem document (the detail never leaking), the security
fixture's own stack replaces the default with no residue appending,
and the deliberate-empty fixture's pipeline runs nothing at all.

## The round's own gate lessons

None worth the note: the local battery ran green on the first pass
(PHPStan, Psalm, the fixer, the full suite), the archeology findings
at zero for both touched files, and the mutant battery's six kills
(each finding's own seams) verified before the branch moved.

**Amended -- the gate lesson the round earned after all.** The first
CI pass failed the mutation lane with an exit-143 the local battery
could never see: the failing-handler fold answers through the record
writer's SAPI fallback (`error_log()`, STDERR on the CLI -- no log
address the defaults-only boot carries), and Infection's initial
suite run STOPS on the first STDERR byte it sees -- its own doctrine
("a test process that writes an error stream is a failing one"),
the whole battery dying as a red suite for one expected record. The
boot battery routes the fallback into a file for its own duration
(`ini_set('error_log', ...)`, the writer's own direct battery's own
spelling) -- the record's content stays pinned where it always was,
the boot battery pinning the pipeline's response alone.

**The second gate round -- the mutation lane's own two escapes.** The
initial suite now green, the lane answered with two escaped mutants,
each killed by its own pin:

- The sanitizer's `mb_strcut` swapped for a plain byte `substr` --
  invisible to the two-byte 'é' repro, whose 4096-byte cut lands on a
  character edge BY ARITHMETIC (2048 two-byte characters exactly).
  The three-byte '€' repro pins what the clamp actually promises: the
  snap to the character edge (1400 '€' = 4200 bytes cuts at 1365 '€',
  4095 bytes -- the last complete character inside the budget), the
  broken half-character tail a plain substr would ship never
  surviving the pin.
- The container value judgment's `default => null` arm removed -- the
  anything-goes verdict for every builtin spelling outside the closed
  scalar set (array, iterable, mixed) never pinned by a scalar
  hand-off. The array-typed `#[Value]` parameter over a raw string
  pins it: the scalar materialises untouched, no refusal, no crash --
  the arm's removal answering an UnhandledMatchError the refusal
  family never promises.
