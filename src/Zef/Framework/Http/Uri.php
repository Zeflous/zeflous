<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\UriInterface;

/**
 * The zero-library PSR-7 URI: an immutable RFC 3986 component holder.
 *
 * The value object owns the components; parsing lives in {@see UriParser},
 * the assertions in {@see UriGrammar} and {@see UriHostGrammar}, the
 * percent-encoding in {@see UriEncoder}, the rendering in {@see UriString}
 * and {@see UriAuthority}, and the `with*` protocol in the
 * {@see UriMutations} trait -- the same depth-split the radix engine and
 * the configuration repository use to stay under the architecture gate's
 * difficulty budget. An explicit port equal to the scheme's default is
 * reported as null and omitted from the rendered authority, while the
 * stored value keeps it so a later withScheme() flip re-exposes a now
 * non-default port.
 *
 * @api
 */
final class Uri implements UriInterface
{
    use UriMutations;

    /** @var array{scheme: string, userInfo: string, host: string, port: null|int, path: string, query: string, fragment: string} */
    private array $components;

    public function __construct(string $uri = '')
    {
        $this->components = UriParser::parse($uri);
    }

    #[Override]
    public function __toString(): string
    {
        return UriString::render($this->components);
    }

    #[Override]
    public function getScheme(): string
    {
        return $this->components['scheme'];
    }

    #[Override]
    public function getAuthority(): string
    {
        return UriAuthority::render($this->components);
    }

    #[Override]
    public function getUserInfo(): string
    {
        return $this->components['userInfo'];
    }

    #[Override]
    public function getHost(): string
    {
        return $this->components['host'];
    }

    #[Override]
    public function getPort(): ?int
    {
        $port = $this->components['port'];
        if ($port === null || UriGrammar::isDefaultPortForScheme($this->components['scheme'], $port)) {
            return null;
        }

        return $port;
    }

    #[Override]
    public function getPath(): string
    {
        return $this->components['path'];
    }

    #[Override]
    public function getQuery(): string
    {
        return $this->components['query'];
    }

    #[Override]
    public function getFragment(): string
    {
        return $this->components['fragment'];
    }
}
