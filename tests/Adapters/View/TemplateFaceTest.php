<?php

declare(strict_types=1);

namespace Zef\Test\Adapters\View;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\View\TemplateFace;
use Zef\Test\Support\Adapters\View\RecordingViewEngine;
use Zef\Test\Support\Adapters\View\StringableValue;

/**
 * Pins the template's own handle: the escape fold is the tree's one
 * HTML-safe spelling (every special crossed, the Stringable cast,
 * the invalid byte substituted), and the partial verb re-enters the
 * engine with the port's own question -- the name and the bag the
 * caller spelled, the engine's own answer returned -- while a name
 * outside the grammar refuses at the identity fold, never inside
 * the engine.
 *
 * @internal
 */
#[CoversClass(TemplateFace::class)]
final class TemplateFaceTest extends TestCase
{
    public function testEscapesEveryHtmlSpecial(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        self::assertSame(
            '&lt;&gt;&amp;&quot;&#039;',
            $templateFace->escape('<>&"\''),
        );
    }

    public function testEscapesPlainTextVerbatim(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        self::assertSame('Zef stays plain', $templateFace->escape('Zef stays plain'));
    }

    public function testEscapesAnEmptyValueToTheEmptyText(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        self::assertSame('', $templateFace->escape(''));
    }

    public function testCastsAStringableThroughTheSameFold(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        self::assertSame('&lt;b&gt;', $templateFace->escape(new StringableValue('<b>')));
    }

    public function testSubstitutesTheInvalidByte(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        self::assertSame("a\u{FFFD}b", $templateFace->escape("a\xFFb"));
    }

    public function testThePartialVerbReentersTheEngine(): void
    {
        $recordingViewEngine = new RecordingViewEngine(['item' => 'the-answer']);
        $templateFace = new TemplateFace($recordingViewEngine);

        self::assertSame(
            'the-answer',
            $templateFace->render('item', ['name' => 'Zef']),
        );
        self::assertSame(
            [['name' => 'item', 'data' => ['name' => 'Zef']]],
            $recordingViewEngine->renders,
        );
    }

    public function testThePartialVerbRefusesANameOutsideTheGrammar(): void
    {
        $templateFace = new TemplateFace(new RecordingViewEngine());

        try {
            $templateFace->render('..', []);
            self::fail('The grammar-refusing name was not refused at the identity fold.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'A view name segment must be letter-or-digit led and carry only letters,'
                . " digits, dots, underscores or hyphens, got '..'.",
                $invalidArgumentException->getMessage(),
            );
        }
    }
}
