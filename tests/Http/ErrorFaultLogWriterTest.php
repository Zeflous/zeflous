<?php

declare(strict_types=1);

namespace Zef\Test\Http;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Framework\Http\ErrorFaultLogWriter;
use Zef\Test\Support\Log\FailingLogger;
use Zef\Test\Support\Log\RecordingLogger;

/**
 * Direct battery for the record writer's message lane: the exception
 * lane's historical spelling stays the default, the diagnostic lane's own
 * spelling travels when named, and a failing logger is contained at the
 * SAPI log fallback.
 *
 * @internal
 */
#[CoversClass(ErrorFaultLogWriter::class)]
final class ErrorFaultLogWriterTest extends TestCase
{
    private string $routedErrorLog = '';

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-writer-errorlog-' . uniqid() . '.log';
        $this->previousErrorLog = (string) ini_set('error_log', $this->routedErrorLog);
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);

        if (is_file($this->routedErrorLog)) {
            unlink($this->routedErrorLog);
        }
    }

    public function testTheLoggerReceivesTheDefaultMessageSpelling(): void
    {
        $recordingLogger = new RecordingLogger();

        ErrorFaultLogWriter::write($recordingLogger, 'critical', ['request_id' => 'req-123']);

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('critical', $recordingLogger->calls[0][0]);
        self::assertSame('Request failed', $recordingLogger->calls[0][1]);
        self::assertSame(['request_id' => 'req-123'], $recordingLogger->calls[0][2]);
    }

    public function testTheLoggerReceivesTheNamedMessageSpelling(): void
    {
        $recordingLogger = new RecordingLogger();

        ErrorFaultLogWriter::write($recordingLogger, 'warning', ['request_id' => 'req-123'], 'Native diagnostic');

        self::assertCount(1, $recordingLogger->calls);
        self::assertSame('warning', $recordingLogger->calls[0][0]);
        self::assertSame('Native diagnostic', $recordingLogger->calls[0][1]);
    }

    public function testTheSapiFallbackCarriesTheNamedMessageSpelling(): void
    {
        ErrorFaultLogWriter::write(null, 'info', ['request_id' => 'req-123'], 'Native diagnostic');

        $decoded = $this->decodedFallbackRecord();

        self::assertSame('info', $decoded['level']);
        self::assertSame('Native diagnostic', $decoded['message']);
        self::assertSame(['request_id' => 'req-123'], $decoded['context']);
    }

    public function testTheSapiFallbackKeepsTheDefaultMessageSpelling(): void
    {
        ErrorFaultLogWriter::write(null, 'error', ['request_id' => 'req-123']);

        $decoded = $this->decodedFallbackRecord();

        self::assertSame('error', $decoded['level']);
        self::assertSame('Request failed', $decoded['message']);
    }

    public function testAFailingLoggerIsContainedAtTheSapiFallback(): void
    {
        ErrorFaultLogWriter::write(
            new FailingLogger(new RuntimeException('The sink is broken.')),
            'critical',
            ['request_id' => 'req-123'],
            'Uncaught fatal',
        );

        self::assertStringContainsString(
            'ZEF logging failure: RuntimeException',
            $this->routedErrorLogContents(),
        );
    }

    /**
     * @return array<array-key, mixed> the decoded fallback record
     */
    private function decodedFallbackRecord(): array
    {
        $contents = $this->routedErrorLogContents();
        self::assertNotSame('', $contents, 'The fallback record must reach the routed SAPI error log.');

        $jsonStart = mb_strpos($contents, '{"timestamp"');
        self::assertIsInt($jsonStart, 'The fallback record must carry its JSON document.');

        $decoded = json_decode(mb_substr($contents, $jsonStart), true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded, 'The fallback record must decode to an object.');

        return $decoded;
    }

    /**
     * Reads back whatever reached the routed SAPI error log (the empty
     * string when nothing did).
     */
    private function routedErrorLogContents(): string
    {
        if (!is_file($this->routedErrorLog)) {
            return '';
        }

        $contents = file_get_contents($this->routedErrorLog);
        self::assertIsString($contents, 'The routed error log must be readable.');

        return $contents;
    }
}
