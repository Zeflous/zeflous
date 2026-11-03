<?php

declare(strict_types=1);

namespace Zef\Test\Telemetry;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Telemetry\LogRecord;

/**
 * The recorded log's value object: the four frozen fields a
 * recording point hands the logs lane -- the severity's spelling,
 * the body, the timestamp, the dimensions -- carried verbatim and
 * read back exactly as they were constructed, with the empty
 * dimensions the record without a table answers.
 *
 * The record is deliberately raw ({@see LogRecord}'s own record:
 * the recording point owns the hygiene), so the suite's job here
 * is only the shape's honesty: what was constructed is what the
 * exporter's fold will encode, nothing minted, nothing coerced.
 *
 * @internal
 */
#[CoversClass(LogRecord::class)]
final class LogRecordTest extends TestCase
{
    public function testARecordCarriesItsOwnFieldsVerbatim(): void
    {
        $logRecord = new LogRecord('ERROR', 'upstream 503', 5, ['http.method' => 'GET']);

        self::assertSame('ERROR', $logRecord->severity);
        self::assertSame('upstream 503', $logRecord->body);
        self::assertSame(5, $logRecord->timeUnixNano);
        self::assertSame(['http.method' => 'GET'], $logRecord->attributes);
    }

    public function testTheDimensionsDefaultToTheEmptyMap(): void
    {
        $logRecord = new LogRecord('INFO', 'ready', 6);

        self::assertSame([], $logRecord->attributes);
    }
}
