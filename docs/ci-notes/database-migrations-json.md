# Database migrations JSON answers — the design record

**Status:** a design record, landed before the code. The implementation
arrives in this slice's own pull request over this record; the slice is
the open ledger's smallest named follow-up ("--json on the mutation
verbs") and has no dependencies — it lands any time the lane wants a
small, clean slice.

## The slice in one paragraph

The machine-readable answers on the migrations MUTATION verbs:
`migrations:run` and `migrations:rollback` still answer text only
(`MigrationsRunText`), while `migrations:status` already answers
`--json` (`MigrationsStatusJson`) — the renderer-pair discipline the
CLI lanes established (`route:list --json`, `storage:map --json`,
the `CliList*` / `HeaderAudit*` / `MigrationsStatus*` pairs in the
test-support tooling lane). This slice closes the pair: the mutation
verbs gain their JSON twins, and an operator's script reads the same
facts the human does.

## The question it owns

"Script, what just happened?" — the run's applied versions and the
rollback's rolled-back versions, with their declared names, in the
shape a pipeline consumes: the exact facts the text renderers already
carry, spelled as JSON. The answer's SHAPE is a flag; the verdict
never is — the exit-code table is the contract both spellings share.

## The units sketch

| Unit | Question it owns |
|---|---|
| the run JSON twin (working name `MigrationsRunJson`) | the applied versions with their declared names, the verb's own word — over the same encoder the status JSON rides |
| the rollback JSON twin (working name `MigrationsRollbackJson`) | the rolled-back versions with their declared names, the same shape discipline |
| the flag wiring | `--json` on the two mutation verbs, the status verb's own flag grammar — unknown flags refuse with usage (exit 2), the CLI boundary unchanged |

## The decisions to carry

- **Exit codes UNCHANGED.** The JSON is the payload, never the
  verdict: a run that applied three migrations answers the same exit
  code in both spellings, and a script keying on the code reads the
  same truth a human does (the `storage:map` discipline: the shape is
  a flag, the verdict is not).
- **The payload is the text renderer's own facts.** No enrichment, no
  second bookkeeping: the versions, the names, the verb's word — the
  twins render the SAME answer object the text renderers render, or
  they drift, and drift at this seam is a silent contract break for
  every script built on it.
- **The encoder is the shared one.** The status JSON's encoder rides
  again — a per-verb encoder would spell the same JSON differently,
  the exact fragmentation the renderer-pair discipline exists to
  prevent.

## What the implementation must answer

- The payload pins: run with three declared migrations answers the
  three applied versions in order; rollback with `--steps=2` answers
  exactly two; the declared names travel with the versions.
- The flag-table pins: `--json` accepted on both verbs, refused with
  usage on verbs that do not own it (the status precedent's own
  grammar), `--steps=` composition unchanged.
- The full battery, archeology under the ceiling, scoped infection
  MSI 100/100, the roadmap row at landing.

## What this slice deliberately does not carry

`--json` on verbs that have no machine-readable answer to carry (the
boundary doctrine: a flag that changes nothing is a dead flag); a
streaming/evented shape (the verbs answer once, at completion — the
progress question is a TTY question the tree's CLI doctrine already
answers with text); any schema for the JSON beyond the pins (the pins
ARE the schema — a formal schema document would be a second source of
truth the pair could drift from).
