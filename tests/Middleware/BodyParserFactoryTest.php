<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\Config;
use Zef\Framework\Http\MalformedBodyException;
use Zef\Framework\Http\Response;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Stream;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\BodyParserFactory;
use Zef\Test\Support\Http\RecordingHandler;

/**
 * The body-parser construction seam, on its own terms: the build from
 * the frozen subtree (a decorated request whose depth refusal answers
 * at the KNOB the subtree declared, not at the constructor default),
 * the absent family's armed default posture, and the disarmed
 * pass-through -- through the factory the profile's own
 * `http.body_parser` service registration names.
 *
 * The census halts (the unknown family, the non-map root, the unknown
 * knob) are the family's own and are pinned where they live ({@see
 * \Zef\Test\Http\BodyParserPolicyTest}); this test pins the wiring the
 * factory alone owns: the subtree read and the policy handed over.
 *
 * @internal
 */
#[CoversClass(BodyParserFactory::class)]
final class BodyParserFactoryTest extends TestCase
{
    private RecordingHandler $recordingHandler;

    #[Override]
    protected function setUp(): void
    {
        $this->recordingHandler = new RecordingHandler(new Response(200));
    }

    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'http' => [
                'body_parser' => [
                    'json_depth' => 2,
                ],
            ],
        ]);

        $response = BodyParserFactory::fromConfig($config)
            ->process($this->jsonRequest('{"order": 7}'), $this->recordingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['order' => 7], $this->recordingHandler->observed()->getParsedBody());
    }

    public function testTheFactoryCarriesTheSubtreesOwnDepthCeilingNotTheDefault(): void
    {
        $config = new Config([
            'http' => [
                'body_parser' => [
                    'json_depth' => 2,
                ],
            ],
        ]);

        $middleware = BodyParserFactory::fromConfig($config);

        $this->expectException(MalformedBodyException::class);
        $this->expectExceptionMessageIsOrContains('Maximum stack depth exceeded');

        $middleware->process($this->jsonRequest('{"a": {"b": {"c": 1}}}'), $this->recordingHandler);
    }

    public function testTheFactoryBuildsTheDefaultArmedPostureFromTheAbsentFamily(): void
    {
        $response = BodyParserFactory::fromConfig(new Config([]))
            ->process($this->jsonRequest('{"order": 7}'), $this->recordingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertSame(['order' => 7], $this->recordingHandler->observed()->getParsedBody());
    }

    public function testTheFactoryBuildsADisarmedPassThrough(): void
    {
        $config = new Config([
            'http' => [
                'body_parser' => [
                    'enabled' => false,
                ],
            ],
        ]);

        BodyParserFactory::fromConfig($config)
            ->process($this->jsonRequest('{"order": 7}'), $this->recordingHandler)
        ;

        self::assertNull(
            $this->recordingHandler->observed()->getParsedBody(),
            'a disabled family is the inert pass-through',
        );
    }

    /**
     * One JSON request the built middleware owns, carrying the body.
     */
    private function jsonRequest(string $body): ServerRequest
    {
        return new ServerRequest(
            'POST',
            new Uri('https://app.example.com/orders'),
            headers: ['Content-Type' => 'application/json'],
            stream: Stream::fromString($body),
        );
    }
}
