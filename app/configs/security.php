<?php

declare(strict_types=1);

/*
 * The security subsystem's configuration file: everything under this key
 * is the `security` map the framework's security families read their
 * validated knobs from at boot.
 *
 * The framework's own defaults live in code (`FrameworkDefaults`, the
 * Default layer under this file and the environment), so a host that
 * deletes or empties this file still boots on the full default posture --
 * this file is the override surface, not the source of the values. Every
 * knob is read strictly: a wrong type or an unknown key halts the boot
 * with the knob named, never a silent fallback, and an unknown family
 * name under this key is refused the same way.
 *
 * The `headers` family -- the hardened response decoration middleware
 * (`security.headers`, the service the framework registers and the stack
 * composes):
 *
 *   'hsts'                      (bool, default false)
 *       Opt-in Strict-Transport-Security: one year, includeSubDomains,
 *       emitted only over https requests.
 *
 *   'csp'                       (bool, default false)
 *       Opt-in Content-Security-Policy.
 *
 *   'content_security_policy'   (string, default "default-src 'self';
 *       frame-ancestors 'none'; base-uri 'self'")
 *       The CSP directive string emitted when `csp` is on.
 *
 *   'permissions_policy'        (bool, default true)
 *       The deny-all Permissions-Policy header (camera, microphone,
 *       geolocation, payment) -- on by default.
 *
 * The six baseline hardening headers (nosniff, frame DENY, no-referrer,
 * no cross-domain policies, COOP/CORP same-origin) are always on and are
 * not knobs: weakening the baseline means removing the middleware from
 * the stack, which is a decision, not a spelling.
 *
 * The `rate_limit` family -- the admission control middleware
 * (`security.rate_limit`, the service the framework registers and the
 * stack composes):
 *
 *   'enabled'                   (bool, default false)
 *       Arms the throttle. The middleware in the stack is the admission
 *       seam; this knob arms it -- the emergency switch an env overlay
 *       can flip without touching the stack.
 *
 *   'limit'                     (int, default 100)
 *       The request budget per client address per window.
 *
 *   'window'                    (int seconds, default 60)
 *       The fixed window's length. The window starts at the address's
 *       first request and resets whole.
 *
 *   'max_keys'                  (int, default 10000)
 *       The capacity bound of the in-memory store's bucket table; a new
 *       address arriving at capacity first reclaims expired buckets,
 *       then the middleware answers its fail-closed 503. The bound is
 *       the memory store's own -- the shared stores own their capacity
 *       (APCu's memory, Redis's maxmemory).
 *
 *   'store'                     ('memory'|'apcu'|'redis', default 'memory')
 *       Where the accounting lives: per-process (memory), shared across
 *       one PHP process tree (apcu -- requires ext-apcu), or shared
 *       across every host pointing at the same Redis (redis -- requires
 *       ext-redis). An unavailable extension halts the boot with the
 *       remediation spelled out: a declared shared store never silently
 *       degrades to per-process accounting, whose effective limit is the
 *       declared one multiplied by the worker count.
 *
 *   'trusted_proxies'           (list<string>, default [])
 *       The exact addresses or CIDR networks allowed to supply the
 *       client address in X-Forwarded-For; every other client keeps its
 *       peer address. The env overlay spells the list comma-separated.
 *
 *   'redis_host'                (string, default '127.0.0.1')
 *   'redis_port'                (int, default 6379)
 *       The redis store's endpoint, connected (and refused) at boot.
 *
 * Environment overlay spelling (prefix + `__` as the dot, values strictly
 * typed): APP_SECURITY__HEADERS__HSTS=1, APP_SECURITY__RATE_LIMIT__
 * ENABLED=on, APP_SECURITY__RATE_LIMIT__LIMIT=200, APP_SECURITY__
 * RATE_LIMIT__STORE=redis.
 *
 * The shipped profile declares no overrides -- the defaults are the
 * posture -- and an application fills this file with the knobs it wants
 * adjusted. Later families (CSRF, authentication) grow their own
 * subtrees under this key with their pull requests.
 */

return [];
