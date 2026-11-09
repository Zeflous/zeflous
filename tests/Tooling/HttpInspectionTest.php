<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Zef\Test\Support\Tooling\CommandOutcome;
use Zef\Test\Support\Tooling\HttpInspection;

/**
 * The HTTP inspection verb as a pure outcome: the joined grammar's own
 * refusals (the missing or flag-shaped positional, the unresolved
 * directory, the invalid method or path, the unknown or mis-scoped
 * flag, the invalid host, scheme or header spelling, the over-bound
 * header and body values), both commands' answers (the wire dump, the
 * audit's table and machine forms, the failing audit's exit 1), and
 * the delegation to the family's own units -- a refused boot
 * surfacing with the inspection's own refusal word, exactly the words
 * the joined branch spelled before the lift. The bounds' units are
 * pinned from both sides: the path and host bounds count characters
 * (a multibyte spelling within the bound reaches the boot where a
 * byte-counting spelling would refuse it), while the header-value and
 * body bounds count bytes (a multibyte spelling past the bound
 * refuses where a character-counting spelling would accept it).
 *
 * @internal
 */
#[CoversClass(HttpInspection::class)]
#[CoversClass(CommandOutcome::class)]
final class HttpInspectionTest extends TestCase
{
    private const string FIXTURE = __DIR__ . '/../Scripts/fixtures/http-app';

    private const string USAGE = 'Usage: bin/zef http:dump <config-directory> <method> <path>'
        . " [--host=<host>] [--scheme=http|https] [--header=<Name: Value>]... [--body=<text>]\n"
        . '       bin/zef http:headers <config-directory> <method> <path>'
        . " [--host=<host>] [--scheme=http|https] [--header=<Name: Value>]... [--body=<text>] [--json]\n";

    private string $routedErrorLog;

    private false|string $previousErrorLog = false;

    #[Override]
    protected function setUp(): void
    {
        $this->routedErrorLog = sys_get_temp_dir() . '/zef-http-inspection-verb-' . uniqid('', false) . '.log';
        $this->previousErrorLog = ini_set('error_log', $this->routedErrorLog);
    }

    #[Override]
    protected function tearDown(): void
    {
        ini_set('error_log', (string) $this->previousErrorLog);
        if (is_file($this->routedErrorLog) && !unlink($this->routedErrorLog)) {
            throw new RuntimeException('Unable to remove the temporary error log.');
        }
    }

    public function testNoArgumentsAnswerTheUsage(): void
    {
        foreach (['http:dump', 'http:headers'] as $command) {
            $outcome = HttpInspection::run($command, []);

            self::assertSame(2, $outcome->exitCode, $command);
            self::assertSame('', $outcome->stdout, $command);
            self::assertSame(self::USAGE, $outcome->stderr, $command);
        }
    }

    public function testAFlagShapedPositionalAnswersTheUsage(): void
    {
        $outcome = HttpInspection::run('http:dump', ['--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    public function testAMissingDirectoryIsAPathDefectNamingIt(): void
    {
        $outcome = HttpInspection::run('http:dump', ['/does/not/exist', 'GET', '/ping']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("Configuration directory does not exist: /does/not/exist\n", $outcome->stderr);
    }

    /**
     * The method grammar refuses every spelling outside the token
     * vocabulary: a space, a trailing newline the /D anchor excludes,
     * and the over-long 33-character twin.
     */
    public function testAnInvalidMethodIsAUsageDefect(): void
    {
        foreach (['', 'G ET', "GET\n", str_repeat('G', 33)] as $method) {
            $outcome = HttpInspection::run('http:dump', [self::FIXTURE, $method, '/ping']);

            self::assertSame(2, $outcome->exitCode, $method);
            self::assertSame('', $outcome->stdout, $method);
            self::assertSame("Invalid request method: {$method}\n", $outcome->stderr, $method);
        }
    }

    public function testAnInvalidPathIsAUsageDefect(): void
    {
        foreach (['', 'noslash', "/pi\x01ng", '/' . str_repeat('a', 2049)] as $path) {
            $outcome = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', $path]);

            self::assertSame(2, $outcome->exitCode, $path);
            self::assertSame('', $outcome->stdout, $path);
            self::assertSame(
                "The request path must start with a slash and carry no control bytes.\n",
                $outcome->stderr,
                $path,
            );
        }
    }

    public function testAnUnknownFlagAnswersTheUsage(): void
    {
        $outcome = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', '/ping', '--bogus']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    /**
     * The --json flag belongs to http:headers alone: a mis-scoped
     * spelling on the dump answers the usage.
     */
    public function testTheJsonFlagOnTheDumpSpellingAnswersTheUsage(): void
    {
        $outcome = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', '/ping', '--json']);

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame(self::USAGE, $outcome->stderr);
    }

    public function testAnInvalidHostIsAUsageDefect(): void
    {
        foreach (['', "--ho\x01st", str_repeat('h', 254)] as $host) {
            $outcome = HttpInspection::run(
                'http:dump',
                [self::FIXTURE, 'GET', '/ping', '--host=' . $host],
            );

            self::assertSame(2, $outcome->exitCode, $host);
            self::assertSame('', $outcome->stdout, $host);
            self::assertSame("Invalid --host= value.\n", $outcome->stderr, $host);
        }
    }

    public function testAnInvalidSchemeIsAUsageDefect(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--scheme=ftp'],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("The --scheme= flag accepts http or https.\n", $outcome->stderr);
    }

    public function testMoreThanSixteenHeadersIsAUsageDefect(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            array_merge([self::FIXTURE, 'GET', '/ping'], array_fill(0, 17, '--header=X-A: b')),
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("At most 16 --header= flags are accepted.\n", $outcome->stderr);
    }

    /**
     * The header grammar refuses every defective spelling: a missing
     * colon, a name outside the token vocabulary, an empty value, a
     * control byte in the value, and a name the /D anchor excludes
     * with its trailing newline.
     */
    public function testTheHeaderGrammarIsAUsageDefect(): void
    {
        foreach (['NoColon', 'Bad Name: v', 'X-Test:', "X-Test: a\x01b", "X-Test\n: v"] as $header) {
            $outcome = HttpInspection::run(
                'http:dump',
                [self::FIXTURE, 'GET', '/ping', '--header=' . $header],
            );

            self::assertSame(2, $outcome->exitCode, $header);
            self::assertSame('', $outcome->stdout, $header);
            self::assertSame(
                'Each --header= flag must spell `Name: value`'
                . " with a printable value of at most 4096 bytes.\n",
                $outcome->stderr,
                $header,
            );
        }
    }

    public function testAnOverLongBodyIsAUsageDefect(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--body=' . str_repeat('a', 8193)],
        );

        self::assertSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertSame("The --body= flag accepts at most 8192 bytes.\n", $outcome->stderr);
    }

    /**
     * The dump answers the wire exchange the fixture's own composition
     * produced: the request block, the hardened response block, and
     * the handler's own body.
     */
    public function testTheDumpAnswersTheWireExchange(): void
    {
        $outcome = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', '/ping']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringStartsWith("-- request --\r\nGET /ping HTTP/1.1\r\nHost: localhost\r\n", $outcome->stdout);
        self::assertStringContainsString("\r\n\r\n-- response --\r\nHTTP/1.1 200 OK\r\n", $outcome->stdout);
        self::assertStringContainsString('X-Content-Type-Options: nosniff', $outcome->stdout);
        self::assertStringEndsWith("\r\n\r\npong", $outcome->stdout);
    }

    /**
     * The simulation flags reach the exchange: the host, the header
     * pair (both the spaced and the unspelled colon forms), and the
     * body all ride the simulated request the dump spells.
     */
    public function testTheSimulationFlagsReachTheExchange(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--host=example.test', '--header=X-Test: custom', '--body=hello'],
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString(
            "POST /mirror/spot HTTP/1.1\r\nX-Test: custom\r\nHost: example.test\r\n",
            $outcome->stdout,
        );
        self::assertStringContainsString(
            "\r\n\r\nhello\r\n\r\n-- response --\r\nHTTP/1.1 201 Created\r\n",
            $outcome->stdout,
        );
        self::assertStringEndsWith("\r\n\r\nmirrored spot", $outcome->stdout);

        $unspaced = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--header=X-NoSpace:tight'],
        );

        self::assertSame(0, $unspaced->exitCode);
        self::assertStringContainsString("X-NoSpace: tight\r\n", $unspaced->stdout);
    }

    /**
     * The header count bound is sixteen: exactly sixteen flags
     * reach the exchange, the seventeenth refusing.
     */
    public function testExactlySixteenHeadersReachTheExchange(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            array_merge([self::FIXTURE, 'GET', '/ping'], array_fill(0, 16, '--header=X-A: b')),
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringStartsWith('-- request --', $outcome->stdout);
        self::assertStringContainsString('X-A: b', $outcome->stdout);
    }

    /**
     * The headers audit answers the passing table over the https
     * scheme the fixture armed for HSTS.
     */
    public function testTheHeadersAuditAnswersThePassingTable(): void
    {
        $outcome = HttpInspection::run('http:headers', [self::FIXTURE, 'GET', '/ping', '--scheme=https']);

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringContainsString('X-Content-Type-Options', $outcome->stdout);
        self::assertStringContainsString('Strict-Transport-Security         pass', $outcome->stdout);
        self::assertStringEndsWith("\nSecurity header audit: PASS\n", $outcome->stdout);
    }

    /**
     * The --json flag answers the audit's machine form.
     */
    public function testTheHeadersAuditAnswersTheJsonForm(): void
    {
        $outcome = HttpInspection::run(
            'http:headers',
            [self::FIXTURE, 'GET', '/ping', '--scheme=https', '--json'],
        );

        self::assertSame(0, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringEndsWith("\n", $outcome->stdout);

        $document = $this->mapAt(json_decode(mb_rtrim($outcome->stdout, "\n"), true));
        self::assertTrue($document['passed']);
        self::assertGreaterThan(0, $this->mapAt($document['counts'])['pass']);
        $findings = $this->mapAt($document['findings']);
        self::assertSame('X-Content-Type-Options', $this->mapAt($findings[0] ?? null)['header']);
    }

    /**
     * The audit's end-to-end truth: an application whose stack omitted
     * the hardening middleware answers the failing table with exit 1
     * -- the verdict a middleware that failed to run surfaces.
     */
    public function testTheFailingAuditAnswersTheFailTableWithExitOne(): void
    {
        $host = $this->unhardenedHost();

        try {
            $outcome = HttpInspection::run('http:headers', [$host, 'GET', '/ping']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stderr);
            self::assertStringContainsString('X-Content-Type-Options            missing', $outcome->stdout);
            self::assertStringEndsWith("\nSecurity header audit: FAIL (8 required finding(s))\n", $outcome->stdout);
        } finally {
            $this->cleanHost($host);
        }
    }

    /**
     * A refused boot surfaces the inspection's own refusal word with
     * the boot's descriptive chain.
     */
    public function testARefusedBootSurfacesTheDescriptiveRefusal(): void
    {
        $host = $this->defectiveHost();

        try {
            $outcome = HttpInspection::run('http:dump', [$host, 'GET', '/ping']);

            self::assertSame(1, $outcome->exitCode);
            self::assertSame('', $outcome->stdout);
            self::assertStringStartsWith('The HTTP inspection refused: ', $outcome->stderr);
            self::assertStringContainsString('The route path "no-slash" must start with a slash.', $outcome->stderr);
            self::assertStringEndsWith("\n", $outcome->stderr);
        } finally {
            $this->cleanHost($host);
        }
    }

    /**
     * The path bound's unit is the character, its edge inclusive: a
     * 1500-character multibyte path (3000 bytes) and the bound's own
     * 2048-character edge both reach the boot, where a byte-counting
     * spelling would refuse the first and an off-by-one spelling the
     * second.
     */
    public function testAMultibytePathWithinTheCharacterBoundReachesTheBoot(): void
    {
        $outcome = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', '/' . str_repeat('é', 1500)]);

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stderr);
        self::assertStringStartsWith('-- request --', $outcome->stdout);
        self::assertStringContainsString('HTTP/1.1 404 Not Found', $outcome->stdout);

        $atTheBound = HttpInspection::run('http:dump', [self::FIXTURE, 'GET', '/' . str_repeat('a', 2047)]);

        self::assertNotSame(2, $atTheBound->exitCode);
        self::assertSame('', $atTheBound->stderr);
        self::assertStringContainsString('HTTP/1.1 404 Not Found', $atTheBound->stdout);
    }

    /**
     * The host bound's unit is the character, its edge inclusive: a
     * 200-character multibyte host (400 bytes) and the bound's own
     * 253-character edge both reach the boot, where a byte-counting
     * spelling would refuse the first and an off-by-one spelling the
     * second -- the URI layer refusing the multibyte spelling with the
     * inspection's own refusal word.
     */
    public function testAMultibyteHostWithinTheCharacterBoundReachesTheBoot(): void
    {
        $outcome = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--host=' . str_repeat('é', 200)],
        );

        self::assertNotSame(2, $outcome->exitCode);
        self::assertSame('', $outcome->stdout);
        self::assertStringStartsWith('The HTTP inspection refused: Invalid URI host.', $outcome->stderr);

        $atTheBound = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--host=' . str_repeat('a.', 126) . 'a'],
        );

        self::assertSame(0, $atTheBound->exitCode);
        self::assertStringContainsString('Host: ' . str_repeat('a.', 126) . 'a', $atTheBound->stdout);
    }

    /**
     * The header value bound's unit is the byte: a 3000-character
     * multibyte value (6000 bytes) refuses where a 2000-character one
     * (4000 bytes) reaches the exchange -- and the bound's own edge
     * (2048 characters, 4096 bytes) reaches it too.
     */
    public function testTheHeaderValueBoundIsTheByte(): void
    {
        $refused = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--header=X-Test: ' . str_repeat('é', 3000)],
        );

        self::assertSame(2, $refused->exitCode);
        self::assertSame(
            'Each --header= flag must spell `Name: value`'
            . " with a printable value of at most 4096 bytes.\n",
            $refused->stderr,
        );

        $answered = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--header=X-Test: ' . str_repeat('é', 2000)],
        );

        self::assertSame(0, $answered->exitCode);
        self::assertStringContainsString('X-Test: éé', $answered->stdout);

        $atTheBound = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--header=X-Test: ' . str_repeat('é', 2048)],
        );

        self::assertSame(0, $atTheBound->exitCode);
        self::assertStringContainsString('X-Test: éé', $atTheBound->stdout);
    }

    /**
     * The body bound's unit is the byte: a 5000-character multibyte
     * body (10000 bytes) refuses where a 4000-character one (8000
     * bytes) reaches the exchange -- and the bound's own edge (4096
     * characters, 8192 bytes) reaches it too.
     */
    public function testTheBodyBoundIsTheByte(): void
    {
        $refused = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--body=' . str_repeat('é', 5000)],
        );

        self::assertSame(2, $refused->exitCode);
        self::assertSame("The --body= flag accepts at most 8192 bytes.\n", $refused->stderr);

        $answered = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--body=' . str_repeat('é', 4000)],
        );

        self::assertSame(0, $answered->exitCode);
        self::assertStringContainsString('ééé', $answered->stdout);

        $atTheBound = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--body=' . str_repeat('é', 4096)],
        );

        self::assertSame(0, $atTheBound->exitCode);
        self::assertStringContainsString('ééé', $atTheBound->stdout);
    }

    /**
     * The strip spellings answer the substituted form for an invalid
     * UTF-8 byte: the mb- fold the CLI's own doctrine rides replaces
     * the invalid byte with the printable question mark, so the header
     * and body the exchange carries never hold the raw byte -- and the
     * host still answers, the substituted question mark riding the
     * authority the URI layer itself parses (where the raw byte would
     * answer the URI's own refusal instead).
     */
    public function testAnInvalidUtf8ByteAnswersTheSubstitutedForm(): void
    {
        $host = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--host=ex' . "\xFF" . 'ample.test'],
        );

        self::assertSame(0, $host->exitCode);
        self::assertStringStartsWith('-- request --', $host->stdout);

        $header = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'GET', '/ping', '--header=X-Test: a' . "\xC3\x28" . 'b'],
        );

        self::assertSame(0, $header->exitCode);
        self::assertStringContainsString('X-Test: a?(b', $header->stdout);

        $body = HttpInspection::run(
            'http:dump',
            [self::FIXTURE, 'POST', '/mirror/spot', '--body=a' . "\xFF\xFE" . 'b'],
        );

        self::assertSame(0, $body->exitCode);
        self::assertStringContainsString("\r\n\r\na??b\r\n\r\n-- response --", $body->stdout);
    }

    /**
     * The unhardened host: the fixture's own files with a stack that
     * omits the hardening middleware -- the audit's FAIL arm.
     *
     * @return non-empty-string the host directory
     */
    private function unhardenedHost(): string
    {
        $host = sys_get_temp_dir() . '/zef-http-verb-unhardened-' . uniqid('', false);
        mkdir($host, 0o700, true);

        $fixtureFiles = glob(self::FIXTURE . '/*.php');
        foreach (\is_array($fixtureFiles) ? $fixtureFiles : [] as $file) {
            copy($file, $host . '/' . basename($file));
        }

        file_put_contents($host . '/middleware.php', '<?php return ["stack" => []];');

        return $host;
    }

    /**
     * The defective host: a configuration whose route table the boot
     * refuses at its own seam.
     *
     * @return non-empty-string the host directory
     */
    private function defectiveHost(): string
    {
        $host = sys_get_temp_dir() . '/zef-http-verb-defective-' . uniqid('', false);
        mkdir($host, 0o700, true);

        file_put_contents(
            $host . '/routes.php',
            '<?php return [["path" => "no-slash", "method" => ["GET"], "handler" => "X::y"]];',
        );

        return $host;
    }

    /**
     * The decoded document node an assertion reads, narrowed to the
     * map shape the JSON form answers.
     *
     * @return array<array-key, mixed>
     */
    private function mapAt(mixed $node): array
    {
        if (!\is_array($node)) {
            self::fail('The document node the assertion read is not an object.');
        }

        return $node;
    }

    private function cleanHost(string $host): void
    {
        $files = glob($host . '/*.php');
        array_map(unlink(...), \is_array($files) ? $files : []);
        rmdir($host);
    }
}
