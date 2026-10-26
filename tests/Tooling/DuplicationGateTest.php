<?php

declare(strict_types=1);

namespace Zef\Test\Tooling;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Test\Support\Tooling\DuplicationGate;
use Zef\Test\Support\Tooling\DuplicationReport;
use Zef\Test\Support\Tooling\ToolingException;

/**
 * @internal
 */
#[CoversClass(DuplicationGate::class)]
#[CoversClass(DuplicationReport::class)]
final class DuplicationGateTest extends TestCase
{
    private const string CLEAN_PMD = '<?xml version="1.0" encoding="UTF-8"?>'
        . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0"/>';

    public function testACleanPayloadCarriesNoClone(): void
    {
        $report = DuplicationReport::fromPmdXml(self::CLEAN_PMD, '/repo');

        self::assertFalse($report->hasClones());
        self::assertSame([], $report->clones);
    }

    public function testAClonePayloadNamesBothSpansRelativisedAgainstTheRoot(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="38" tokens="110" exact="false">'
            . '<file line="19" endline="57" path="/repo/src/Http/A.php"/>'
            . '<file line="27" endline="65" path="/repo/src/Http/B.php"/>'
            . '<codefragment><![CDATA[final class A]]></codefragment>'
            . '</duplication>'
            . '</pmd-cpd>';

        $report = DuplicationReport::fromPmdXml($xml, '/repo');

        self::assertTrue($report->hasClones());
        self::assertSame(
            [
                [
                    'files' => [
                        ['path' => 'src/Http/A.php', 'line' => 19, 'endLine' => 57],
                        ['path' => 'src/Http/B.php', 'line' => 27, 'endLine' => 65],
                    ],
                    'lines' => 38,
                ],
            ],
            $report->clones,
        );
    }

    public function testARootSpelledWithATrailingSlashStillRelativises(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="5" tokens="70" exact="true">'
            . '<file line="1" endline="5" path="/repo/src/A.php"/>'
            . '</duplication>'
            . '</pmd-cpd>';

        $report = DuplicationReport::fromPmdXml($xml, '/repo/');

        self::assertSame('src/A.php', $report->clones[0]['files'][0]['path']);
    }

    public function testAPathOutsideTheRootKeepsItsFullSpelling(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="5" tokens="70" exact="true">'
            . '<file line="1" endline="5" path="/elsewhere/src/A.php"/>'
            . '</duplication>'
            . '</pmd-cpd>';

        $report = DuplicationReport::fromPmdXml($xml, '/repo');

        self::assertSame('/elsewhere/src/A.php', $report->clones[0]['files'][0]['path']);
    }

    public function testADuplicationWithoutFileSpansIsRefused(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="38" tokens="110" exact="false"/>'
            . '</pmd-cpd>';

        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('without file spans');

        DuplicationReport::fromPmdXml($xml, '/repo');
    }

    public function testARootOutsideTheAsciiPlaneStillRelativises(): void
    {
        // The prefix's own length is measured in characters, not bytes
        // (mb_strlen over strlen), so a multibyte root relativises the
        // same file the ASCII root does -- the byte-count spelling would
        // cut the path mid-character and name a file that does not exist.
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="5" tokens="70" exact="true">'
            . '<file line="1" endline="5" path="/répo/src/A.php"/>'
            . '</duplication>'
            . '</pmd-cpd>';

        $report = DuplicationReport::fromPmdXml($xml, '/répo');

        self::assertSame('src/A.php', $report->clones[0]['files'][0]['path']);
    }

    public function testParsingRestoresTheLibxmlErrorState(): void
    {
        try {
            DuplicationReport::fromPmdXml(self::CLEAN_PMD, '/repo');

            self::assertFalse(libxml_use_internal_errors());
        } finally {
            libxml_use_internal_errors(false);
        }
    }

    public function testGarbageIsRefusedAsAPayload(): void
    {
        $this->expectException(ToolingException::class);
        $this->expectExceptionMessageIsOrContains('not valid XML');

        DuplicationReport::fromPmdXml('not xml at all', '/repo');
    }

    public function testTheGatePassesACleanReport(): void
    {
        $gateResult = new DuplicationGate()
            ->evaluate(DuplicationReport::fromPmdXml(self::CLEAN_PMD, '/repo'))
        ;

        self::assertTrue($gateResult->passed);
        self::assertSame(
            'Duplication gate PASSED: the copy-paste detector found no clone in src/.',
            $gateResult->message,
        );
    }

    public function testTheGateFailsACloneReportNamingBothSpans(): void
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'
            . '<pmd-cpd xmlns="https://systemsdk.github.io/phpcpd/report" version="1.0.0">'
            . '<duplication lines="38" tokens="110" exact="false">'
            . '<file line="19" endline="57" path="/repo/src/Http/A.php"/>'
            . '<file line="27" endline="65" path="/repo/src/Http/B.php"/>'
            . '</duplication>'
            . '<duplication lines="6" tokens="80" exact="true">'
            . '<file line="1" endline="6" path="/repo/src/Http/C.php"/>'
            . '<file line="2" endline="7" path="/repo/src/Http/D.php"/>'
            . '</duplication>'
            . '</pmd-cpd>';

        $gateResult = new DuplicationGate()
            ->evaluate(DuplicationReport::fromPmdXml($xml, '/repo'))
        ;

        self::assertFalse($gateResult->passed);
        self::assertSame(
            'Duplication gate FAILED: 2 code clone(s) in src/: '
            . 'src/Http/A.php:19-57 <-> src/Http/B.php:27-65 (38 lines); '
            . 'src/Http/C.php:1-6 <-> src/Http/D.php:2-7 (6 lines).',
            $gateResult->message,
        );
    }
}
