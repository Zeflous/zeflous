<?php

declare(strict_types=1);

namespace Zef\Framework\Container\Aot;

/**
 * The dump lane's entries block: the rendering fold that spells one
 * validated artifact's entries as the PHP array literal the artifact
 * file carries -- closure entries embed their rendered code verbatim
 * (the array literal evaluates it into a live closure at `require`
 * time), build entries export as plain data.
 *
 * Split out of {@see ContainerAotCache}'s own body so the cache seam
 * owns the file's lifecycle alone (the staging write, the atomic
 * rename, the probe and the load) while this fold owns the bytes --
 * the same single-question split the dump lane's family carries
 * everywhere else.
 */
final readonly class AotEntriesBlock
{
    /**
     * Renders the entries block: closure entries with their rendered
     * factory code verbatim, build entries as plain data.
     *
     * The closure member is captured once before its judgement -- the
     * dumper's own data always carries it on the closure kind, and
     * the capture keeps the rendering honest about that trust without
     * asserting it to either analyser.
     *
     * @param array<string, array{kind: 'build', factory: string, arguments: list<mixed>,
     *     calls: list<array{method: string, arguments: list<mixed>}>, lifetime: string}
     *     |array{kind: 'closure', closure: string, lifetime: string}> $entries
     */
    public static function rendered(array $entries): string
    {
        $rendered = '';

        foreach ($entries as $id => $entry) {
            $closure = $entry['closure'] ?? null;

            if ($entry['kind'] === 'closure' && \is_string($closure)) {
                $rendered .= '        ' . var_export($id, true) . " => [\n"
                    . "            'kind' => 'closure',\n"
                    . '            ' . "'closure' => " . $closure . ",\n"
                    . '            ' . "'lifetime' => " . var_export($entry['lifetime'], true) . ",\n"
                    . "        ],\n";
            } else {
                $rendered .= '        ' . var_export($id, true) . ' => ' . var_export($entry, true) . ",\n";
            }
        }

        return $rendered;
    }
}
