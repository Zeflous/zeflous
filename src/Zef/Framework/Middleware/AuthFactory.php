<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Zef\Framework\Config\Config;
use Zef\Framework\Http\AllowScopePolicy;
use Zef\Framework\Http\ApcuReplayProtector;
use Zef\Framework\Http\AuthBoundary;
use Zef\Framework\Http\AuthBounds;
use Zef\Framework\Http\AuthClock;
use Zef\Framework\Http\AuthMiddleware;
use Zef\Framework\Http\AuthPolicy;
use Zef\Framework\Http\BoundedReplayProtector;
use Zef\Framework\Http\PhpRedisCommands;
use Zef\Framework\Http\RedisReplayProtector;
use Zef\Framework\Http\RedisReplayStore;
use Zef\Framework\Http\ReplayProtectorInterface;
use Zef\Framework\Http\ReplayStores;
use Zef\Framework\Http\SecurityFamilies;
use Zef\Framework\Http\StaticCredentialProvider;

/**
 * The auth middleware's config-driven construction seam: the factory
 * the framework profile's own `security.auth` service registration
 * names.
 *
 * The registration is declared as data in {@see
 * \Zef\Framework\FrameworkDefaults} -- `'factory' =>
 * AuthFactory::class . '::fromConfig'` over the `framework.config`
 * service argument -- resolved through the container's existing
 * declarative grammar, exactly as the security-headers, rate-limit
 * and CSRF families' are. This class is the grammar's
 * string-addressed target, which is why it is marked `@api` rather
 * than being baselined or suppressed.
 *
 * Validation is the family's own ({@see AuthPolicy::fromMap()}), and
 * it is fail-loud where the reference fell silent: an armed family
 * with an empty static token table halts the resolution with the
 * remediation spelled out ({@see AuthBounds}) -- the same seam the
 * CSRF family's signing secret already holds, argued the same way:
 * being resolved here means the stack membership opted in, and a
 * silently tokenless boundary is a boundary the operator believes is
 * running while it admits nothing but refuses nobody it should not
 * have.
 *
 * The composition the knobs shape is the reference's own distributed
 * boundary in this repository's spelling: the static token table as
 * the credential provider, the scope grant as the authorization
 * policy, the seen-table the `replay_store` knob selects as the
 * replay protector -- per-process, APCu across the process tree, or
 * Redis across the host fleet -- and the boundary that orchestrates
 * the three -- every
 * collaborator replaceable through hand construction ({@see
 * AuthBoundary}) when the application grows a real credential
 * backing or a real authorization model.
 *
 * @api
 */
final readonly class AuthFactory
{
    /**
     * Builds the middleware from the frozen repository's `security
     * .auth` subtree: the merged, already-defaulted knob map,
     * validated fail-closed by the policy, over the boundary the
     * knobs compose -- an armed policy only: the disarmed one
     * carries no boundary by construction, and a middleware that will
     * never consult one carries none.
     *
     * This resolution is the family's fail-loud seam: the armed
     * branch asserts the token table is one it can admit with ({@see
     * AuthBounds::assertUsableTokens()}) before any boundary is
     * composed, because being resolved here means the stack
     * membership opted in.
     */
    public static function fromConfig(Config $config): AuthMiddleware
    {
        $authPolicy = AuthPolicy::fromMap(
            SecurityFamilies::subtree($config->get('security'), 'auth'),
        );

        if (!$authPolicy->enabled) {
            return new AuthMiddleware($authPolicy);
        }

        AuthBounds::assertUsableTokens($authPolicy->tokens);

        return new AuthMiddleware(
            $authPolicy,
            new AuthBoundary(
                new StaticCredentialProvider(
                    $authPolicy->tokens,
                    $authPolicy->principal,
                    $authPolicy->scope,
                    $authPolicy->allowAnonymous,
                ),
                new AllowScopePolicy($authPolicy->requiredScope, $authPolicy->allowAnonymous),
                self::replayProtector($authPolicy),
            ),
        );
    }

    /**
     * The seen-table the `security.auth.replay_store` knob selected: the
     * per-process spelling the reference shipped, the shared APCu
     * spelling a process tree declares, or the shared Redis spelling
     * a host fleet declares -- the topology statement the
     * closed vocabulary keeps exact ({@see ReplayStores}), the same
     * selection the rate-limit family's own store knob makes. The
     * redis arm composes the phpredis boundary the rate-limit family's
     * own store rides, the family's own refusal spelled for it: the
     * endpoint the policy's `redis_host`/`redis_port` knobs declare,
     * the connection established at this resolution, so a declared
     * fleet table without its server is the honest halt the boot
     * hears, never a first-request catch-all 503 the operator waited
     * for a client to meet.
     */
    private static function replayProtector(AuthPolicy $authPolicy): ReplayProtectorInterface
    {
        if ($authPolicy->replayStore === ReplayStores::APCU) {
            return new ApcuReplayProtector(
                AuthClock::millisecondsFromSeconds($authPolicy->replayWindow),
            );
        }

        if ($authPolicy->replayStore === ReplayStores::REDIS) {
            return new RedisReplayProtector(
                new RedisReplayStore(
                    new PhpRedisCommands(
                        $authPolicy->redisHost,
                        $authPolicy->redisPort,
                        null,
                        'The security.auth redis replay store is unavailable at '
                        . $authPolicy->redisHost . ':' . $authPolicy->redisPort
                        . ' (install ext-redis and start the server, or declare the memory'
                        . ' or apcu replay store).',
                    ),
                ),
                AuthClock::millisecondsFromSeconds($authPolicy->replayWindow),
            );
        }

        return new BoundedReplayProtector(
            $authPolicy->replayCapacity,
            AuthClock::millisecondsFromSeconds($authPolicy->replayWindow),
        );
    }
}
