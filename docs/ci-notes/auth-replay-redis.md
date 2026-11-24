# Auth replay Redis — the fleet spelling of the seen-table

The decision record for the Redis composition of the auth replay
seam (the Http lane's own spelling: `RedisReplayStore` +
`RedisReplayProtector`): the HTTP Layer row's own named open slice
("the Redis composition of the auth replay seam, the APCu answer
live #518"), the third topology the `security.auth.replay_store`
knob's closed vocabulary answers, closing the port's own promise --
the interface's docblock spelled the deployment that "needs the
boundary across a process tree or a host fleet" as a composition
over the seam rather than a fork, and this slice lands the fleet
half the same way #518 landed the tree half.

## KDD-RR1 — the interface was the seam, the composition is the answer

The replay port (`ReplayProtectorInterface`) sees only the id and the
clock; the window, the capacity and the storage are the
implementation's own -- the doctrine the port's own docblock spelled
the day it was ported from the reference. The reference itself never
shipped a Redis replay spelling (its `Distributed` lane carried the
interface and the in-memory bounded table only), so this slice is the
tree's own composition, not a port: the interface stays untouched,
the boundary stays store-agnostic, and the new spelling is one more
`implements` clause -- exactly what "a composition rather than a
fork" means.

## KDD-RR2 — the claim is one INCR, the count is the accounting

The APCu spelling claims a first sight with one atomic `apcu_add`
({@see ApcuReplayStore}); the Redis spelling claims with one
server-side-atomic `INCR` over the phpredis command seam
({@see RedisCommandsInterface}) the rate-limit family's shared store
already rides -- the reference's own remediation doctrine held again:
its first counter design ran a Lua script through `Redis::eval()` and
its second used `WATCH`/`MULTI`/`EXEC`, both rejected as heavier than
the INCR-atomic pattern that answers the same exactness contract. A
replayed id's key starts its life at `INCR` answering `1`, so exactly
one request of the whole fleet ever answers the first sight; every
later sight inside the window answers a count above one and is a
duplicate by the same single step. No new interface was opened for
this: the commands seam's three verbs (INCR/TTL/EXPIRE) answer the
whole claim, so the recording double the rate-limit family's tests
already ride covers this family's too.

## KDD-RR3 — the TTL is the window, the recovery arm the sister's own

The winner installs the duplicate window with one `EXPIRE`, and the
key's TTL carrying the window means an id whose sight expired is
fresh again exactly as the in-memory table's cutoff and the APCu
claim's TTL both make theirs -- the key vanishes at expiry, so the
next `INCR` answers `1` again. The crash-between-INCR-and-EXPIRE
state (a TTL that reads negative without being brand new) is the
sister store's own crash state answered the same way: the window is
re-installed whole, the duplicate verdict stands (the count is the
accounting), and the key's life stays bounded -- even a window whose
first install was lost eventually elapses and the id turns fresh
again, never an immortal key a single lost `EXPIRE` minted.

## KDD-RR4 — UNAVAILABLE stays the APCu spelling's own, honestly

The interface's closed verdict set carries UNAVAILABLE for the
accounting outage, and the APCu twin answers it because its
`apcu_add`'s false is ambiguous (a genuine duplicate or an outage --
the read-back disambiguates). The Redis spelling never reaches that
verdict: the INCR reply is either the count (a definite verdict by
itself) or the fail-closed refusal the reply normalisation throws
with the command named -- the `redisInt` doctrine the sister store
spells, reached one-way and re-worded for this family's own store.
The family's fail-closed line is held on every spelling: never an
unaccounted admission, never a fabricated duplicate, and the
boundary's catch-all converts either shape into the 5xx family the
operator meets.

## KDD-RR5 — the window round-up, the key, and the clock never spent

The window's milliseconds round up to the whole seconds Redis's own
`EXPIRE` keeps (`windowMs + 999` divided by 1000, the APCu twin's
arithmetic character-for-character over its own store's TTL): the
duplicate window stays at least as long as configured, never
silently shortened, and the constructor's `windowMs >= 1` bound
already proves the round-up answers at least one second for every
admitted window. The key is the bounded replay id under the Redis
family's own colon prefix (`zef:replay:`, the rate-limit counters'
`zef:ratelimit:` the naming twin -- one prefix, no collision), the
id's own grammar bound keeping the key bounded the way the APCu
twin's raw spelling keeps its. The port's `$nowMs` clock is
deliberately not spent: the in-memory and APCu spellings lay the
request's clock into the table, but the Redis accounting is the
server's own -- the TTL the winner installs carries the window, and
the expiry turns the id fresh again without any request clock
entering the accounting.

## KDD-RR6 — the endpoint knobs are the family's own, the shape the rate-limit family's

The `redis_host`/`redis_port` knobs join the family's own
`security.auth` block -- mirroring the rate-limit family's own
endpoint pair exactly (non-empty host string, integer port with the
1-65535 bound, the digit-string fold the environment overlay's
`APP_SECURITY__AUTH__REDIS_PORT=6380` spells): each family owns its
knobs because each family's endpoint is its own declaration -- a
deployment's auth table and its rate-limit counters may live on
different servers, and a shared knob would be a topology statement
neither family can make for the other. The strict census
(`AuthKnobs`) widens to eleven knobs, and the profile mirror
(`FrameworkDefaults` + the shipped file's docblock) carries the
endpoint pair beside the `replay_store` word it already owed.

## KDD-RR7 — the boot-time connection, the family's own refusal

The factory's redis arm composes the phpredis boundary the
rate-limit family's own store rides (`PhpRedisCommands`), and the
connection is established at the middleware resolution the `redis`
spelling arms -- a declared fleet table is a statement that shared
accounting is wanted, and booting without its server is the honest
halt (the doctrine the rate-limit family spelled when it moved the
reference's unconnected `\Redis` out of the first request's catch-all
503). The establishment's refusal words are the reaching family's
own: the `PhpRedisConnection::establish` seam gained the optional
refusal-spelling parameter this slice (null keeps the rate-limit
family's own message character-identical, its pinned tests
untouched), and the auth arm passes its own -- the endpoint the
knobs declared named, the family's own alternatives (memory, apcu)
spelled in the remediation.

## KDD-RR8 — the drift the slice re-homes, documentation trailing the code

Three sentences had trailed the code and ride this slice's files:
the shipped `security.php` docblock never documented `replay_store`
at all (the knob landed with #518 without its docblock entry), the
`replay_window` paragraph still told the operator that "a deployment
that runs a process tree behind the boundary composes its own shared
protector through the family's interface seam" -- the pre-APCu
posture, stale since #518 answered it -- and the factory's own
docblock still named "the bounded in-memory seen-table as the replay
protector" while the knob selected three spellings. All three are
re-answered in the files the slice already touches, the drift-honesty
doctrine the #905 hygiene pass spelled; the FrameworkDefaults auth
block gains the `replay_store` default word it never carried, so the
profile mirror the boot test pins now carries the whole knob set.
