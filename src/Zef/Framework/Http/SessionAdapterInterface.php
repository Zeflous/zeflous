<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's storage port: the hexagonal boundary the
 * roadmap's Phase 3.3 promise spells -- session storage behind one
 * interface, so the driver can be swapped (the Redis, file or table
 * driver a deployment prefers) without the application's code ever
 * changing, because every consumer reads the port and only the
 * composition names the driver.
 *
 * The port's shape is deliberately the storage's own three
 * questions and nothing else: load one identifier's bag, save one
 * identifier's bag, destroy one identifier's bag. The expiry
 * semantics are the driver's own -- each storage has its own
 * time-to-live idiom (a Redis TTL, a file's mtime, a table's
 * last-written column), and the family hands the driver its idle
 * timeout at construction ({@see InMemorySessionAdapter}) rather
 * than widening the port with a clock the driver did not ask for.
 * A load that answers null is the port's whole "not found, or
 * found-but-expired" verdict: the middleware cannot tell the two
 * apart, and no consumer ever needs to -- both answer a fresh
 * session.
 *
 * The shipped driver is the {@see InMemorySessionAdapter} -- the
 * zero-dependency engine's own proof of the port, the same role the
 * in-memory stores play in the rate-limit family. The Redis, file
 * and table drivers are the lane's own named reservations: each is
 * a slice of its own (a file driver owns directory posture and
 * permissions; a Redis driver owns a connection the zero-dependency
 * gate would have to probe; a table driver owns a schema), and none
 * of them is a seam this port needs before its first consumer
 * asks.
 */
interface SessionAdapterInterface
{
    /**
     * Loads one identifier's data bag.
     *
     * @param string $id the session identifier
     *
     * @return null|array<string, mixed> the stored bag, or null when the
     *                                   identifier is unknown or its idle
     *                                   timeout has fully elapsed
     */
    public function load(string $id): ?array;

    /**
     * Saves one identifier's data bag, refreshing its idle clock --
     * the sliding-expiry discipline: activity extends a session, and
     * only idleness ends one.
     *
     * @param string               $id   the session identifier
     * @param array<string, mixed> $data the bag to store
     */
    public function save(string $id, array $data): void;

    /**
     * Destroys one identifier's bag: the storage answers as though
     * the session never existed.
     *
     * @param string $id the session identifier
     */
    public function destroy(string $id): void;
}
