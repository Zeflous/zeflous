<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Http\UploadOrigin;

/**
 * The anchor walk's own battery: the bag entries the walk owns (the
 * spec-shaped arrays carrying the error axis) and the entries it
 * passes through verbatim -- a string spec is not a SAPI upload entry
 * at all, and an array without an error axis is not one either. The
 * disowned error-OK leaf still downgrades to NO_FILE through the
 * walk's own verdict.
 *
 * @internal
 */
#[CoversClass(UploadOrigin::class)]
final class UploadOriginTest extends TestCase
{
    public function testAStringSpecPassesThroughVerbatim(): void
    {
        self::assertSame(['field' => 'a plain string spec'], UploadOrigin::anchor(['field' => 'a plain string spec']));
    }

    public function testAnArrayWithoutAnErrorAxisPassesThroughVerbatim(): void
    {
        $spec = ['field' => ['name' => 'report.txt', 'type' => 'text/plain']];

        self::assertSame($spec, UploadOrigin::anchor($spec));
    }

    public function testADisownedErrorOkLeafDowngradesToNoFile(): void
    {
        // The CLI engine never uploaded anything, so every error-OK
        // entry with a temporary path is disowned here and downgrades
        // to NO_FILE -- the one honest answer for a payload the engine
        // disowns.
        $anchored = UploadOrigin::anchor([
            'field' => [
                'name' => 'report.txt',
                'type' => 'text/plain',
                'tmp_name' => '/tmp/php-never-wrote-this',
                'error' => \UPLOAD_ERR_OK,
                'size' => 12,
            ],
        ]);

        $field = $anchored['field'];
        self::assertIsArray($field);
        self::assertSame(\UPLOAD_ERR_NO_FILE, $field['error']);
    }

    public function testALeafErrorTheEngineOwnsPassesUntouched(): void
    {
        $anchored = UploadOrigin::anchor([
            'field' => [
                'name' => 'report.txt',
                'type' => 'text/plain',
                'tmp_name' => '',
                'error' => \UPLOAD_ERR_INI_SIZE,
                'size' => 0,
            ],
        ]);

        $field = $anchored['field'];
        self::assertIsArray($field);
        self::assertSame(\UPLOAD_ERR_INI_SIZE, $field['error']);
    }

    public function testAParallelSpecWalksEveryLeaf(): void
    {
        $anchored = UploadOrigin::anchor([
            'field' => [
                'name' => ['one.txt', 'two.txt'],
                'type' => ['text/plain', 'text/plain'],
                'tmp_name' => ['/tmp/one', '/tmp/two'],
                'error' => [\UPLOAD_ERR_OK, \UPLOAD_ERR_OK],
                'size' => [1, 2],
            ],
        ]);

        $field = $anchored['field'];
        self::assertIsArray($field);
        self::assertSame(
            [\UPLOAD_ERR_NO_FILE, \UPLOAD_ERR_NO_FILE],
            $field['error'],
            'every disowned leaf of the parallel spec downgrades on its own',
        );
    }
}
