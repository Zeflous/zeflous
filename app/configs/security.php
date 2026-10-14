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
 * with the knob named, never a silent fallback.
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
 * Environment overlay spelling (prefix + `__` as the dot, values strictly
 * boolean): APP_SECURITY__HEADERS__HSTS=1, APP_SECURITY__HEADERS__CSP=on.
 *
 * The shipped profile declares no overrides -- the defaults are the
 * posture -- and an application fills this file with the knobs it wants
 * adjusted. Later families (rate limiting, CSRF, authentication) grow
 * their own subtrees under this key with their pull requests.
 */

return [];
