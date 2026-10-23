<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use Override;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\AppEnvironment;
use Zef\Framework\Export\ExportException;
use Zef\Framework\Export\ExportJson;
use Zef\Framework\Export\OpenApiDocument;
use Zef\Framework\Http\Response;
use Zef\Framework\Router\Router;

/**
 * The ecosystem export's development endpoint: the PSR-15 boundary
 * that serves the route table's own OpenAPI document and the Swagger
 * UI shell that renders it -- the roadmap's "Swagger UI integration,
 * dev-only" made a middleware, so a host's development stack answers
 * `/openapi.json` and `/openapi/ui` from the same compiled table the
 * runtime dispatches over.
 *
 * THE DISCLOSURE IS DOUBLE-GATED, fail-closed both ways -- the
 * disclosure family's own replayed ({@see RouteDebugMiddleware}): the
 * composition arm is the host's own composition root (the middleware
 * is constructed with the identity and asset the deployment chooses,
 * and a stack that does not carry it serves nothing, whatever the
 * environment), and the environment arm is the verdict itself
 * ({@see AppEnvironment::isDevelopment()}, re-judged per request: an
 * unset or unrecognized `APP_ENV` answers not-development, so no
 * spelling can accidentally serve the endpoint in production). In a
 * non-development environment the middleware is a pass-through -- the
 * endpoints do not exist, and the request travels to the router like
 * any other.
 *
 * THE DOCUMENT IS THE SAME ONE THE CLI ANSWERS WITH: the middleware
 * renders the router's own compiled table through
 * {@see OpenApiDocument} once, at construction (the table is frozen
 * behind the router's memoized tree), so the served spec and
 * `bin/zef route:openapi` can never drift apart -- one rendering, two
 * carriers. The grammar the construction carries (the identity, the
 * scheme, the asset URL) refuses at composition time, so a defective
 * wiring halts the boot rather than the first request.
 *
 * The two paths are the family's own, exact-match only: `/openapi.json`
 * answers the spec (`application/json`), `/openapi/ui` the shell
 * (`text/html`). A route table that declares the same paths wins
 * nothing in development (the disclosure serves first) and serves
 * normally in production -- the precedence is the double gate's own.
 *
 * @api the development composition's own seam: construct it with the
 *      identity the deployment documents under
 */
final readonly class OpenApiUiMiddleware implements MiddlewareInterface
{
    /** The specification document's path. */
    public const string SPEC_PATH = '/openapi.json';

    /** The Swagger UI shell's path. */
    public const string UI_PATH = '/openapi/ui';

    /** The default Swagger UI asset base: the unpkg CDN, major-pinned. */
    public const string DEFAULT_ASSET_URL = 'https://unpkg.com/swagger-ui-dist@5/';

    private string $specJson;

    private string $uiHtml;

    /**
     * @throws ExportException when the identity or scheme grammar refuses,
     *                         or the table's enrichment declaration refuses
     */
    public function __construct(
        Router $router,
        string $title,
        string $version,
        string $scheme = 'https',
        string $assetUrl = self::DEFAULT_ASSET_URL,
    ) {
        $openApiDocument = OpenApiDocument::fromRoutes($router->tree()->routes(), $title, $version, $scheme);
        $this->specJson = ExportJson::encode($openApiDocument->toArray());
        $this->uiHtml = OpenApiUiMarkup::shell($title, $this->validatedAssetUrl($assetUrl));
    }

    #[Override]
    public function process(
        ServerRequestInterface $serverRequest,
        RequestHandlerInterface $requestHandler,
    ): ResponseInterface {
        if (!AppEnvironment::isDevelopment()) {
            return $requestHandler->handle($serverRequest);
        }

        $path = $serverRequest->getUri()->getPath();

        if ($path === self::SPEC_PATH) {
            return new Response(
                200,
                ['Content-Type' => 'application/json'],
                $this->specJson,
            );
        }

        if ($path === self::UI_PATH) {
            return new Response(
                200,
                ['Content-Type' => 'text/html; charset=utf-8'],
                $this->uiHtml,
            );
        }

        return $requestHandler->handle($serverRequest);
    }

    /**
     * @return non-empty-string
     *
     * @throws ExportException when the asset URL is not an https URL base
     */
    private function validatedAssetUrl(string $assetUrl): string
    {
        if ($assetUrl === '') {
            throw new ExportException(
                'The Swagger UI asset URL must be a non-empty https URL base.',
            );
        }

        if (
            mb_strlen($assetUrl) > 2048
            || preg_match('/[\x00-\x1f\x7f]/', $assetUrl) === 1
            || !str_starts_with($assetUrl, 'https://')
        ) {
            throw new ExportException(
                'The Swagger UI asset URL must be a non-empty https URL base.',
            );
        }

        return $assetUrl;
    }
}
