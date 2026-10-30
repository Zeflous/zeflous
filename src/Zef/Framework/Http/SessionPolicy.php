<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The session family's validated knob set: the `http.session`
 * configuration subtree as one immutable value object -- following the
 * body-parser family's own {@see BodyParserPolicy} lineage, which
 * followed the compression family's.
 *
 * Construction through {@see self::fromMap()} is the config-driven path
 * -- the merged map the layered builder hands over, where a knob's
 * value can be the native type a file declared or the raw string an
 * environment overlay wrote over it. The arm and the two cookie flags
 * read through the shared toggle reader ({@see StrictToggles}), the
 * two integer knobs through the integer seam ({@see StrictIntegers}),
 * the cookie name through the family's name vocabulary ({@see
 * SessionCookieName}), and the SameSite posture
 * through the family's vocabulary ({@see SameSiteVocabulary}): a wrong
 * type, an out-of-corridor bound, an unknown word or an unknown key
 * halts the boot with the knob named. The storage-driver word reads
 * through the family's store vocabulary ({@see SessionStores}) and the
 * file driver's declared directory is any string the host spelled
 * (the empty string the derive posture, an explicit address always
 * the deployment's own word) -- the two composition knobs the factory
 * seam consumes, validated here so the census and the reading can
 * never disagree.
 *
 * A `null` map is the absent family and reads as the constructor
 * defaults, which mirror the framework profile's own declaration
 * ({@see \Zef\Framework\FrameworkDefaults}); the boot test pins the
 * two declarations together so they cannot drift.
 *
 * The family arms ON by default: a deployment that composes the
 * middleware into its stack is asking for sessions, exactly as a
 * deployment that composes the body-parser middleware is asking for
 * parsed bodies. The cookie posture's defaults are the roadmap's own
 * promised secure trio -- `HttpOnly` on, `Secure` on (emitted
 * scheme-conditionally, the CSRF family's own posture: a local http
 * bootstrap still receives its session cookie), and `SameSite=Lax`,
 * the roadmap's spelled default. The `SameSite= None` word requires
 * the Secure knob at validation exactly as the CSRF policy demands it
 * ({@see CsrfPolicy}): no current browser accepts a `None` cookie
 * without `Secure`, so no posture may reach the issuance line with an
 * unsatisfiable request.
 *
 * The idle timeout (1_800 seconds, thirty minutes) is the storage-side
 * sliding expiry: every save refreshes it, so activity extends the
 * session and only idleness ends one. Both integer corridors are the
 * bounds class's own ({@see SessionBounds}), asserted at this
 * constructor so they hold at both construction paths alike -- the
 * map reading and the programmatic spelling.
 */
final readonly class SessionPolicy
{
    /** The shared rejection suffix: the value the call site read, as the
     * message spells it. */
    private const string GIVEN_SUFFIX = ' given.';

    /** The knob map's own defaults, the merge that fills the absent knobs
     * before the strict readers read -- one spelling of the default
     * posture the constructor's own signature also spells (the empty-map
     * test pins the two together). */
    private const array KNOB_DEFAULTS = [
        'enabled' => true,
        'cookie_name' => 'zef_session',
        'idle_timeout' => 1800,
        'id_bytes' => 32,
        'cookie_secure' => true,
        'cookie_http_only' => true,
        'same_site' => SameSiteVocabulary::LAX,
        'store' => SessionStores::MEMORY,
        'path' => '',
    ];

    public string $cookieName;

    /** The storage driver the composition seam wires over the port ({@see SessionStores}). */
    public string $store;

    /**
     * @param bool   $enabled        the family's arm: a disabled family is a
     *                               pass-through that reads no cookie and
     *                               loads no session
     * @param string $cookieName     the session cookie's name
     * @param int    $idleTimeout    the storage-side sliding expiry, in seconds
     *                               ({@see SessionBounds} holds the corridor)
     * @param int    $idBytes        the session identifier's entropy, in bytes
     *                               ({@see SessionBounds} holds the corridor)
     * @param bool   $cookieSecure   whether the cookie asks for the Secure
     *                               attribute (emitted over https, or
     *                               whenever SameSite is None)
     * @param bool   $cookieHttpOnly whether the cookie carries HttpOnly
     * @param string $sameSite       the SameSite posture: one of the
     *                               {@see SameSiteVocabulary} words
     * @param string $store          the storage driver the composition seam
     *                               wires over the port: one of the
     *                               {@see SessionStores} words
     * @param string $path           the file driver's declared directory --
     *                               empty derives from the storage layout's
     *                               `sessions` lane when the composition
     *                               asks ({@see \Zef\Framework\Middleware\SessionStorageWiring}),
     *                               an explicitly spelled address always wins
     */
    public function __construct(
        public bool $enabled = true,
        string $cookieName = 'zef_session',
        public int $idleTimeout = 1800,
        public int $idBytes = 32,
        public bool $cookieSecure = true,
        public bool $cookieHttpOnly = true,
        public string $sameSite = SameSiteVocabulary::LAX,
        string $store = SessionStores::MEMORY,
        public string $path = '',
    ) {
        $this->cookieName = SessionCookieName::read($cookieName);
        $this->store = SessionStores::read($store);
        SessionBounds::assertIdleTimeout($this->idleTimeout);
        SessionBounds::assertIdBytes($this->idBytes);

        if ($this->sameSite === SameSiteVocabulary::NONE && !$this->cookieSecure) {
            throw new HttpException(
                'The http.session same_site None knob requires the Secure cookie attribute'
                . ' (cookie_secure).',
            );
        }
    }

    /**
     * Reads the merged `http.session` map into the policy.
     *
     * @param mixed $map the subtree as the frozen repository carries it: a
     *                   knob map, or null when the family is absent
     *
     * @throws HttpException when the value is neither a map nor null, or a
     *                       knob fails its strict reading, its bound or its
     *                       vocabulary
     */
    public static function fromMap(mixed $map): self
    {
        if ($map === null) {
            return new self();
        }

        if (!\is_array($map)) {
            throw new HttpException(
                'The http.session configuration must be a map, '
                . get_debug_type($map) . self::GIVEN_SUFFIX,
            );
        }

        SessionKnobs::rejectUnknown($map);

        $map += self::KNOB_DEFAULTS;

        return new self(
            enabled: StrictToggles::read($map['enabled'], 'http.session enabled'),
            cookieName: SessionCookieName::read($map['cookie_name']),
            idleTimeout: StrictIntegers::read($map['idle_timeout'], 'http.session idle_timeout'),
            idBytes: StrictIntegers::read($map['id_bytes'], 'http.session id_bytes'),
            cookieSecure: StrictToggles::read($map['cookie_secure'], 'http.session cookie_secure'),
            cookieHttpOnly: StrictToggles::read($map['cookie_http_only'], 'http.session cookie_http_only'),
            sameSite: SameSiteVocabulary::read($map['same_site'], 'http.session'),
            store: SessionStores::read($map['store']),
            path: self::readPath($map['path']),
        );
    }

    /**
     * Reads the file driver's declared directory: any address string,
     * the empty string included (the derive posture the wiring answers).
     *
     * @param mixed $path the raw knob value, the empty string when absent
     *
     * @throws HttpException when the knob is not a string
     */
    private static function readPath(mixed $path): string
    {
        if (\is_string($path)) {
            return $path;
        }

        throw new HttpException(
            'The http.session path knob must be a string, '
            . get_debug_type($path) . self::GIVEN_SUFFIX,
        );
    }
}
