<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\SlowRequestFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The slow-request construction seam, on its own terms: the build
 * from the frozen subtree (a slow request against a one-millisecond
 * bar landing its record in the deployment's structured log
 * address), the unconfigured address falling back to the SAPI error
 * log the record's own writer carries, the absent family's answered
 * request, and the halts -- the log address's type guard, the
 * unknown family and the unknown knob -- through the factory the
 * profile's own service registration names.
 *
 * @internal
 */
#[CoversClass(SlowRequestFactory::class)]
final class SlowRequestFactoryTest extends TestCase
{
    private string $logPath = '';

    #[Override]
    protected function setUp(): void
    {
        $this->logPath = sys_get_temp_dir() . '/zef-slow-request-' . uniqid() . '.jsonl';
    }

    #[Override]
    protected function tearDown(): void
    {
        if (!is_file($this->logPath)) {
            return;
        }

        unlink($this->logPath);
    }

    public function testTheFactoryBuildsTheBoundaryOverTheStructuredLogAddress(): void
    {
        $config = new Config([
            'http' => [
                'error_handling' => [
                    'log_path' => $this->logPath,
                ],
            ],
            'observability' => [
                'slow_request' => [
                    'threshold_ms' => 1,
                ],
            ],
        ]);

        SlowRequestFactory::fromConfig($config)
            ->process($this->request(), $this->sleepingHandler())
        ;

        self::assertFileExists($this->logPath, "the record landed in the deployment's one structured log address");

        $record = json_decode((string) file_get_contents($this->logPath), true, 3, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('warning', $record['level'] ?? null);
        self::assertSame('Slow request', $record['message'] ?? null);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame('req-123', $context['request_id'] ?? null);
        self::assertSame('GET', $context['method'] ?? null);
        self::assertSame('/health', $context['path'] ?? null);
        self::assertSame(1, $context['threshold_ms'] ?? null);
        $elapsedMs = $context['elapsed_ms'] ?? null;
        self::assertIsFloat($elapsedMs);
        self::assertGreaterThan(0.0, $elapsedMs, 'a three-millisecond handler exceeded the one-millisecond bar');
    }

    public function testTheUnconfiguredAddressFallsBackToTheSapiErrorLog(): void
    {
        $routedErrorLog = sys_get_temp_dir() . '/zef-slow-request-errorlog-' . uniqid() . '.log';
        $previousErrorLog = (string) ini_set('error_log', $routedErrorLog);

        try {
            $config = new Config([
                'observability' => [
                    'slow_request' => [
                        'threshold_ms' => 1,
                    ],
                ],
            ]);

            SlowRequestFactory::fromConfig($config)
                ->process($this->request(), $this->sleepingHandler())
            ;

            self::assertFileExists($routedErrorLog, "the record's own writer carried the SAPI fallback");

            $contents = (string) file_get_contents($routedErrorLog);
            $jsonStart = mb_strpos($contents, '{"timestamp"');
            self::assertIsInt($jsonStart, 'The fallback record must carry its JSON document.');

            $record = json_decode(mb_substr($contents, $jsonStart), true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($record);
            self::assertSame('Slow request', $record['message'] ?? null);
            $context = $record['context'] ?? null;
            self::assertIsArray($context);
            self::assertSame(1, $context['threshold_ms'] ?? null);
        } finally {
            ini_set('error_log', $previousErrorLog);

            if (is_file($routedErrorLog)) {
                unlink($routedErrorLog);
            }
        }
    }

    public function testTheAbsentFamilyAnswersTheRequest(): void
    {
        $response = SlowRequestFactory::fromConfig(new Config([]))
            ->process($this->request(), new FixedResponseHandler(new Response(200, [], 'answered')))
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame('answered', (string) $response->getBody());
    }

    public function testTheLogAddressMustBeAString(): void
    {
        try {
            SlowRequestFactory::fromConfig(new Config([
                'http' => [
                    'error_handling' => [
                        'log_path' => false,
                    ],
                ],
            ]));
            self::fail('A wrong-typed log address must halt the build.');
        } catch (ConfigException $configException) {
            self::assertSame('http.error_handling.log_path must be a string.', $configException->getMessage());
        }
    }

    public function testTheUnknownFamilyHaltsTheBoot(): void
    {
        try {
            SlowRequestFactory::fromConfig(new Config([
                'observability' => [
                    'slow_requst' => [],
                ],
            ]));
            self::fail('An unknown observability family must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'slow_requst'"
                . ' (known families: timing, tracing, correlation, export, metrics, router, slow_request).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheUnknownKnobHaltsTheBoot(): void
    {
        try {
            SlowRequestFactory::fromConfig(new Config([
                'observability' => [
                    'slow_request' => [
                        'threshold' => 2500,
                    ],
                ],
            ]));
            self::fail('An unknown knob must halt the boot.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.slow_request configuration carries the unknown knob 'threshold'"
                . ' (known knobs: enabled, threshold_ms).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * The handler a one-millisecond bar judges: a deterministic
     * three-millisecond sleep, the slow request the factory's own
     * real monotonic clock measures.
     */
    private function sleepingHandler(): RequestHandlerInterface
    {
        return new class implements RequestHandlerInterface {
            #[Override]
            public function handle(ServerRequestInterface $serverRequest): ResponseInterface
            {
                usleep(3000);

                return new Response(200, [], 'answered');
            }
        };
    }

    /**
     * The request every record judges: a claim the response echoes.
     */
    private function request(): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('https://app.example.com/health'),
            headers: ['X-Request-ID' => 'req-123'],
        );
    }
}
