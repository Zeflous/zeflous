<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Config\ConfigException;
use Zef\Test\Support\Http\FileEntryMiddleware;
use Zef\Test\Support\Tooling\HttpExchange;
use Zef\Test\Support\Tooling\HttpInspectionBoot;

/**
 * The HTTP inspection commands' boot: the one seam the two CLI
 * commands share -- a configuration directory becomes the answered
 * exchange, simulated through the same composition the runtime rides
 * (the application orchestrator's own pipeline over the router's own
 * PSR-15 handler), so the middleware the fixture declared actually
 * ran and the security headers the response carries were actually
 * stamped. These tests pin the honest end-to-end answer, the typed
 * refusal a defective configuration surfaces, and the throwaway
 * artifact's own cleanup.
 *
 * @internal
 */
#[CoversClass(HttpInspectionBoot::class)]
#[CoversClass(HttpExchange::class)]
final class HttpInspectionBootTest extends TestCase
{
    private string $routedErrorLog;

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-http-inspection-' . uniqid('', false) . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->routedErrorLog);
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        if (is_file($this->routedErrorLog) && !unlink($this->routedErrorLog)) {
            throw new RuntimeException('Unable to remove the temporary error log.');
        }
    }

    public function testTheBootAnswersTheFixtureExchangeEndToEnd(): void
    {
        $exchange = HttpInspectionBoot::answered(
            $this->fixtureDirectory(),
            'GET',
            '/ping',
            'localhost',
            'http',
            [],
            '',
        );

        self::assertSame(200, $exchange->response->getStatusCode());
        self::assertSame('pong', (string) $exchange->response->getBody());
        self::assertSame('nosniff', $exchange->response->getHeaderLine('X-Content-Type-Options'));
        self::assertSame('http', $exchange->scheme());
        self::assertSame('GET', $exchange->request->getMethod());
        self::assertSame('/ping', $exchange->request->getRequestTarget());
        self::assertSame('localhost', $exchange->request->getUri()->getHost());
    }

    public function testTheBootAnswersASimulatedRequestWithHeadersAndABody(): void
    {
        $exchange = HttpInspectionBoot::answered(
            $this->fixtureDirectory(),
            'POST',
            '/mirror/spot',
            'localhost',
            'http',
            [['Accept', 'text/plain'], ['X-Probe', 'one'], ['X-Probe', 'two']],
            'the payload',
        );

        self::assertSame(201, $exchange->response->getStatusCode());
        self::assertSame('mirrored spot', (string) $exchange->response->getBody());
        self::assertSame(['one', 'two'], $exchange->request->getHeader('X-Probe'));
        self::assertSame('the payload', (string) $exchange->request->getBody());
        self::assertSame('text/plain', $exchange->request->getHeaderLine('Accept'));
    }

    public function testARoutingRefusalIsAnsweredByTheErrorBoundaryTheFixtureDeclared(): void
    {
        $exchange = HttpInspectionBoot::answered(
            $this->fixtureDirectory(),
            'GET',
            '/unmatched',
            'localhost',
            'http',
            [],
            '',
        );

        self::assertSame(404, $exchange->response->getStatusCode());
        self::assertStringContainsString(
            'application/problem+json',
            $exchange->response->getHeaderLine('Content-Type'),
        );
    }

    public function testTheHttpsSchemeCarriesTheHstsHeaderTheKnobArmed(): void
    {
        $exchange = HttpInspectionBoot::answered(
            $this->fixtureDirectory(),
            'GET',
            '/ping',
            'localhost',
            'https',
            [],
            '',
        );

        self::assertSame('https', $exchange->scheme());
        self::assertSame(
            'max-age=31536000; includeSubDomains',
            $exchange->response->getHeaderLine('Strict-Transport-Security'),
        );
    }

    public function testADefectiveConfigurationSurfacesTheBootTypedRefusal(): void
    {
        $configDirectory = sys_get_temp_dir() . '/zef-http-boot-' . uniqid('', false);
        mkdir($configDirectory, 0o777, true);
        file_put_contents(
            $configDirectory . '/routes.php',
            '<?php return [["path" => "no-slash", "method" => ["GET"], "handler" => "X::y"]];',
        );

        // The marker-armed pipeline: an outermost middleware whose entry
        // the boot's own doctrine forbids -- a defective table refuses
        // at the boot seam, never from inside a pipeline that already
        // started.
        $markerFile = $configDirectory . '/marker.log';
        file_put_contents(
            $configDirectory . '/services.php',
            '<?php return ["marker" => ["class" => '
            . var_export(FileEntryMiddleware::class, true)
            . ', "arguments" => [' . var_export($markerFile, true) . ']]];',
        );
        file_put_contents(
            $configDirectory . '/middleware.php',
            '<?php return ["stack" => [["service" => "marker", "priority" => 2000]]];',
        );

        try {
            HttpInspectionBoot::answered($configDirectory, 'GET', '/ping', 'localhost', 'http', [], '');
            self::fail("A defective configuration must surface the boot's typed refusal.");
        } catch (ConfigException $configException) {
            self::assertStringContainsString(
                'The route path "no-slash" must start with a slash.',
                $configException->getMessage(),
            );
        } finally {
            self::assertFileDoesNotExist($markerFile, 'The boot must refuse before the pipeline ever ran.');

            unlink($configDirectory . '/routes.php');
            unlink($configDirectory . '/services.php');
            unlink($configDirectory . '/middleware.php');
            rmdir($configDirectory);
        }
    }

    public function testTheThrowawayArtifactNeverOutlivesTheBoot(): void
    {
        $before = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
        self::assertIsArray($before);

        HttpInspectionBoot::answered(
            $this->fixtureDirectory(),
            'GET',
            '/ping',
            'localhost',
            'http',
            [],
            '',
        );

        $after = glob(sys_get_temp_dir() . '/zef-container-inventory-*');
        self::assertIsArray($after);
        self::assertSame($before, $after);
    }

    /**
     * @return non-empty-string
     */
    private function fixtureDirectory(): string
    {
        return \dirname(__DIR__, 2) . '/tests/Scripts/fixtures/http-app';
    }
}
