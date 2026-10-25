<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

use Psr\Http\Message\ServerRequestInterface;
use Zef\Framework\ApplicationBootstrapper;
use Zef\Framework\Container\Aot\ContainerAotCache;
use Zef\Framework\Http\Psr17Factory;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\RouterRequestHandler;
use Zef\Framework\Router\Router;

/**
 * The HTTP inspection commands' boot: the one seam `bin/zef http:dump`
 * and `http:headers` share -- a configuration directory becomes the
 * answered exchange those commands render from, booted through the
 * same application orchestrator the runtime rides ({@see
 * ApplicationBootstrapper}, the route inspection commands' own
 * doctrine: never a parallel parse), and then SIMULATED through the
 * same composition the runtime rides -- the router's own PSR-15
 * handler ({@see RouterRequestHandler}) inside the pipeline the
 * orchestrator builds over the `middleware.stack` the host declared.
 *
 * The simulation is therefore end-to-end truth: the middleware the
 * application declared actually ran, the terminal handler the route
 * named actually answered, and the response the commands render is
 * the response the wire would carry. What the boot owns beyond the
 * route inspection seam is the request construction -- a synthetic
 * `ServerRequest` over the caller's method, path, host, scheme,
 * header pairs and body, carrying no server params because a CLI
 * request genuinely has no SAPI context to invent -- and the
 * container AOT artifact, wired to the throwaway file the analysis
 * CLIs share ({@see ContainerAnalysisArtifact}) and never allowed to
 * outlive the boot.
 */
final readonly class HttpInspectionBoot
{
    /**
     * Boots the configuration directory's application and answers the
     * simulated exchange.
     *
     * @param non-empty-string                                $configDirectory the host's configuration directory
     *                                                                         (the `app/configs` layout)
     * @param non-empty-string                                $method          the request method
     * @param non-empty-string                                $path            the request path, origin-form
     * @param non-empty-string                                $host            the request host
     * @param non-empty-string                                $scheme          'http' or 'https'
     * @param list<array{non-empty-string, non-empty-string}> $headers         the request's header pairs, in
     *                                                                         declaration order (a repeated
     *                                                                         name answers a repeated field)
     * @param string                                          $body            the request body, empty for none
     */
    public static function answered(
        string $configDirectory,
        string $method,
        string $path,
        string $host,
        string $scheme,
        array $headers,
        string $body,
    ): HttpExchange {
        $artifactFile = ContainerAnalysisArtifact::path();

        try {
            $applicationBootstrapper = new ApplicationBootstrapper(
                containerAotCache: new ContainerAotCache($artifactFile),
            );

            $kernel = $applicationBootstrapper->boot($configDirectory);

            $router = Router::fromConfig($kernel->config());

            // The boot's own validation, the route inspection seam's own
            // doctrine: the lazy radix compilation runs once, here, so a
            // defective table refuses at the boot seam -- the inspection
            // commands never answer from a router whose table has not
            // been judged yet.
            $router->tree();

            $request = self::request($method, $path, $host, $scheme, $headers, $body);

            $pipeline = $applicationBootstrapper->pipeline(
                $kernel,
                new RouterRequestHandler($router, $kernel->container(), new Psr17Factory()),
            );

            return new HttpExchange($request, $pipeline->handle($request));
        } finally {
            if (is_file($artifactFile)) {
                unlink($artifactFile);
            }
        }
    }

    /**
     * The synthetic request: no server params, no cookie, query,
     * upload or parsed-body context -- a CLI request has no SAPI
     * context to invent, and the dump's honesty depends on carrying
     * only what the caller actually spelled.
     *
     * @param non-empty-string                                $method  the request method
     * @param non-empty-string                                $path    the request path, origin-form
     * @param non-empty-string                                $host    the request host
     * @param non-empty-string                                $scheme  'http' or 'https'
     * @param list<array{non-empty-string, non-empty-string}> $headers the request's header pairs
     * @param string                                          $body    the request body, empty for none
     */
    private static function request(
        string $method,
        string $path,
        string $host,
        string $scheme,
        array $headers,
        string $body,
    ): ServerRequestInterface {
        $fields = [];

        foreach ($headers as [$name, $value]) {
            $fields[$name][] = $value;
        }

        return new ServerRequest(
            $method,
            new Uri($scheme . '://' . $host . $path),
            [],
            [],
            [],
            [],
            null,
            $fields,
            $body === '' ? null : Stream::fromString($body),
        );
    }
}
