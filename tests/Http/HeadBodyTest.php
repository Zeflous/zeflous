<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HeadBody;

/**
 * The HEAD answer's own body, on its own terms: every member the
 * emitter, the reconciler, and a verbatim forwarder could ask of a
 * stream answers the view's own doctrine -- the GET twin's length,
 * no content at all, the cursor at rest (issue #801's finding 1).
 *
 * @internal
 */
#[CoversClass(HeadBody::class)]
final class HeadBodyTest extends TestCase
{
    public function testTheSizeAnswersTheGetTwinsLength(): void
    {
        self::assertSame(28, new HeadBody(28)->getSize());
        self::assertSame(0, new HeadBody(0)->getSize());
    }

    public function testEveryReadAnswersNothingAtEndOfStream(): void
    {
        $headBody = new HeadBody(28);

        self::assertTrue($headBody->eof(), 'the view rests at its end: nothing is ever readable');
        self::assertSame('', $headBody->read(8192), 'no octet ever leaves the view');
        self::assertSame('', $headBody->getContents(), 'the whole contents are the empty string');
        self::assertSame('', (string) $headBody, 'the cast never materialises content either');
    }

    public function testTheCursorRestsAtZeroForTheEmittersRewindPump(): void
    {
        $headBody = new HeadBody(28);

        self::assertSame(0, $headBody->tell());
        self::assertTrue($headBody->isSeekable(), 'the emitter rewinds the body before its pump');

        $headBody->rewind();
        self::assertSame(0, $headBody->tell(), 'the rewind is the no-op the resting cursor spells');

        $headBody->seek(4, \SEEK_SET);
        self::assertSame(0, $headBody->tell(), "any seek lands at zero too, the empty stream's own semantics");
    }

    public function testTheViewIsAReadOnlyStream(): void
    {
        $headBody = new HeadBody(28);

        self::assertFalse($headBody->isWritable());
        self::assertTrue($headBody->isReadable());
        self::assertSame(0, $headBody->write('payload'), 'no octet ever enters the view');
    }

    public function testTheDetachedViewCarriesNoEngineResource(): void
    {
        $headBody = new HeadBody(28);

        self::assertNull($headBody->detach());
        self::assertNull($headBody->getMetadata());
        self::assertNull($headBody->getMetadata('uri'));

        $headBody->close();
        self::assertSame(28, $headBody->getSize(), 'the close holds no engine resource to release');
    }
}
