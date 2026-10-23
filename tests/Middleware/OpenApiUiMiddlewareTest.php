<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Zef\Framework\Export\ExportException;
use Zef\Framework\Export\ExportJson;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\OpenApiUiMarkup;
use Zef\Framework\Middleware\OpenApiUiMiddleware;
use Zef\Framework\Router\Router;
use Zef\Test\Support\Http\FixedResponseHandler;
use Zef\Test\Support\Tooling\RouteOpenApiExport;

/**
 * The ecosystem export's development endpoint, on its own terms: the
 * double fail-closed gate (the environment verdict answers the
 * endpoints' existence, so a non-development environment is a
 * pass-through whatever the composition carries), the two exact-match
 * paths the family owns (the spec document with its content type, the
 * UI shell with its asset base and its escaped title), the
 * construction-time grammar (a defective wiring halts the boot, never
 * the first request), and the delegation every other path receives.
 *
 * @internal
 */
#[CoversClass(OpenApiUiMiddleware::class)]
#[CoversClass(OpenApiUiMarkup::class)]
#[CoversClass(ExportJson::class)]
final class OpenApiUiMiddlewareTest extends TestCase
{
    private false|string $previousEnvironment;

    #[Override]
    protected function setUp(): void
    {
        $this->previousEnvironment = getenv('APP_ENV');
        putenv('APP_ENV=development');
    }

    #[Override]
    protected function tearDown(): void
    {
        if ($this->previousEnvironment === false) {
            putenv('APP_ENV');
        } else {
            putenv('APP_ENV=' . $this->previousEnvironment);
        }
    }

    public function testTheSpecPathAnswersTheDocumentWithItsContentType(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/openapi.json'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('application/json', $response->getHeaderLine('Content-Type'));

        $document = json_decode((string) $response->getBody(), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($document);
        self::assertSame('3.1.0', $document['openapi']);
        self::assertSame(['title' => 'Fixture API', 'version' => '2.5.0'], $document['info']);
        $paths = $this->mapAt($document['paths']);
        self::assertSame(['/users/{id}', '/users'], array_keys($paths));
    }

    public function testTheUiPathAnswersTheShellWithItsAssetAndTitle(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/openapi/ui'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        self::assertStringContainsString('<title>Fixture API</title>', $body);
        self::assertStringContainsString('https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js', $body);
        self::assertStringContainsString("url: '/openapi.json'", $body);
    }

    public function testTheShellDocumentIsPinnedVerbatim(): void
    {
        // The golden shell: the exact document the fold answers over one
        // title and one asset base, pinned verbatim so every concat
        // mutant (an operand dropped, a fragment spliced differently)
        // dies on the literal it no longer spells.
        $expected = <<<'HTML'
            <!doctype html>
            <html lang="en">
            <head>
                <meta charset="utf-8">
                <meta name="viewport" content="width=device-width, initial-scale=1">
                <title>Fixture API</title>
                <link rel="stylesheet" href="https://unpkg.com/swagger-ui-dist@5/swagger-ui.css">
            </head>
            <body>
                <div id="swagger-ui"></div>
                <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-bundle.js"></script>
                <script src="https://unpkg.com/swagger-ui-dist@5/swagger-ui-standalone-preset.js"></script>
                <script>
                    window.onload = function () {
                        window.ui = SwaggerUIBundle({
                            url: '/openapi.json',
                            dom_id: '#swagger-ui',
                            presets: [
                                SwaggerUIBundle.presets.apis,
                                SwaggerUIStandalonePreset
                            ],
                            layout: 'StandaloneLayout'
                        });
                    };
                </script>
            </body>
            </html>

            HTML;

        self::assertSame(
            $expected,
            OpenApiUiMarkup::shell('Fixture API', 'https://unpkg.com/swagger-ui-dist@5/'),
        );
    }

    public function testTheTitleIsEscapedIntoTheShell(): void
    {
        // The title's angle brackets AND double quotes both escape:
        // ENT_QUOTES is what escapes the quotes, so a flags operand
        // that loses it leaves the quotes bare.
        $openApiUiMiddleware = new OpenApiUiMiddleware(
            $this->router(),
            'Fixture <script> "quoted" API',
            '2.5.0',
        );

        $response = $openApiUiMiddleware->process(
            $this->request('GET', '/openapi/ui'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertStringContainsString(
            '<title>Fixture &lt;script&gt; &quot;quoted&quot; API</title>',
            (string) $response->getBody(),
        );
    }

    public function testTheSelfHostedAssetBaseIsSpelled(): void
    {
        $openApiUiMiddleware = new OpenApiUiMiddleware(
            $this->router(),
            'Fixture API',
            '2.5.0',
            'https',
            'https://assets.internal.example.com/swagger-ui-dist/',
        );

        $response = $openApiUiMiddleware->process(
            $this->request('GET', '/openapi/ui'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertStringContainsString(
            'https://assets.internal.example.com/swagger-ui-dist/swagger-ui.css',
            (string) $response->getBody(),
        );
    }

    public function testEveryOtherPathDelegates(): void
    {
        $delegated = $this->response(202);

        $response = $this->middleware()->process(
            $this->request('GET', '/users'),
            new FixedResponseHandler($delegated),
        );

        self::assertSame($delegated, $response);
    }

    public function testANonDevelopmentEnvironmentIsAPassThrough(): void
    {
        putenv('APP_ENV=production');

        $delegated = $this->response(202);

        foreach (['/openapi.json', '/openapi/ui', '/users'] as $path) {
            $response = $this->middleware()->process(
                $this->request('GET', $path),
                new FixedResponseHandler($delegated),
            );

            self::assertSame($delegated, $response, $path . ' must delegate in production.');
        }
    }

    public function testTheConstructionGrammarRefusesTheIdentity(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The OpenAPI info.title must be non-empty');

        new OpenApiUiMiddleware($this->router(), '', '2.5.0');
    }

    public function testTheConstructionGrammarRefusesTheScheme(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The OpenAPI server scheme must be one of');

        new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'ftp');
    }

    public function testTheConstructionGrammarRefusesAnEmptyAsset(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Swagger UI asset URL must be a non-empty https URL base.');

        new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'https', '');
    }

    public function testTheConstructionGrammarRefusesANonHttpsAsset(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Swagger UI asset URL must be a non-empty https URL base.');

        new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'https', 'http://cdn.example.com/');
    }

    public function testTheConstructionGrammarRefusesAnOversizeAsset(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Swagger UI asset URL must be a non-empty https URL base.');

        new OpenApiUiMiddleware(
            $this->router(),
            'Fixture API',
            '2.5.0',
            'https',
            'https://' . str_repeat('a', 2041) . '/',
        );
    }

    public function testTheAssetCeilingIsPinnedAtItsBoundary(): void
    {
        // The character boundary itself: a 2048-character asset
        // base is an asset, a 2049-character one is a refusal -- the
        // mutant that shifts the ceiling dies on the boundary it no
        // longer spells.
        $exactly = 'https://' . str_repeat('a', 2048 - 8 - 1) . '/';

        self::assertSame(2048, mb_strlen($exactly));

        $openApiUiMiddleware = new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'https', $exactly);

        $response = $openApiUiMiddleware->process(
            $this->request('GET', '/openapi/ui'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertStringContainsString($exactly . 'swagger-ui.css', (string) $response->getBody());

        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Swagger UI asset URL must be a non-empty https URL base.');

        new OpenApiUiMiddleware(
            $this->router(),
            'Fixture API',
            '2.5.0',
            'https',
            'https://' . str_repeat('a', 2048 - 8) . '/',
        );
    }

    public function testTheAssetCeilingCountsCharactersNotBytes(): void
    {
        // A 1029-character two-byte-per-character asset base is 2058
        // bytes but 1029 characters: the character fold accepts it,
        // the byte fold refuses it, and the pin is the character
        // semantics the grammar spells.
        $multibyte = 'https://' . str_repeat('é', 1020) . '/';

        $openApiUiMiddleware = new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'https', $multibyte);

        $response = $openApiUiMiddleware->process(
            $this->request('GET', '/openapi/ui'),
            new FixedResponseHandler($this->response(500)),
        );

        self::assertStringContainsString($multibyte . 'swagger-ui.css', (string) $response->getBody());
    }

    public function testTheConstructionGrammarRefusesAControlByteAsset(): void
    {
        $this->expectException(ExportException::class);
        $this->expectExceptionMessageIsOrContains('The Swagger UI asset URL must be a non-empty https URL base.');

        new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0', 'https', "https://cdn\x1f.example.com/");
    }

    public function testTheServedSpecIsTheCliAnswersDocument(): void
    {
        $response = $this->middleware()->process(
            $this->request('GET', '/openapi.json'),
            new FixedResponseHandler($this->response(500)),
        );

        $expected = RouteOpenApiExport::rendered(
            $this->router(),
            'Fixture API',
            '2.5.0',
            'https',
        );

        self::assertSame($expected, (string) $response->getBody());
    }

    private function middleware(): OpenApiUiMiddleware
    {
        return new OpenApiUiMiddleware($this->router(), 'Fixture API', '2.5.0');
    }

    private function router(): Router
    {
        return Router::fromRoutes([
            [
                'path' => '/users/{id}',
                'method' => ['GET'],
                'handler' => 'UserController::show',
                'name' => 'users.show',
                'constraints' => ['id' => '\d+'],
            ],
            ['path' => '/users', 'method' => ['GET', 'POST'], 'handler' => 'UserController::index'],
        ]);
    }

    private function request(string $method, string $path): ServerRequest
    {
        return new ServerRequest($method, new Uri('https://api.example.com' . $path));
    }

    private function response(int $status): ResponseInterface
    {
        return new Response($status);
    }

    /**
     * One document node the assertions read containers through,
     * shaped to the map it must be before any offset touches it.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        self::assertIsArray($node);

        return $node;
    }
}
