<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The worker answer's header map in the layer's canonical form, the
 * outbound fold {@see WorkerResponseEmitter} reads a foreign response
 * through.
 *
 * Foreign PSR-7 implementations skip the layer's construction-time
 * validation, so the emitter re-judges every field and SKIPS -- never
 * trusts, never coerces, never fails the answer -- the members whose
 * name or value lose the RFC 9110 grammars: the SAPI emission path's
 * own skip-on-invalid policy ({@see EmittedHeaders}), replayed on the
 * wire shape, because one attacker-shaped header must not cost the
 * answer its remaining, well-formed fields. The names lowercase to the
 * canonical representation {@see WorkerHeaders} answers (an integer
 * key cast to its decimal spelling, the engine's own fold -- the
 * KDD-H10 claim, replayed on the name), two spellings of one name merge
 * in encounter order (the map form of two header lines of one field),
 * and a non-string member drops exactly like an invalid name or value
 * -- the same rule the SAPI emission path applies.
 */
final class WorkerResponseHeaders
{
    /**
     * Answers the canonical header map, every member re-judged and the
     * unacceptable ones skipped.
     *
     * The value is deliberately mixed at the boundary -- the same
     * doctrine {@see HeaderGrammar::normalize()} states for the PSR-7
     * contract's own untyped parameter: the runtime checks are the
     * enforcement point, and a non-string member is skipped rather than
     * coerced.
     *
     * @param array<array-key, mixed> $raw the header map a response
     *                                     answers, names in any case,
     *                                     values string, list or the
     *                                     shape the grammar refuses
     *
     * @return array<array-key, list<string>> lowercased names => validated
     *                                        value lists (a numeric name
     *                                        lands under its integer engine
     *                                        form -- the fold the engine
     *                                        itself performs)
     */
    public static function acceptable(array $raw): array
    {
        $headers = [];

        foreach ($raw as $name => $value) {
            // The canonical spelling -- an integer key folds to its decimal form.
            $folded = mb_strtolower((string) $name);

            $members = \is_array($value) ? $value : [$value];
            foreach ($members as $member) {
                if (\is_string($member) && HeaderGrammar::acceptable($folded, $member)) {
                    $headers[$folded][] = $member;
                }
            }
        }

        return $headers;
    }
}
