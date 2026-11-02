# Database condition objects — the where-family's exotic forms returned as values

Slice: `feat(database): the condition-object family` — KDD-DB3's
recorded promise (`docs/ci-notes/database-core.md`: "the condition-object
API returns with the where-family's exotic forms"), the sugar the core
slice's lean verb surface deliberately deferred (whereColumn, whereNested,
the negated IN) landing as a VALUE grammar: trees the host composes and
hands to the verbs that already exist.

## The slice in one paragraph

The `Condition` contract (`fragment(SqlIdentifier): QueryFragment`) is
the single seam every exotic form answers: six leaves —
`ConditionComparison` (the value-bound comparison, a `SqlExpression`
column welcome), `ConditionColumnComparison` (the column-vs-column form
the reference spelled `whereColumn`), `ConditionIn` (a scalar list or a
subquery builder whose SQL and bindings merge, `NOT` polarity included),
`ConditionBetween` (low then high), `ConditionNull` (both polarities),
`ConditionLike` (the wildcard-escape ask) — and two composites:
`ConditionGroup::all()/any()` (the parenthesised AND/OR set the
reference spelled `whereNested`, returned as a value, nestable without
any imperative callback) and `ConditionNot` (the operand ALWAYS wrapped).
The facade's `where()`/`orWhere()` first parameter widens to
`Condition|SqlExpression|string` — the method count never grows, the
condition-object tree entering through the same verbs the triple always
rode — and the criteria lane widens the same way:
`RepositoryCriteria::apply(array|Condition)` rides the facade's widened
verb (never a parallel rendering), so `findBy`/`findOneBy`/`count`/
`exists`/`update`/`delete` all walk a composed tree through the one
entry the lane owns. Spelling both lanes in one call (a Condition beside
its operator/value) is refused; the column form still requires its
operator; the write-phase guard still bites on trees.

## KDD-DC1: the scope — exotic forms as values, never as verbs

The reference's answer to the exotic where-forms was sugar verbs
(`whereColumn`, `whereNested`, `whereIn`'s negated spelling). This tree's
answer is the verb-count doctrine the core slice pinned (KDD-DB3): every
exotic form is a VALUE the existing verbs accept, so the facade's surface
never grows — `where()`/`orWhere()` widen their first parameter and
nothing else changes. The consequence the slice owns: a host composes a
tree (`ConditionGroup::any(new ConditionNot(...), new ConditionIn(...))`)
and hands it to `where()` exactly where they would hand `'age', '>', 18`
— the same verb, the same phase guard, the same binding order discipline.
The mixed chain is first-class: fluent predicates and tree nodes join one
chain (AND through `where`, OR through `orWhere`), the tree
parenthesised as a whole so the outer connector can never reach inside.

## KDD-DC2: the grammar fires at the composition verb, exactly where the fluent grammar always fired

A leaf built with a bad operator does NOT refuse at construction — it
refuses at the `where()` call that composes it, surfacing precisely
where the fluent verb's own bad operator has always surfaced (the
unknown-operator list, the scalar guard naming the column, the empty
LIKE pattern). This is the `QueryComparison` fold's doing: the leaf's
`fragment()` renders through the same fold the fluent verb rides, so the
operator vocabulary, its refusal, and the null-equality guard
("use `whereNull()` instead of comparing with `= null`") live ONCE and
fire at the same moment either lane spells them. The construction-time
refusal is the STRUCTURAL one only: the empty group (a group with no
member is not a predicate — `()` renders nothing); the empty IN list
refuses with the grammar, at the composition verb, where its own fold's
guard lives.

## KDD-DC3: the two composites' parenthesisation discipline

`ConditionGroup` renders `(m1 AND m2 ...)` — parenthesised as a whole,
members in construction order, each member's bound values travelling
after the fragment that carries them; `ConditionNot` renders
`NOT (operand)` — ALWAYS wrapped, never a bare `NOT a = b` precedence
gamble. Nesting needs no callback: a group is a condition like any leaf,
composable inside other groups to any depth, and the deep-tree test pins
the binding order at depth (construction order, not render order, is the
order the placeholders appear in).

## KDD-DC4: the criteria lane — the gateway's six verbs walk the tree through the one widened entry

`RepositoryCriteria::apply()` already owned the map walk (AND-equality,
null cells spelling IS NULL); the slice widens its parameter to
`array|Condition` and answers the tree with ONE line — the facade's
widened `where()` — never a parallel rendering path the map lane does
not ride. Every gateway verb that takes criteria (`findBy`,
`findOneBy`, `count`, `exists`, `update`, `delete`) and the aggregate's
`COUNT(*)` walk therefore answer composed trees, and the repository
tests pin the SQL the walk renders for each. The lesson the lane
records: a widened union type on the SINGLE entry fold is the whole cost
of a new criteria lane — the six verbs downstream never learn the
condition-object family exists.

## KDD-DC5: the settle — the ceiling demanded the comparison grammar split its shapes

The settle round's extraction: the comparison folds left
`QueryPredicate` (the shaped-predicates fold keeps inList, between,
nullPredicate, like) for `QueryComparison` — and the first spelling of
the new fold carried BOTH comparison shapes (value-bound and
column-bound) at difficulty 22.62, over the 20 ceiling. The split the
ceiling demanded: the VALUE-BOUND comparison keeps the fold to itself
(17.45 — the verb's, the having verb's, and the leaf's shared unit, the
null-equality guard riding it), while the COLUMN-BOUND shape inlines in
its only consumer, the `ConditionColumnComparison` leaf (2.00 -> 6.38),
the way the trivial composites own their own assembly — its operator
refusal still the shared `QueryValues::assertComparisonOperator`, its
assembly four lines with no second caller. The lesson beside
KDD-DO5's class-difficulty curriculum: the metric does not care that two
shapes share a grammar — operator variety times operand repetition
counts the CLASS, and a two-shape class pays for both. The second
extraction the facade's own difficulty demanded: `QueryOperand` (18.75)
— the widened verbs' operand judgement (Condition vs triple, the
both-spelled refusal, the operator-required refusal) left the facade so
`where()`/`orWhere()` own only the connector they spell
(`QueryBuilder` 17.83), the verbs' refusal messages riding the `$verb`
name the fold carries.

## KDD-DC6: the psalm ledger — the leaf constructors are the host's spelling

Nine entries arrive, the "consumer-arrives-with-the-wiring" posture
every public-surface family before its first in-src consumer has
carried: seven `UnusedClass` (the leaves and composites — their
constructors are HOST code, outside the analysed paths) and
`ConditionGroup::all/any` as `PossiblyUnusedMethod` (the group's own
named constructors, host-composed like the leaves they build). The
interface itself carries no entry: psalm does not flag an unused
interface the leaves implement. The entries leave when a src consumer
composes the family — the same census wave the wiring slice recorded
(KDD-DW5), the promise the ledger keeps making and keeping.

## KDD-DC7: the mutation round — 242 mutants, and the two equivalents the variadic rewrap owns

The scoped battery over the slice's eighteen files: 242 mutants, 242
killed, MSI 100/100. The two `UnwrapArrayValues` escapes on
`ConditionGroup::all()/any()` joined the documented-equivalent ledger
after the no-hiding discipline's own proof: the complete suite (6733
tests) passes with both unwraps manually applied. The rewrap is the
LIST CONTRACT's spelling — psalm models the variadic as
`array<array-key, Condition>` exactly because named arguments are legal
PHP, while the composite's member property promises `list<Condition>` —
and it is never a runtime observable: the rendering fold walks its
members keyless (foreach without keys), its fragment list and param
accumulation rebuilt as lists by construction, so positional and named
callers alike answer the same SQL and the same binding order. The third
`UnwrapArrayValues` family member (after `ContainerModeSpellings::verdict`
and `RouteVersionSelection::select`), the same static-contract story.

## KDD-DC8: the tooling truths the slice paid for

PHPUnit 13 refuses an INTERFACE as a `CoversClass` target — one warning
per test method, "not a valid target for code coverage", the run still
green but the warning noise real; the `Condition` contract left
ConditionTest's CoversClass ledger (its implementations each carry their
own entry — the interface has no executable lines to cover). The
infection `--filter` scope over the branch's changed files is how a
slice answers its OWN mutants before the PR's full-tree battery: the
escape list stays the slice's, not the tree's. And the settle-pass
order the session cut short teaches the recovery posture: the working
tree may hold a half-finished extraction (the fold file written, the
call sites not yet rewired) — the test suite's undefined-method failures
ARE the map of what remains.
