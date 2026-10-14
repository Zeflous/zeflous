<?php

declare(strict_types=1);

namespace Zef\Framework;

/**
 * The framework's own configuration defaults: the code-declared profile the
 * {@see ApplicationBootstrapper} lays down as the Default layer under the
 * application's `app/configs/` files and the environment (see `ROADMAP.md`,
 * "Configuration System + DSL + Radix Tree" -- Default < App < Env).
 *
 * Defaults live in code, not in a shipped file, so they can never be
 * accidentally deleted or half-edited by a host application: a host that
 * never writes a configuration file boots on exactly this profile, and a
 * host that writes one overrides only the keys it cares about -- the
 * Laravel-style vendor-defaults seam, adapted to the layered builder the
 * configuration system already owns.
 *
 * The profile is deliberately empty today, and that is a discipline rather
 * than a gap: every key it ships must be a key a live consumer reads, so
 * no knob is declared before its subsystem exists. The growth plan is the
 * roadmap's own order -- the HTTP lane's knobs (`http.body_budget`,
 * `http.trusted_hosts`, `http.cors.allowed_origins`, ...) migrate here with
 * their wiring, the container's `aot` switch arrives with the compile/dump
 * area (roadmap area 8), and `tags` with the autoconfiguration area
 * (roadmap area 3). Until then an empty profile is also the honest one:
 * the framework ships no services, no middleware and no routes of its own.
 */
final readonly class FrameworkDefaults
{
    /**
     * The framework's default configuration profile.
     *
     * @return array<non-empty-string, mixed>
     */
    public static function profile(): array
    {
        return [];
    }
}
