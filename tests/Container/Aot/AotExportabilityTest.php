<?php

declare(strict_types=1);

namespace Zef\Test\Container\Aot;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;
use Zef\Framework\Container\Aot\AotExportability;
use Zef\Framework\Container\Aot\AotUnrenderableException;
use Zef\Test\Support\Container\Aot\Tone;

/**
 * @internal
 */
#[CoversClass(AotExportability::class)]
final class AotExportabilityTest extends TestCase
{
    public function testDataFoldsAtAnyDepth(): void
    {
        AotExportability::assertExportable(['a' => [1, 2.5, true, null, 'word']], 's', 'p');

        $this->expectNotToPerformAssertions();
    }

    public function testAScalarFolds(): void
    {
        AotExportability::assertExportable('word', 's', 'p');
        AotExportability::assertExportable(4, 's', 'p');
        AotExportability::assertExportable(null, 's', 'p');

        $this->expectNotToPerformAssertions();
    }

    /**
     * The data verdict refuses enums even though the expression lane
     * folds them as code: a build entry's arguments cross as plain
     * data the artifact file's own array literal would have to export
     * whole.
     */
    public function testAnEnumIsDataTheVerdictRefuses(): void
    {
        try {
            AotExportability::assertExportable(Tone::CALM, 's', 'p');
            self::fail('Expected an AotUnrenderableException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 's' carries a value the AOT artifact cannot export at 'p' ("
                . Tone::class . ') -- move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testAnObjectRefusesWithItsDottedPath(): void
    {
        try {
            AotExportability::assertExportable(['a' => [new stdClass()]], 's', 'p');
            self::fail('Expected an AotUnrenderableException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 's' carries a value the AOT artifact cannot export at 'p.a.0' (stdClass) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        }
    }

    public function testAResourceRefuses(): void
    {
        $stream = fopen('php://memory', 'rb');
        self::assertIsResource($stream);

        try {
            AotExportability::assertExportable($stream, 's', 'p');
            self::fail('Expected an AotUnrenderableException to be thrown.');
        } catch (AotUnrenderableException $aotUnrenderableException) {
            self::assertSame(
                "Service 's' carries a value the AOT artifact cannot export at 'p' (resource (stream)) -- "
                . 'move the computation behind the factory shape, or boot without the AOT lane.',
                $aotUnrenderableException->getMessage(),
            );
        } finally {
            fclose($stream);
        }
    }
}
