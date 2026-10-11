<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Override;
use Psr\Http\Message\UriInterface;

/**
 * The `with*` mutation protocol of the PSR-7 URI.
 *
 * Every method returns a *new* {@see Uri} instance with one component
 * replaced (CONTRIBUTING.md rule 3): the value object stays immutable, and
 * the component array is a value copy, so a clone plus one assignment is a
 * complete copy-on-write. Guards and encoding stay in the grammar
 * collaborators; this trait only binds them to the components.
 */
trait UriMutations
{
    /**
     * @throws HttpException when the scheme carries control characters or
     *                       its folded form is not a valid RFC 3986 scheme
     */
    #[Override]
    public function withScheme(string $scheme): UriInterface
    {
        $scheme = UriGrammar::normalizeScheme($scheme);
        $new = clone $this;
        $new->components['scheme'] = $scheme;

        return $new;
    }

    /**
     * @throws HttpException when the user info carries control characters
     */
    #[Override]
    public function withUserInfo(string $user, ?string $password = null): UriInterface
    {
        UriGrammar::assertNoControls($user, 'URI user info');
        if ($password !== null) {
            UriGrammar::assertNoControls($password, 'URI user info');
        }

        $new = clone $this;
        $new->components['userInfo'] = UriEncoder::encodeUserInfo($user, $password);

        return $new;
    }

    /**
     * @throws HttpException when the host is not a valid reg-name, IPv4 or
     *                       IPv6 literal
     */
    #[Override]
    public function withHost(string $host): UriInterface
    {
        $host = UriHostGrammar::sanitize($host);
        $new = clone $this;
        $new->components['host'] = $host;

        return $new;
    }

    /**
     * @throws HttpException When the port falls outside 1..65535.
     */
    #[Override]
    public function withPort(?int $port): UriInterface
    {
        UriGrammar::assertPort($port);
        $new = clone $this;
        $new->components['port'] = $port;

        return $new;
    }

    /**
     * @throws HttpException when the path carries control characters
     */
    #[Override]
    public function withPath(string $path): UriInterface
    {
        UriGrammar::assertNoControls($path, 'URI path');
        $new = clone $this;
        $new->components['path'] = UriEncoder::encodeComponent($path, UriEncoder::PATH_ALLOWED);

        return $new;
    }

    /**
     * @throws HttpException when the query carries control characters
     */
    #[Override]
    public function withQuery(string $query): UriInterface
    {
        UriGrammar::assertNoControls($query, 'URI query');
        $new = clone $this;
        $new->components['query'] = UriEncoder::encodeComponent(
            $query,
            UriEncoder::QUERY_FRAGMENT_ALLOWED,
        );

        return $new;
    }

    /**
     * @throws HttpException when the fragment carries control characters
     */
    #[Override]
    public function withFragment(string $fragment): UriInterface
    {
        UriGrammar::assertNoControls($fragment, 'URI fragment');
        $new = clone $this;
        $new->components['fragment'] = UriEncoder::encodeComponent(
            $fragment,
            UriEncoder::QUERY_FRAGMENT_ALLOWED,
        );

        return $new;
    }
}
