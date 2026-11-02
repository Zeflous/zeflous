<?php

declare(strict_types=1);

namespace Zef\Test\Support\Tooling;

/**
 * The migration operations' answer text: the one-line headline
 * `bin/zef migrations:run` and `migrations:rollback` render -- the
 * verb, the count with its honest singular, then one line per
 * version carrying the declaration's name. The empty answer is
 * spelled too (`applied 0 migrations:` and no lines): an operator
 * who runs twice learns the second run touched nothing, the same
 * honest-empty doctrine the status table's empty sections carry.
 */
final readonly class MigrationsRunText
{
    /**
     * The versions migrate() applied now.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions applied
     */
    public static function applied(array $names, array $versions): string
    {
        return self::lines('applied', $names, $versions);
    }

    /**
     * The versions rollback() rolled back.
     *
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions rolled back
     */
    public static function rolledBack(array $names, array $versions): string
    {
        return self::lines('rolled back', $names, $versions);
    }

    /**
     * One headline and one line per version.
     *
     * @param string                $verb     the operation's own word
     * @param array<string, string> $names    the declared migrations' names keyed
     *                                        by version stamp
     * @param list<string>          $versions the versions the operation touched
     */
    private static function lines(string $verb, array $names, array $versions): string
    {
        $lines = [
            \sprintf(
                '%s %d migration%s:',
                $verb,
                \count($versions),
                \count($versions) === 1 ? '' : 's',
            ),
        ];

        foreach ($versions as $version) {
            $lines[] = '  ' . $version . '  ' . ($names[$version] ?? '(undeclared)');
        }

        return implode("\n", $lines) . "\n";
    }
}
