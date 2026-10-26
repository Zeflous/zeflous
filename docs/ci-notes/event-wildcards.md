# The Event Wildcard Listeners — KDD

**Scope:** the Event Source lane's second slice — the wildcard listener
support the roadmap's enterprise phase 1 ("Advanced Listener
Management") demands at its head: one listener answering every event
whose name continues past a declared prefix. Two new source classes
(`EventPattern` the pattern value object, `WildcardMatches` the match
walk) plus the wildcard book (`WildcardSubscriptions`) and the
dispatcher's two new verbs (`subscribeWildcard` /
`unsubscribeWildcard`) and its one-pool delivery, `feat/qa-gates`'s
sibling branch `feat/event-wildcard-listeners`.

**Verified locally before the PR:** `ci:static` EXIT-0 (5552 tests /
13536 assertions; smoke 2760B over 20000 iterations; the duplication
and mess gates green inside the same battery — the fold this slice
adds is a net-new vocabulary, no clone) · psalm EXIT-0 at 99.9312%
inference with two surgical baseline lines (the family's own
tests-consume-outside-projectFiles posture) · progpilot `[]` · scoped
infection over the four touched source files, MSI 100/100, 0 escapes,
two documented MBString ignores (the tail-cut byte-agreement class the
event shape field's nullable-prefix entry already spelled) ·
archeology 0 error-level findings after the match-walk fold
(`WildcardSubscriptions` first spelled 23.06 against the ceiling of 20;
the walk folded out into `WildcardMatches` at 7.44, leaving the book
at 13.5).

## The map

| Unit | Question it owns |
|---|---|
| `EventPattern` | the pattern's own grammar and match: a name-prefixed trailing star, matching every name that continues past the prefix |
| `WildcardSubscriptions` | the book: one listener registry per declared pattern, declaration order kept |
| `WildcardMatches` | the walk: which subscriptions a dispatched event's name collects, one pass over the book's patterns |
| `EventDispatcher::subscribeWildcard` / `unsubscribeWildcard` | the wildcard pair of the runtime-modification vocabulary |
| `EventDispatcher::dispatch` | the one-pool delivery: exact and matching wildcard subscriptions sorted together |

## KDD-EW1 — the star is spent in the final segment alone

The core slice's name grammar (ED10) reserved the signature characters
so a wildcard matcher would never meet a name that spells one of its
own. This slice spends exactly one of them, in exactly one position:
the pattern is a valid event name whose last segment is the single
character `*` (`user.*`, `Order.Created.*`). A star anywhere else --
glued to a word (`user*`), mid-pattern (`user.*.created`), doubled
(`user.**`), or standing alone behind a bare dot (`.*`) -- is refused
with the spelling named, because each of those positions would make
the pattern's meaning a guess the framework refuses to make
(`user.*.created` addresses which events? at what depths?).

The pattern grammar buys its narrowness cheaply: it reuses the event
name's own grammar for everything before the star, adds one
empty-segment refusal (the character before `.*` must not be a dot --
`user..*` would match an event named `user.` plus anything, and no
grammar-valid name is), and inherits the 64-character ceiling the name
grammar spells.

## KDD-EW2 — matching is continuation past the prefix, any depth

`user.*` matches `user.created` and `user.updated.late` -- any name
that continues past `user.` with at least one more segment -- and
refuses `user` itself (the name is not *past* the dot) and
`usergroup.created` (the shared spelling is not the segment boundary).
The match is one `str_starts_with` against the pattern's prefix with
the star dropped: no depth counting, no segment arithmetic, and no
ambiguity about whether one or many segments may follow -- the
roadmap's own examples (`User.*`, `Order.Created.*`) are both
"everything under this branch", and an audit listener that only wanted
exactly-one-segment depth can spell the exact names themselves.

## KDD-EW3 — a wildcard subscription is its own contract

`subscribeWildcard()` does not consult the event book: a pattern needs
no definition, because the pattern IS the address, and the events it
matches are the events the application defines -- each dispatch
validating its payload against the matched event's own shape before
any listener, wildcard included, is invoked (a mis-shaped payload never
reaches a wildcard listener; the test pins it). The inverse holds too:
`dispatch()` still refuses an undefined event even when a wildcard
listener stands ready to observe it -- the definition surface is the
only authority on whether an event exists at all, and a typo cannot
silently deliver to a pattern that happens to prefix it.

## KDD-EW4 — one pool, one priority policy; the exact-first tiebreak

The dispatch pool is the exact-name registry's ordered subscriptions
and the matching wildcard subscriptions, sorted together by priority
descending -- priority stays THE ordering policy (ED5 extended to
wildcards: the framework does not guess category boundaries an
application could spell for itself). Among EQUAL priorities the pool
breaks exactly one tie itself: the exact-name listener stands before
the wildcard, the targeted answer before the general observation --
and each kind then keeps its own order (registration order among
equals, pattern-declaration order across patterns). The sort is the
registry's own stable usort replayed over the merged seed, so the
tiebreak is structural (the seed's own order), not a second comparator
the pool would have to justify.

## KDD-EW5 — the book keeps the family's own honesty rules

The wildcard book replays the exact registry's rules per pattern: a
second standing for the same listener object under the same pattern is
refused (the double-delivery bug), removal answers a boolean (the
stale-book answer a caller can act on), and an unsubscribe under a
pattern nobody declared answers false exactly like an unknown event's
does. The same listener under two different patterns -- or under a
pattern and an exact name -- is two subscriptions and two deliveries:
the wider world's own semantics, not a bug the book refuses for the
application (the test pins the double delivery and the outcome's count
of two).

## KDD-EW6 — the walk is a fold the book's ceiling forced out

The book first spelled the entry-keeping AND the match walk in one
class, and the architecture gate refused it at difficulty 23.06
against the ceiling of 20 -- the same fold-forcing the event book's
own entry-keeping split (ED11a) and the storage root's document-root
split spelled. The walk moved out into `WildcardMatches`, constructed
per call over the book's two maps: the arrays are copied at
construction but the registries they carry are the live objects, so a
dispatch always sees the stands that exist at the moment it asks. Two
questions, two seams: "who stands under which pattern" is the book's;
"who receives this event" is the walk's.

## KDD-EW7 — the two byte-agreement ignores, paid in the ledger

`EventPattern`'s two tail cuts (the constructor's `-2` prefix cut, the
match-side `-1` prefix cut) sit in zones the guards above them have
already proven ASCII: the constructor's cut runs only behind the
`str_ends_with '.*'` check, and the match-side cut stands behind the
same grammar, so `mb_substr` and `substr` answer the identical prefix
for every input the lines can ever see -- the same byte-agreement
class the event shape field's nullable-prefix entry already carries in
`infection.json5`, with the same argument recorded inline. The
constructor's own `mb_strlen` ceiling stays fully under the mutator:
the multibyte pattern tests kill its rewrites through the refusal
message's character count (a hundred two-byte letters are a hundred
characters over the ceiling, never two hundred -- and a 33-letter
multibyte prefix reaches the GRAMMAR refusal, not the length refusal,
only under the character-counting spelling).

## KDD-EW8 — what this slice deliberately leaves named

- **Listener priority grouping** (phase 1's third item) -- still
  waits for a real consumer, per the core slice's own ledger (ED12).
- **Async execution, retry, DLQ, poison pill** (phase 2) -- owns the
  failure regime this slice's bubbling doctrine declares for wildcard
  listeners too.
- **Serialization, cross-process bus, transactional outbox** (phases
  3-4) -- unchanged by this slice; the pool is still synchronous.
- **Event traceability** (phase 5) -- the outcome's delivery count now
  includes wildcard deliveries, the seam the observability fold will
  timestamp; splitting the count by kind waits for that consumer.
- **A `matches()` on the dispatcher's public face** (asking which
  patterns a name would collect) -- an inspection surface nobody has
  asked for; the route inspector's own doctrine: build it when the
  operator need lands.
