<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The stateless cookie helper's response half -- the Zero-Library
 * Shim's own promised Secure Cookie Builder: the value object that
 * carries one cookie's name, value and attributes, and renders the
 * `Set-Cookie` header value with the security flags right by
 * default.
 *
 * The defaults ARE the roadmap's promised trio: `Path=/`, `HttpOnly`,
 * `SameSite=Lax`, and the Secure posture armed -- so a host's plain
 * `new SetCookie('theme', 'dark')` already answers the secure
 * spelling, and relaxing a flag (`new SetCookie('pref', 'light',
 * httpOnly: false)` for a cookie JavaScript must read) is a
 * declaration, not an accident. The CSRF family's issuance line
 * ({@see CsrfSetCookie}) keeps its own narrow fold: it predates this
 * seam, its provenance is the reference implementation's own
 * attribute order, and folding it here would restructure a family
 * this slice does not own -- the same one-way-composition discipline
 * every lane follows.
 *
 * The Secure attribute is emitted scheme-conditionally -- only over
 * https, or whenever `SameSite=None` asks for it, the one exception
 * the browser contract forces ({@see CsrfSetCookie}'s own note: no
 * current browser accepts a `None` cookie without `Secure`) -- so a
 * local http bootstrap still receives its cookie, and the policy
 * validation upstream ({@see SessionPolicy}) has already refused a
 * `None` posture whose Secure flag is off.
 *
 * Every attribute is validated at construction: a name that is not
 * an RFC 9110 token, a value outside the cookie-octet grammar, a
 * `SameSite` word outside the closed vocabulary, a path that is
 * neither empty nor root-relative, a domain outside its grammar, or
 * a negative age is a host bug the constructor names loudly -- never
 * a header line the browser would silently drop.
 */
final readonly class SetCookie
{
    public const string SAME_SITE_STRICT = 'Strict';

    public const string SAME_SITE_LAX = 'Lax';

    public const string SAME_SITE_NONE = 'None';

    /** The cookie-name grammar: an RFC 9110 token. */
    private const string NAME_GRAMMAR = '/^[!#$%&\'*+\-.^_`|~0-9A-Za-z]+$/D';

    /** The cookie-value grammar: RFC 6265 cookie-octets, empty allowed
     * (the deletion spelling rides an empty value). */
    private const string VALUE_GRAMMAR = '/^[\x21\x23-\x2B\x2D-\x3A\x3C-\x5B\x5D-\x7E]*$/D';

    /** The path grammar: empty (not rendered) or root-relative. */
    private const string PATH_GRAMMAR = '/^(|\/[^\x00-\x1F\x7F]*)$/D';

    /** One domain label: alnum-led, hyphen-tolerant inside, alnum-tailed. */
    private const string LABEL = '[0-9A-Za-z]([0-9A-Za-z-]*[0-9A-Za-z])?';

    /** The domain grammar: optionally dot-prefixed dot-separated
     * labels, each hyphen-tolerant inside. */
    private const string DOMAIN_GRAMMAR = '/^[.]?' . self::LABEL . '(\.' . self::LABEL . ')*$/D';

    /** The canonical SameSite words, keyed by their folded spelling. */
    private const array SAME_SITE_WORDS = [
        'strict' => self::SAME_SITE_STRICT,
        'lax' => self::SAME_SITE_LAX,
        'none' => self::SAME_SITE_NONE,
    ];

    /**
     * @param string   $name      the cookie's name (RFC 9110 token)
     * @param string   $value     the cookie's value (cookie-octets; empty
     *                            allowed)
     * @param string   $path      the Path attribute (root-relative, or
     *                            empty to leave the attribute out)
     * @param string   $domain    the Domain attribute (its own grammar,
     *                            or empty to leave the attribute out)
     * @param string   $sameSite  the SameSite word: Strict, Lax or None
     * @param bool     $secure    whether the Secure attribute is asked
     *                            for (emitted over https, or whenever
     *                            SameSite is None)
     * @param bool     $httpOnly  whether the HttpOnly attribute is
     *                            carried
     * @param null|int $maxAge    the Max-Age attribute in seconds, or
     *                            null to leave it out
     * @param null|int $expiresAt the Expires attribute as a Unix
     *                            timestamp rendered in the IMF-fixdate
     *                            spelling, or null to leave it out
     *
     * @throws HttpException when any attribute fails its grammar
     */
    public function __construct(
        public string $name,
        public string $value = '',
        public string $path = '/',
        public string $domain = '',
        public string $sameSite = self::SAME_SITE_LAX,
        public bool $secure = true,
        public bool $httpOnly = true,
        public ?int $maxAge = null,
        public ?int $expiresAt = null,
    ) {
        if (preg_match(self::NAME_GRAMMAR, $name) !== 1) {
            throw new HttpException(
                'The cookie name must be an RFC 9110 token, '
                . "'" . $name . "' given.",
            );
        }

        self::assertValueGrammar($value);

        if (preg_match(self::PATH_GRAMMAR, $path) !== 1) {
            throw new HttpException(
                'The cookie path must be root-relative or empty, '
                . "'" . $path . "' given.",
            );
        }

        if ($domain !== '' && preg_match(self::DOMAIN_GRAMMAR, $domain) !== 1) {
            throw new HttpException(
                'The cookie domain must be a dot-separated domain or empty, '
                . "'" . $domain . "' given.",
            );
        }

        $foldedSameSite = mb_strtolower($sameSite);

        if (
            !isset(self::SAME_SITE_WORDS[$foldedSameSite])
            || self::SAME_SITE_WORDS[$foldedSameSite] !== $sameSite
        ) {
            throw new HttpException(
                'The cookie sameSite must be one of the SameSite vocabulary '
                . '(Strict, Lax, None), ' . "'" . $sameSite . "' given.",
            );
        }

        if ($maxAge !== null && $maxAge < 0) {
            throw new HttpException(
                'The cookie maxAge must not be negative, ' . $maxAge . ' given.',
            );
        }

        if ($expiresAt !== null && $expiresAt < 0) {
            throw new HttpException(
                'The cookie expiresAt must not be negative, ' . $expiresAt . ' given.',
            );
        }
    }

    /**
     * The deletion cookie: the name kept, the value emptied, the age
     * zeroed and the expiry pinned to the epoch -- the belt-and-braces
     * spelling that retires a cookie on every browser, including the
     * ones that predate Max-Age. The flags ride along because a
     * deletion the browser cannot match to the issuance it saw
     * (a different Path or Domain) does not delete anything.
     */
    public static function deleted(
        string $name,
        string $sameSite = self::SAME_SITE_LAX,
        bool $secure = true,
        bool $httpOnly = true,
    ): self {
        return new self(
            name: $name,
            value: '',
            sameSite: $sameSite,
            secure: $secure,
            httpOnly: $httpOnly,
            maxAge: 0,
            expiresAt: 0,
        );
    }

    /**
     * Renders the `Set-Cookie` header value for one request scheme.
     *
     * @param string $scheme the request's own scheme ('https' or
     *                       otherwise), which the Secure attribute's
     *                       emission rides
     */
    public function headerValue(string $scheme): string
    {
        $attributes = [$this->name . '=' . $this->value];

        if ($this->domain !== '') {
            $attributes[] = 'Domain=' . $this->domain;
        }

        if ($this->path !== '') {
            $attributes[] = 'Path=' . $this->path;
        }

        if ($this->expiresAt !== null) {
            $attributes[] = 'Expires=' . gmdate('D, d M Y H:i:s', $this->expiresAt) . ' GMT';
        }

        if ($this->maxAge !== null) {
            $attributes[] = 'Max-Age=' . $this->maxAge;
        }

        $attributes[] = 'SameSite=' . $this->sameSite;

        if ($this->secure && ($scheme === 'https' || $this->sameSite === self::SAME_SITE_NONE)) {
            $attributes[] = 'Secure';
        }

        if ($this->httpOnly) {
            $attributes[] = 'HttpOnly';
        }

        return implode('; ', $attributes);
    }

    /**
     * Holds the cookie-value grammar: the one existence check both
     * this constructor and the request-side write ({@see
     * RequestCookies::with()}) answer -- a value the cookie-octet
     * grammar refuses is a value no browser will carry back, so it is
     * named at the seam that would have rendered it.
     *
     * @throws HttpException when the value fails the cookie-octet
     *                       grammar
     */
    public static function assertValueGrammar(string $value): void
    {
        if (preg_match(self::VALUE_GRAMMAR, $value) !== 1) {
            throw new HttpException(
                'The cookie value must match the cookie-octet grammar '
                . '(no quotes, commas, semicolons, backslashes or control bytes).',
            );
        }
    }
}
