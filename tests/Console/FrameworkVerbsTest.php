<?php

declare(strict_types=1);

namespace Zef\Test\Console;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Console\CliCommandName;
use Zef\Framework\Console\FrameworkVerbs;

/**
 * The framework's own verb vocabulary: every verb a valid command-name
 * spelling, every purpose renderable, and -- the correspondence pin --
 * every verb a branch `bin/zef` actually answers, so the registry's
 * collision refusal guards the real dispatch surface, never a list the
 * CLI has drifted away from.
 *
 * @internal
 */
#[CoversClass(FrameworkVerbs::class)]
final class FrameworkVerbsTest extends TestCase
{
    public function testEveryVerbIsAValidCommandNameSpelling(): void
    {
        foreach (array_keys(FrameworkVerbs::all()) as $verb) {
            self::assertSame($verb, new CliCommandName($verb)->toString());
        }
    }

    public function testEveryPurposeIsRenderableText(): void
    {
        foreach (FrameworkVerbs::all() as $verb => $purpose) {
            self::assertNotSame('', $purpose, "The '{$verb}' purpose must not be empty.");
            self::assertSame(
                0,
                preg_match('/[\x00-\x1F\x7F]/', $purpose),
                "The '{$verb}' purpose must not carry control bytes.",
            );
        }
    }

    public function testTheVocabularyCarriesTheDirectoryCore(): void
    {
        self::assertArrayHasKey('route:list', FrameworkVerbs::all());
        self::assertArrayHasKey('errors:report', FrameworkVerbs::all());
        self::assertArrayHasKey('storage:map', FrameworkVerbs::all());
    }

    /**
     * The correspondence pin: every verb the vocabulary spells must be a
     * verb `bin/zef` answers -- the CLI's own command-matching literals,
     * read from the file itself so the pin fails the day a builtin
     * branch is removed without the vocabulary following.
     */
    public function testEveryVocabularyVerbIsAVerbTheCliAnswers(): void
    {
        $cli = (string) file_get_contents(\dirname(__DIR__, 2) . '/bin/zef');

        self::assertNotSame('', $cli);

        foreach (array_keys(FrameworkVerbs::all()) as $verb) {
            self::assertStringContainsString(
                "'" . $verb . "'",
                $cli,
                "The vocabulary verb '{$verb}' is not a command bin/zef answers.",
            );
        }
    }
}
