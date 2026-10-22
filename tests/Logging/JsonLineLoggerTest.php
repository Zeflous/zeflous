<?php

declare(strict_types=1);

namespace Zef\Test\Logging;

use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use stdClass;
use Zef\Framework\Logging\JsonLineFormatter;
use Zef\Framework\Logging\JsonLineLogger;
use Zef\Framework\Logging\JsonLineRecordWriter;
use Zef\Framework\Logging\JsonLineWriter;
use Zef\Framework\Logging\JsonLogDestination;
use Zef\Framework\Logging\JsonLogDocumentRoot;
use Zef\Framework\Logging\JsonLogFilePrivacy;
use Zef\Framework\Logging\JsonLogFileSecurity;
use Zef\Framework\Logging\JsonLogWriteException;
use Zef\Framework\Logging\LogContextSanitizer;
use Zef\Framework\Logging\NonFiniteFloat;
use Zef\Test\Support\Http\UnopenableStreamWrapper;
use Zef\Test\Support\Logging\JsonLineDoubleState;
use Zef\Test\Support\Logging\JsonLineFunctionDoubles;

/**
 * @internal
 */
#[CoversClass(JsonLineLogger::class)]
#[CoversClass(JsonLineFormatter::class)]
#[CoversClass(LogContextSanitizer::class)]
#[CoversClass(NonFiniteFloat::class)]
#[CoversClass(JsonLogDestination::class)]
#[CoversClass(JsonLogDocumentRoot::class)]
#[CoversClass(JsonLogFilePrivacy::class)]
#[CoversClass(JsonLineWriter::class)]
#[CoversClass(JsonLogFileSecurity::class)]
#[CoversClass(JsonLineRecordWriter::class)]
final class JsonLineLoggerTest extends TestCase
{
    private const string UNOPENABLE_PROTOCOL = 'zef-unopenable-log';

    private ?string $path = null;

    #[Override]
    protected function tearDown(): void
    {
        JsonLineFunctionDoubles::reset();

        if ($this->path === null || (!is_file($this->path) && !is_link($this->path))) {
            return;
        }

        unlink($this->path);
    }

    public function testWritesOneStructuredAndRedactedJsonRecord(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->warning('Request failed for token=secret123', [
            'request_id' => 'req-123',
            'password' => 'hunter2',
            'exception' => new RuntimeException('authorization=Bearer secret123'),
        ]);

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('warning', $record['level']);
        self::assertSame('Request failed for token=[REDACTED]', $record['message']);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame('req-123', $context['request_id']);
        self::assertSame('[REDACTED]', $context['password']);
        $exception = $context['exception'] ?? null;
        self::assertIsArray($exception);
        self::assertSame(RuntimeException::class, $exception['class']);
        self::assertStringNotContainsString('secret123', $lines[0]);
        self::assertStringNotContainsString('hunter2', $lines[0]);
    }

    /**
     * A non-finite float context entry keeps the record alive: the value
     * degrades to its diagnostic string spelling at the sanitizer's gate,
     * so the JSON encoding never refuses the record -- the whole point of
     * writing the failure down is lost if the diagnostic that arrives with
     * it can silence the record.
     */
    public function testANonFiniteFloatContextValueKeepsTheRecordAlive(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->error('Request failed', ['budget' => \NAN, 'cap' => \INF]);

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame('error', $record['level']);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame('NAN', $context['budget']);
        self::assertSame('INF', $context['cap']);
    }

    /**
     * The message is bounded to 2048 bytes, both edges pinned: a message
     * at the bound travels whole, one past it clamps to the bound, and a
     * multibyte message clamps without splitting a valid character -- the
     * cut is byte-wise (mb_strcut, not mb_substr) so invalid bytes survive
     * to the encoder's own substitution, keeping the flag combination's
     * pinned contract intact while a runaway host message can no longer
     * turn one line into the file's whole budget.
     */
    public function testTheMessageIsBoundedToTwoThousandFortyEightBytes(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->error(str_repeat('a', 2048) . 'b');

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame(str_repeat('a', 2048), $record['message']);
    }

    /**
     * The multibyte edge of the byte bound: a message of two-byte
     * characters clamps at 1024 characters (2048 bytes) rather than
     * splitting the 1025th, and never at a character count that would
     * double the line's budget.
     */
    public function testAMultibyteMessageClampsOnTheByteBoundWithoutSplittingACharacter(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->error(str_repeat("\u{00e9}", 1025));

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame(str_repeat("\u{00e9}", 1024), $record['message']);
    }

    /**
     * The three-byte edge of the same bound: 2048 bytes lands two bytes
     * into the 683rd character, and the cut must stop before it -- a
     * byte-blind cut would leave a broken tail the encoder's substitution
     * would surface as a replacement character, not the whole characters
     * the record actually keeps. This is the edge that separates
     * mb_strcut from a plain byte substr.
     */
    public function testAThreeByteMessageStopsBeforeTheCharacterTheBoundLandsInside(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->error(str_repeat("\u{4e2d}", 700));

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);

        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame(str_repeat("\u{4e2d}", 682), $record['message']);
    }

    public function testSanitizesNestedAndUnsupportedContextValues(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);
        $nested = ['token' => 'private', 'child' => ['value' => 'visible']];
        for ($depth = 0; $depth < 7; ++$depth) {
            $nested = ['next' => $nested];
        }

        $jsonLineLogger->info('safe', [
            'count' => 5,
            'ratio' => 1.5,
            'enabled' => true,
            'empty' => null,
            'nested' => $nested,
            'object' => new stdClass(),
        ]);

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        $record = json_decode($lines[0], true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $context = $record['context'] ?? null;
        self::assertIsArray($context);
        self::assertSame(5, $context['count']);
        self::assertSame(1.5, $context['ratio']);
        self::assertTrue($context['enabled']);
        self::assertNull($context['empty']);
        self::assertSame('[stdClass]', $context['object']);
        self::assertSame(
            '{"4":"numeric key"}',
            json_encode(LogContextSanitizer::sanitize([4 => 'numeric key'])),
        );
        self::assertStringContainsString('"_truncated":true', $lines[0]);
        self::assertStringNotContainsString('private', $lines[0]);
    }

    public function testRejectsUnknownLevels(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        try {
            $jsonLineLogger->log('verbose', 'not a PSR-3 level');
            self::fail('Unknown levels must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame('Unknown PSR-3 log level.', $invalidArgumentException->getMessage());
        }
    }

    public function testCreatesLogFilesWithOwnerOnlyPermissions(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';

        new JsonLineLogger($this->path)->info('private record');

        $permissions = fileperms($this->path);
        self::assertIsInt($permissions);
        self::assertSame(0, $permissions & 0o077);
        self::assertStringEndsWith("\n", (string) file_get_contents($this->path));
        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(1, $lines);
    }

    public function testFailsClosedWhenConfiguredDocumentRootCannotBeResolved(): void
    {
        $missingRoot = sys_get_temp_dir() . '/zef-missing-root-' . bin2hex(random_bytes(8));
        $hadRoot = \array_key_exists('DOCUMENT_ROOT', $_SERVER);
        $previousRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $missingRoot;

        try {
            $this->expectException(InvalidArgumentException::class);
            new JsonLineLogger(sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl');
        } finally {
            if ($hadRoot) {
                $_SERVER['DOCUMENT_ROOT'] = $previousRoot;
            } else {
                unset($_SERVER['DOCUMENT_ROOT']);
            }
        }
    }

    public function testRejectsLogPathsInsideDocumentRoot(): void
    {
        $root = sys_get_temp_dir() . '/zef-public-root-' . bin2hex(random_bytes(8));
        self::assertTrue(mkdir($root, 0o700));
        $hadRoot = \array_key_exists('DOCUMENT_ROOT', $_SERVER);
        $previousRoot = $_SERVER['DOCUMENT_ROOT'] ?? null;
        $_SERVER['DOCUMENT_ROOT'] = $root;

        try {
            $this->expectException(InvalidArgumentException::class);
            new JsonLineLogger($root . '/errors.jsonl');
        } finally {
            if ($hadRoot) {
                $_SERVER['DOCUMENT_ROOT'] = $previousRoot;
            } else {
                unset($_SERVER['DOCUMENT_ROOT']);
            }

            rmdir($root);
        }
    }

    public function testRejectsSymlinkDestinations(): void
    {
        $target = sys_get_temp_dir() . '/zef-json-log-target-' . bin2hex(random_bytes(8)) . '.jsonl';
        $this->path = sys_get_temp_dir() . '/zef-json-log-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        file_put_contents($target, '');
        if (!symlink($target, $this->path)) {
            @unlink($target);
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        try {
            new JsonLineLogger($this->path);
            self::fail('Symlink destinations must be rejected.');
        } catch (InvalidArgumentException) {
            self::assertFileExists($target);
        } finally {
            @unlink($target);
        }
    }

    public function testEveryPsr3LevelWritesItsOwnRecord(): void
    {
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $jsonLineLogger = new JsonLineLogger($this->path);

        $jsonLineLogger->emergency('level one');
        $jsonLineLogger->alert('level two');
        $jsonLineLogger->critical('level three');
        $jsonLineLogger->error('level four');
        $jsonLineLogger->warning('level five');
        $jsonLineLogger->notice('level six');
        $jsonLineLogger->info('level seven');
        $jsonLineLogger->debug('level eight');

        $lines = file($this->path, \FILE_IGNORE_NEW_LINES);
        self::assertIsArray($lines);
        self::assertCount(8, $lines);

        $records = [];
        foreach ($lines as $line) {
            $decoded = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
            self::assertIsArray($decoded);
            $records[] = $decoded;
        }

        self::assertSame(
            ['emergency', 'alert', 'critical', 'error', 'warning', 'notice', 'info', 'debug'],
            array_column($records, 'level'),
        );
        self::assertSame(
            [
                'level one',
                'level two',
                'level three',
                'level four',
                'level five',
                'level six',
                'level seven',
                'level eight',
            ],
            array_column($records, 'message'),
        );
    }

    public function testRefusesToAppendThroughASymlinkDestination(): void
    {
        $target = sys_get_temp_dir() . '/zef-json-real-' . bin2hex(random_bytes(8)) . '.jsonl';
        $link = sys_get_temp_dir() . '/zef-json-link-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($target, ''));
        if (!symlink($target, $link)) {
            @unlink($target);
            self::markTestSkipped('Symlinks are not available in this environment.');
        }

        try {
            JsonLineWriter::append($link, '{}');
            self::fail('A symlink destination must be refused at append time.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'Refusing to write through a symlink log destination.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            @unlink($link);
            @unlink($target);
        }
    }

    public function testAnUnopenableDestinationIsRefused(): void
    {
        self::assertTrue(stream_wrapper_register(self::UNOPENABLE_PROTOCOL, UnopenableStreamWrapper::class));

        try {
            set_error_handler(static fn (): bool => true);

            try {
                JsonLineWriter::append(self::UNOPENABLE_PROTOCOL . '://refused.jsonl', '{}');
                self::fail('An unopenable destination must be refused.');
            } finally {
                restore_error_handler();
            }
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'Unable to open the configured JSON log destination.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            stream_wrapper_unregister(self::UNOPENABLE_PROTOCOL);
        }
    }

    public function testAMutatedDestinationIsRefused(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-mutated-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, 'first'));
        $stream = fopen($path, 'r+b');
        self::assertIsResource($stream);

        self::assertTrue(unlink($path));
        self::assertNotFalse(file_put_contents($path, 'second'));

        try {
            JsonLogFileSecurity::secure($stream, $path);
            self::fail('A destination replaced underneath the open handle must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame(
                'The JSON log destination changed while it was being opened.',
                $jsonLogWriteException->getMessage(),
            );
        } finally {
            if (\is_resource($stream)) {
                fclose($stream);
            }

            @unlink($path);
        }
    }

    public function testAnExistingGroupReadableLogDestinationIsRejected(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-priv-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, ''));
        self::assertTrue(chmod($path, 0o640));

        try {
            JsonLogFilePrivacy::assertExistingFileIsPrivate($path);
            self::fail('A group-readable destination must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The existing JSON log file must be writable with owner-only permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            @chmod($path, 0o600);
            @unlink($path);
        }
    }

    public function testAnUnwritableLogDestinationIsRejected(): void
    {
        $path = sys_get_temp_dir() . '/zef-json-priv-' . bin2hex(random_bytes(8)) . '.jsonl';
        self::assertNotFalse(file_put_contents($path, ''));
        self::assertTrue(chmod($path, 0o400));

        try {
            JsonLogFilePrivacy::assertExistingFileIsPrivate($path);
            self::fail('A read-only destination must be rejected.');
        } catch (InvalidArgumentException $invalidArgumentException) {
            self::assertSame(
                'The existing JSON log file must be writable with owner-only permissions.',
                $invalidArgumentException->getMessage(),
            );
        } finally {
            @chmod($path, 0o600);
            @unlink($path);
        }
    }

    /**
     * Every record carries the UTC stamp: the first key of the JSONL line
     * is the timestamp in the engine's own strict format, so a record
     * without one is a record the formatter refused to sign.
     */
    public function testEveryRecordCarriesTheUtcTimestamp(): void
    {
        $line = JsonLineFormatter::line('info', 'stamped', []);

        $record = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        $timestamp = $record['timestamp'] ?? null;
        self::assertIsString($timestamp, 'The record must carry the UTC timestamp.');
        self::assertMatchesRegularExpression(
            '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
            $timestamp,
            "The record must carry the UTC timestamp in the engine's strict format.",
        );
        self::assertSame('stamped', $record['message']);
    }

    /**
     * A message that is not valid UTF-8 is substituted, not refused: the
     * throwing encoder runs with the substitution flag, so the broken
     * byte pair becomes the replacement character escape inside the line
     * and the record still decodes -- the flag combination is the
     * contract, and the bitwise rewrite that drops it answers an empty
     * line instead.
     */
    public function testSubstitutesInvalidUtf8RatherThanRefusingTheRecord(): void
    {
        $line = JsonLineFormatter::line('warning', "\xC3\x28 broken bytes", []);

        self::assertStringContainsString(
            '\ufffd',
            $line,
            'The broken lead byte must surface as the replacement escape.',
        );
        $record = json_decode($line, true, 512, \JSON_THROW_ON_ERROR);
        self::assertIsArray($record);
        self::assertSame("\u{FFFD}( broken bytes", $record['message']);
    }

    /**
     * The append's lock hygiene on the success path: the advisory lock is
     * taken and released around the record, exactly one exclusive
     * acquisition and one release, and nothing else.
     */
    public function testReleasesTheAdvisoryLockOnTheAppendSuccessPath(): void
    {
        JsonLineFunctionDoubles::define();
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';

        JsonLineWriter::append($this->path, '{"level":"info"}' . "\n");

        self::assertSame(
            [\LOCK_EX, \LOCK_UN],
            array_map(static fn (array $call): int => $call[0], JsonLineDoubleState::$flockCalls),
            'A completed append must release its advisory lock before the stream closes.',
        );
    }

    /**
     * A lock refusal fails closed without releasing a lock never taken:
     * the flag starts false, so the finally arm skips the release -- a
     * refusal that still unlocked would leave the unlocked stream's
     * release recorded after a lock that never existed.
     */
    public function testALockRefusalFailsClosedWithoutReleasingALockNeverTaken(): void
    {
        JsonLineFunctionDoubles::define();
        JsonLineDoubleState::$flockAnswer = false;
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';

        try {
            JsonLineWriter::append($this->path, '{"level":"info"}' . "\n");
            self::fail('A lock refusal must fail closed.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame('Unable to acquire the JSON log write lock.', $jsonLogWriteException->getMessage());
        }

        self::assertSame(
            [\LOCK_EX],
            array_map(static fn (array $call): int => $call[0], JsonLineDoubleState::$flockCalls),
            'No lock was taken when the acquisition failed, so none may be released.',
        );
    }

    /**
     * A zero-byte write refusal still runs the finally arm: the lock the
     * writer took is released and the stream closed even while the
     * exception travels, and the partial record is rolled back to the
     * byte offset the append started from -- the first record survives
     * whole and the refused second record leaves nothing behind.
     */
    public function testAWriteRefusalReleasesTheLockAndRollsTheRecordBack(): void
    {
        JsonLineFunctionDoubles::define();
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        $firstRecord = '{"level":"info"}' . "\n";

        JsonLineWriter::append($this->path, $firstRecord);
        JsonLineDoubleState::$fwriteAnswers = [0];

        try {
            JsonLineWriter::append($this->path, '{"level":"error"}' . "\n");
            self::fail('A zero-byte write must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame('Unable to write a complete JSON log record.', $jsonLogWriteException->getMessage());
        }

        self::assertSame(
            [\LOCK_EX, \LOCK_UN, \LOCK_EX, \LOCK_UN],
            array_map(static fn (array $call): int => $call[0], JsonLineDoubleState::$flockCalls),
            'Every append releases the lock it took, the refused one included.',
        );
        self::assertSame($firstRecord, file_get_contents($this->path), 'The refused record must leave nothing behind.');
    }

    /**
     * The record writer's loop bound counts bytes and its remainders
     * read byte offsets: a one-byte-per-call budget walks a
     * two-character line whose second character is the two-byte
     * e-acute in three calls, and the destination ends the walk whole
     * -- the second call ships the e-acute's second byte (the exact
     * byte-tail the first call's accepted byte left behind), never the
     * plain ASCII tail a character-offset walk would jump to while
     * the stream still misses half of the e-acute.
     */
    public function testTheRecordLoopSlicesOnByteOffsetsUnderAByteBudget(): void
    {
        JsonLineFunctionDoubles::define();
        JsonLineDoubleState::$fwriteChunk = 1;
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        JsonLineRecordWriter::write($stream, 'éa', 0);

        $payloads = array_map(
            static fn (array $call): string => $call[0],
            JsonLineDoubleState::$fwriteCalls,
        );
        self::assertSame(['éa', "\xA9a", 'a'], $payloads, 'The cursor advances on byte offsets under the byte budget.');
        rewind($stream);
        self::assertSame('éa', stream_get_contents($stream), 'the byte walk leaves the multibyte line whole');
    }

    /**
     * The byte bound is the exit: a line of two characters whose second
     * is two bytes wide completes in three one-budget calls -- the walk
     * ships every byte the stream accepted a remainder for, and the
     * character-count exit this loop once kept would have left the
     * e-acute's second byte unwritten while claiming success.
     */
    public function testTheRecordLoopExitsOnTheByteBoundNotTheCharacterCount(): void
    {
        JsonLineFunctionDoubles::define();
        JsonLineDoubleState::$fwriteChunk = 1;
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        JsonLineRecordWriter::write($stream, 'aé', 0);

        $payloads = array_map(
            static fn (array $call): string => $call[0],
            JsonLineDoubleState::$fwriteCalls,
        );
        self::assertSame(['aé', "\xC3\xA9", "\xA9"], $payloads, 'Every byte the stream accepted earned its remainder.');
        rewind($stream);
        self::assertSame('aé', stream_get_contents($stream), 'the walk ends with the line whole, never torn');
    }

    /**
     * An interim zero-byte write is a refusal even after progress: the
     * first call advanced the cursor, and the second answered zero -- the
     * rollback and the exception answer immediately rather than spinning
     * on a cursor that never advances.
     */
    public function testAnInterimZeroByteWriteIsRefusedAfterProgress(): void
    {
        JsonLineFunctionDoubles::define();
        JsonLineDoubleState::$fwriteAnswers = [1, 0];
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        try {
            JsonLineRecordWriter::write($stream, 'ab', 0);
            self::fail('An interim zero-byte write must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame('Unable to write a complete JSON log record.', $jsonLogWriteException->getMessage());
        }
    }

    /**
     * A refused first record leaves the destination empty, not even one
     * byte of it: the rollback clamps to the very top of the file, so a
     * destination that never held a complete record answers the refusal
     * with zero bytes -- never a padded null the truncation's lower bound
     * would manufacture.
     */
    public function testARefusedFirstRecordLeavesTheDestinationEmpty(): void
    {
        JsonLineFunctionDoubles::define();
        $this->path = sys_get_temp_dir() . '/zef-json-log-' . bin2hex(random_bytes(8)) . '.jsonl';
        JsonLineDoubleState::$fwriteAnswers = [0];

        try {
            JsonLineWriter::append($this->path, '{"level":"info"}' . "\n");
            self::fail('A zero-byte write must be refused.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame('Unable to write a complete JSON log record.', $jsonLogWriteException->getMessage());
        }

        self::assertSame('', file_get_contents($this->path), 'The refused first record must leave zero bytes behind.');
    }

    /**
     * A negative start offset still rolls back to the top of the file:
     * the clamp absorbs the impossible offset a future collaborator might
     * pass, so the refusal the caller hears is the write's own -- never
     * the engine's negative-size refusal the truncation would otherwise
     * throw.
     */
    public function testANegativeStartOffsetStillRollsBackToTheTop(): void
    {
        JsonLineFunctionDoubles::define();
        JsonLineDoubleState::$fwriteAnswers = [0];
        $stream = fopen('php://temp', 'r+b');
        self::assertIsResource($stream);

        try {
            JsonLineRecordWriter::write($stream, 'ab', -5);
            self::fail('A zero-byte write must be refused whatever the start offset.');
        } catch (JsonLogWriteException $jsonLogWriteException) {
            self::assertSame('Unable to write a complete JSON log record.', $jsonLogWriteException->getMessage());
        }
    }
}
