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
 *       and a host composes its own store at the composition seam)
 *       or 'file' (the filesystem answer, `FileDeliveryStore` over
 *       the storage layout's `queue` lane -- the at-least-once
 *       local store whose dead-letter lane an operator reads, whose
 *       admission bound is the capacity knob below).
 *
 *       The word is read through a closed vocabulary: a typo is a
 *       named halt, never a silently defaulted store. The
 *       cross-process answers (Redis, and the transports the
 *       roadmap names after it) are their own named futures -- the
 *       word arrives with the wiring that can read its own knobs,
 *       never before.
 *
 *       Resolving the framework's `events.deferred.store` service
 *       while this knob stays empty is the halt that names the way
 *       out: spell the 'file' word, or compose the durable arm by
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
    ],
];
