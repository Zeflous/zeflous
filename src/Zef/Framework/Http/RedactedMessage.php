<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The dev-mode diagnostic message with embedded credential material masked.
 *
 * Staged scanning (the flat candidate pattern finds `key separator value`
 * regions, PHP verifies the key against the credential vocabulary), with
 * edits applied right-to-left so they never overlap. Once a credential key
 * matches and its first value token is an auth scheme keyword ("Authorization:
 * Bearer abc123xyz"), the whole remaining region on that line is consumed:
 * the candidate value class stops at whitespace, so every token after the
 * keyword would otherwise leak (legacy issue #307). Single-token values keep
 * the historical behaviour of preserving trailing prose ("password=hunter2
 * gone" becomes "password=[REDACTED] gone") so diagnostics stay readable.
 *
 * Attribution: legacy `zef-framework` `Middleware/ErrorResponseFactory`
 * (`redactSecrets()`, move-verbatim behaviour). Two restructurings for
 * observability, both verified against the engine: the scan loop's exit
 * condition is the match result itself, not a byte bound (an offset bound
 * compares equal at the boundary for a pattern that can never match empty),
 * and the non-credential advance resumes at the quote capture's own offset
 * -- the value region's first byte, the earliest position a further
 * candidate could ever start at -- instead of the legacy `+ 1` literal (a
 * candidate can never start inside the matched key or the separator region:
 * a word boundary cannot occur mid-word, and no separator byte is a key
 * byte). The capture form carries no arithmetic to mutate: its decrement
 * direction is the grammar-equivalent separator offset (the one documented
 * ignore in `infection.json5`), while its increment direction reads an
 * undefined capture and the engine answers an undefined-dimension error.
 */
final class RedactedMessage
{
    /**
     * Candidate `key (separator) value` regions; the key is verified against
     * the credential vocabulary in PHP. Conservative ASCII classes (the /i
     * flag covers case) so normal prose is left untouched -- the explicit
     * ASCII classes are the contract, and the pattern runs in byte mode so
     * it also works over messages that are not valid UTF-8.
     */
    private const string REDACTION_CANDIDATE_RE
        = '/\b([a-z][a-z0-9_-]{2,})\b(\s*[=:]\s*|\s+)(["\']?)[a-z0-9._\/-]{4,}\3/i';

    /**
     * Answers the message with every credential value it embeds replaced by
     * the masked marker, everything else byte-identical.
     */
    public static function of(string $message): string
    {
        /** @var list<array{int, int, string}> $edits [byte offset, byte length, replacement] */
        $edits = [];
        $offset = 0;
        while (true) {
            $found = preg_match(self::REDACTION_CANDIDATE_RE, $message, $match, \PREG_OFFSET_CAPTURE, $offset);
            if ($found !== 1) {
                break;
            }

            $edit = RedactionCandidate::edit($message, $match);
            if ($edit === null) {
                // Not a credential key: resume the scan at the quote
                // capture's own offset -- the first byte of the value
                // region, the earliest position a further candidate could
                // ever start at.
                $offset = $match[3][1];

                continue;
            }

            $edits[] = $edit;
            $offset = $edit[0] + $edit[1];
        }

        foreach (array_reverse($edits) as [$start, $length, $replacement]) {
            $message = substr_replace($message, $replacement, $start, $length);
        }

        return $message;
    }
}
