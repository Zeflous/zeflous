<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\HttpException;
use Zef\Framework\Http\ReplayStores;

/**
 * @internal
 */
#[CoversClass(ReplayStores::class)]
final class ReplayStoresTest extends TestCase
{
    public function testThePerProcessStoreNameReadsAsItself(): void
    {
        self::assertSame('memory', ReplayStores::read('memory'));
    }

    public function testTheSharedStoreNameReadsAsItself(): void
    {
        self::assertSame('apcu', ReplayStores::read('apcu'));
    }

    public function testTheStoreNameFoldsCase(): void
    {
        self::assertSame('apcu', ReplayStores::read('APCU'));
        self::assertSame('memory', ReplayStores::read('Memory'));
    }

    public function testAnUnknownStoreNameIsRefusedWithTheValueSpelledOut(): void
    {
        try {
            ReplayStores::read('redis');
            self::fail('Expected an HttpException to be thrown.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth replay_store knob must be one of the known replay stores '
                . "(memory, apcu), 'redis' given.",
                $httpException->getMessage(),
            );
        }
    }

    public function testANonStringValueIsRefusedWithItsType(): void
    {
        try {
            ReplayStores::read(42);
            self::fail('Expected an HttpException to be thrown.');
        } catch (HttpException $httpException) {
            self::assertSame(
                'The security.auth replay_store knob must be one of the known replay stores '
                . '(memory, apcu), int given.',
                $httpException->getMessage(),
            );
        }
    }
}
