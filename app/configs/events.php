<?php

declare(strict_types=1);

/*
 * The events family's configuration file: everything under this key
 * is the `events` map the framework's wired default reads -- the
 * durable event delivery arm's declaration surface.
 *
 * The deferred delivery family
 * ----------------------------
 *
 *   'store'                     (string, default '')
 *       The wired default's store word over the durable delivery
 *       port (`DeferredDeliveryStore`): '' (the shipped default --
 *       no wired default is declared, the durable arm stays opt-in
 *       and a host composes its own store at the composition seam),
 *       'file' (the filesystem answer, `FileDeliveryStore` over
 *       the storage layout's `queue` lane -- the at-least-once
 *       local store whose dead-letter lane an operator reads, whose
 *       admission bound is the capacity knob below) or 'database'
 *       (the database family's answer, `PdoDeliveryStore` over the
 *       named connection the 'connection' knob spells -- the
 *       cross-process answer the transactional/outbox lane's own
 *       slice wires, its claim columns the exclusivity a fleet of
 *       drainers needs).
 *
 *       The word is read through a closed vocabulary: a typo is a
 *       named halt, never a silently defaulted store. The Redis
 *       answer and the bus transports the roadmap names after it
 *       are their own named futures -- the word arrives with the
 *       wiring that can read its own knobs, never before.
 *
 *       Resolving the framework's `events.deferred.store` service
 *       while this knob stays empty is the halt that names the way
 *       out: spell a store word, or compose the durable arm by
 *       hand over the port (`EventDispatcher::durableDelivery()`).
 *
 *   'path'                      (string, default '')
 *       The file store's directory: an explicitly spelled address
 *       always wins (judged by the store's own privacy posture --
 *       existing, writable, non-symlink, owner-only, outside the
 *       public document root), and an empty string derives from the
 *       storage layout's `queue` lane when `storage.root` declares
 *       one (the same one-way precedence every storage family
 *       keeps). The 'file' store word with neither an address nor a
 *       declared root is a named halt -- a directory the host never
 *       asked for is never silently defaulted.
 *
 *   'capacity'                  (int, default 1024)
 *       The store's pending bound: the admission ceiling the wired
 *       store enforces whole-batch (a defer that would push past it
 *       is refused with the counts spelled -- the backpressure the
 *       durable arm owns). At least one; an environment overlay may
 *       spell it as an all-digit string (`APP_EVENTS__DEFERRED__
 *       CAPACITY=2048`) and it folds to the integer it spells.
 *
 *   'connection'                (string, default '')
 *       The database word's named connection: the name the
 *       `database.connections` map carries, the default connection
 *       the `database.default` knob spells when this knob stays
 *       silent (the migration runner's per-purpose name doctrine).
 *       A name the map does not carry is a named halt. The
 *       transactional/outbox lane's relay composes the outbox and
 *       this store over the SAME connection -- the relay's
 *       read-defers-removal unit is the engine's own atomicity
 *       over one socket.
 *
 *   'prefix'                    (string, default 'zef_')
 *       The database word's table namespace: the deployment's own
 *       spelling every table name starts with (the delivery
 *       tables and the outbox table alike), so one schema can hold
 *       more than one store and the framework's tables stay
 *       visibly the framework's -- the migrations family's own
 *       posture (`zef_migrations`). Judged by the store's own
 *       identifier grammar at composition.
 *
 *   'claim_timeout'             (int, default 60)
 *       The database word's visibility timeout: the seconds a
 *       crashed drainer's claims survive before the next drainer
 *       hands the claimed entries on. At least one; an environment
 *       overlay may spell it as an all-digit string and it folds to
 *       the integer it spells.
 *
 * The retry policy, the quarantine threshold and the listener
 * resolution stay deliberately OUT of this block: they are the
 * composition's own words (the resolver table a deployment spells,
 * the policy arithmetic, the containment threshold), consumed at
 * the `EventDispatcher::durableDelivery()` seam -- a knob without
 * a reader is a dead key, and the module system's own events
 * reader is the named future that may widen this block.
 */

return [
    'deferred' => [
        'store' => '',
        'path' => '',
        'capacity' => 1024,
        'connection' => '',
        'prefix' => 'zef_',
        'claim_timeout' => 60,
    ],
];
