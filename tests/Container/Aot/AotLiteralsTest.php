<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Aot\AotLiterals;
use Zef\Framework\Container\Aot\AotUnrenderableException;
use Zef\Test\Support\Container\Aot\Tone;

/**
 * The literal rendering's own battery: the expressions the boundary
 * carries and the refusal that names the exact dotted position a value
 * the boundary cannot carry sits at. The nested refusal path is pinned
 * segment by segment -- a rearranged separator, a dropped prefix or a
 * reset walk would name a different position and the battery sees it.
 *
 * @internal
 */
#[CoversClass(AotLiterals::class)]
final class AotLiteralsTest extends TestCase
{
    public function testAScalarRendersAsItsVarExportSpelling(): void
    {
        self::assertSame("'café'", AotLiterals::export('café', 'app.service', 'args'));
        self::assertSame('7', AotLiterals::export(7, 'app.service', 'args'));
        self::assertSame('true', AotLiterals::export(true, 'app.service', 'args'));
        self::assertSame('NULL', AotLiterals::export(null, 'app.service', 'args'));
    }

    public function testAnEnumCaseRendersAsItsConstantExpression(): void
    {
        self::assertSame(
            '\\' . Tone::class . '::CALM',
            AotLiterals::export(Tone::CALM, 'app.service', 'args'),
        );
    }

    public function testANestedArrayRendersItsBracketLiteral(): void
    {
        $rendered = AotLiterals::export(['outer' => ['inner' => 'value']], 'app.service', 'args');

        self::assertSame("['outer' => ['inner' => 'value']]", $rendered);
    }

    /**
     * The refusal names the exact dotted position of the unexportable
     * value: every nested key appends to the path with its own dot, so
     * the message spells `args.outer.inner` -- a dropped separator, a
     * walk that resets, or a path that loses its prefix names another
     * position and the pinned message refuses it.
     */
    public function testAnUnexportableValueRefusesWithItsExactDottedPosition(): void
    {
        try {
            AotLiterals::export(['outer' => ['inner' => new stdClass()]], 'app.service', 'args');
            self::fail('A nested object must refuse to export.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertStringContainsString(
                "carries a value the AOT artifact cannot export at 'args.outer.inner' (stdClass)",
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testATopLevelObjectRefusesWithTheBarePosition(): void
    {
        try {
            AotLiterals::export(new stdClass(), 'app.service', 'args');
            self::fail('A top-level object must refuse to export.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertStringContainsString(
                "carries a value the AOT artifact cannot export at 'args' (stdClass)",
                $aotUnrenderableException->getMessage(),
            );
        }
    }
}
