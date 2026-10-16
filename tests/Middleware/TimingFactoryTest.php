<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ResponseTime;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\TimingFactory;
use Zef\Test\Support\Http\FixedResponseHandler;

/**
 * The timing construction seam, on its own terms: the build from the
 * frozen subtree (an emitted header matching the two-decimal shape
 * the real monotonic clock renders), the absent family's inert
 * pass-through, and the two census halts -- the unknown family and
 * the unknown knob -- through the factory the profile's own service
 * registration names.
 *
 * @internal
 */
#[CoversClass(TimingFactory::class)]
final class TimingFactoryTest extends TestCase
{
    private FixedResponseHandler $fixedResponseHandler;

    #[Override]
    protected function setUp(): void
    {
        $this->fixedResponseHandler = new FixedResponseHandler(new Response(200, [], 'answered'));
    }

    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'observability' => [
                'timing' => [
                    'emit_header' => true,
                ],
            ],
        ]);

        $response = TimingFactory::fromConfig($config)
            ->process($this->request(), $this->fixedResponseHandler)
        ;

        self::assertMatchesRegularExpression(
            '/^\d+\.\d{2}ms$/D',
            $response->getHeaderLine(ResponseTime::HEADER),
            'the real monotonic clock renders the two-decimal shape',
        );
    }

    public function testTheFactoryBuildsTheDefaultInertPostureFromTheAbsentFamily(): void
    {
        $response = TimingFactory::fromConfig(new Config([]))
            ->process($this->request(), $this->fixedResponseHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertFalse($response->hasHeader(ResponseTime::HEADER));
    }

    public function testTheFactoryBuildsADisarmedPassThrough(): void
    {
        $config = new Config([
            'observability' => [
                'timing' => [
                    'enabled' => false,
                    'emit_header' => true,
                ],
            ],
        ]);

        $response = TimingFactory::fromConfig($config)
            ->process($this->request(), $this->fixedResponseHandler)
        ;

        self::assertFalse($response->hasHeader(ResponseTime::HEADER));
    }

    public function testTheFactoryHaltsOnAnUnknownFamily(): void
    {
        $config = new Config([
            'observability' => [
                'timming' => [],
            ],
        ]);

        try {
            TimingFactory::fromConfig($config);
            self::fail('An unknown observability family must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability configuration carries the unknown family 'timming'"
                . ' (known families: timing, tracing, correlation, export).',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsOnANonMapObservabilityRoot(): void
    {
        $config = new Config([
            'observability' => 'on',
        ]);

        try {
            TimingFactory::fromConfig($config);
            self::fail('A non-map observability root must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The observability configuration must be a map, string given.',
                $httpException->getMessage(),
            );
        }
    }

    public function testTheFactoryHaltsOnAnUnknownKnob(): void
    {
        $config = new Config([
            'observability' => [
                'timing' => [
                    'header' => true,
                ],
            ],
        ]);

        try {
            TimingFactory::fromConfig($config);
            self::fail('An unknown knob must halt the construction.');
        } catch (HttpException $httpException) {
            self::assertSame(
                "The observability.timing configuration carries the unknown knob 'header'"
                . ' (known knobs: enabled, emit_header).',
                $httpException->getMessage(),
            );
        }
    }

    /**
     * One request the built middleware measures.
     */
    private function request(): ServerRequest
    {
        return new ServerRequest('GET', new Uri('https://app.example.com/health'));
    }
}
