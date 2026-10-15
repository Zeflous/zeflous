<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Redis;
use Throwable;

/**
 * The phpredis extension's connection half: the boot-time establishment
 * of one client connection, refused fail-closed with the endpoint and
 * the remediation spelled out for every way it can fail.
 *
 * This is one half of the family's phpredis boundary ({@see
 * PhpRedisCommands} is the command half, and stays pure over whatever
 * connection this half hands it): the extension's class missing (an
 * environment without ext-redis), the endpoint refusing, or the
 * extension throwing its own connection error all fold into the one
 * refusal -- a declared `store: redis` is a statement that shared
 * accounting is wanted, and booting without it is the honest refusal.
 * The reference constructed an unconnected `\Redis` and let the first
 * request's commands fatal into its catch-all 503 instead -- an outage
 * the operator never heard about until a client did.
 *
 * The optional third argument carries the client the test suite
 * injects (its class double, {@see RedisConnectionInterface}); the
 * production path lets it default to the extension's own `\Redis`,
 * which the return union carries exactly as the extension declares it.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (the endpoint the reference's own
 * `\Redis` member connected to; extracted so the command half above it
 * stays pure), plus the connect-fail remediation.
 */
final class PhpRedisConnection
{
    /**
     * The established client connection: the extension's own `\Redis`
     * by default, or the injected client once connected.
     *
     * @param string                   $host            the Redis endpoint's host
     * @param int                      $port            the Redis endpoint's port
     * @param RedisConnectionInterface $redisConnection the client to establish (the
     *                                                  extension's own by default; the
     *                                                  test suite injects its double)
     *
     * @throws HttpException when the connection cannot be established
     */
    public static function establish(
        string $host,
        int $port,
        ?RedisConnectionInterface $redisConnection = null,
    ): Redis|RedisConnectionInterface {
        try {
            $client = $redisConnection ?? new Redis();
            $connected = $client->connect($host, $port);
        } catch (Throwable) {
            throw new HttpException(self::unavailable($host, $port));
        }

        if ($connected === false) {
            throw new HttpException(self::unavailable($host, $port));
        }

        return $client;
    }

    /**
     * The one refusal every way the connection can fail: the endpoint and
     * the remediation, spelled out.
     */
    private static function unavailable(string $host, int $port): string
    {
        return 'The redis rate-limit store is unavailable at ' . $host . ':' . $port
            . ' (install ext-redis and start the server, or declare the memory store).';
    }
}
