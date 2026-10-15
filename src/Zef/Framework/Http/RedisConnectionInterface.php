<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The phpredis client seam: what the shared rate-limit family's boundary
 * speaks to -- a client that connects and answers the three commands.
 *
 * The real client is the extension's own `\Redis` (it cannot implement
 * this interface -- it is a C extension's class); the boundary's union
 * type (`\Redis|RedisConnectionInterface`) carries it exactly as
 * phpredis declares it, engine-checked on every runner that carries the
 * extension. The interface arm is the seam everything else in the
 * family is written against, and the arm the test suite's double
 * implements: a real, autoloaded, fully analysed class, one behaviour
 * in every environment -- no process isolation, no eval, no signature
 * gymnastics against the extension's own shapes.
 *
 * Extends the command surface ({@see RedisCommandsInterface}) with the
 * connection establishment the boot refusal ({@see PhpRedisConnection})
 * needs to attempt.
 *
 * Attribution: legacy `zef-main` `Framework/Security/
 * RedisSharedRateLimitStore.php` (the `\Redis` member the reference
 * called directly; extracted as the family's own seam so the commands
 * and the connection stay pure).
 */
interface RedisConnectionInterface extends RedisCommandsInterface
{
    /**
     * Connect to the endpoint: the two arguments the boundary always
     * supplies, over whatever defaults the client carries.
     */
    public function connect(string $host, int $port): bool;
}
