<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\UploadOwnership;

/**
 * @internal
 */
#[CoversClass(UploadOwnership::class)]
final class UploadOwnershipTest extends TestCase
{
    /**
     * The engine's own upload claim: an error-OK entry whose temporary
     * file the engine never wrote is the ghost upload -- `NO_FILE`, the
     * one honest answer for a payload the engine disowns. Under the
     * CLI test runtime no file is engine-uploaded, so every honest
     * path answers the downgrade.
     */
    public function testADisownedErrorOkEntryDowngradesToNoFile(): void
    {
        self::assertSame(\UPLOAD_ERR_NO_FILE, UploadOwnership::leafError('/tmp/nowhere.bin', \UPLOAD_ERR_OK));
    }

    public function testAFileTheProcessTouchedItselfIsStillDisowned(): void
    {
        $touched = sys_get_temp_dir() . '/zef-upload-ownership-' . uniqid('', true);
        touch($touched);

        try {
            self::assertSame(\UPLOAD_ERR_NO_FILE, UploadOwnership::leafError($touched, \UPLOAD_ERR_OK));
        } finally {
            unlink($touched);
        }
    }

    /**
     * An empty or non-string temporary path names nothing the engine
     * wrote, so the error-OK verdict passes untouched -- the leaf
     * builder's own `NO_FILE` reading covers that shape instead.
     */
    public function testAnEmptyTemporaryPathPassesTheVerdictUntouched(): void
    {
        self::assertSame(\UPLOAD_ERR_OK, UploadOwnership::leafError('', \UPLOAD_ERR_OK));
    }

    public function testANonStringTemporaryPathPassesTheVerdictUntouched(): void
    {
        self::assertSame(\UPLOAD_ERR_OK, UploadOwnership::leafError(null, \UPLOAD_ERR_OK));
        self::assertSame(\UPLOAD_ERR_OK, UploadOwnership::leafError(42, \UPLOAD_ERR_OK));
    }

    public function testAnErrorVerdictOtherThanOkPassesUntouched(): void
    {
        self::assertSame(\UPLOAD_ERR_INI_SIZE, UploadOwnership::leafError('/tmp/nowhere.bin', \UPLOAD_ERR_INI_SIZE));
        self::assertSame(\UPLOAD_ERR_NO_FILE, UploadOwnership::leafError('/tmp/nowhere.bin', \UPLOAD_ERR_NO_FILE));
    }

    public function testAMixedVerdictPassesUntouched(): void
    {
        self::assertNull(UploadOwnership::leafError('/tmp/nowhere.bin', null));
    }
}
