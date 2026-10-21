<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\ResponseInterface;

/**
 * Emits the status code and the validated header fields of a response
 * through the SAPI transmission seam.
 *
 * Foreign PSR-7 implementations skip the layer's construction-time
 * validation, so every field is re-judged here and skipped -- never
 * trusted, never fatal -- when its name or value fails the RFC 9110
 * grammars: one attacker-shaped header must not cost the response its
 * remaining, well-formed fields. This is the reference implementation's
 * skip-on-invalid policy with its catch-everything folded into the
 * infallible {@see HeaderGrammar::acceptable()} gate, and one deliberate
 * deviation from it: the reference coerced non-string field members
 * through the string cast, while this layer never coerces a header value
 * -- the same rule the header store applies to mutations -- so a
 * non-string member is dropped exactly like an invalid name or value.
 * For every well-formed field the observable behaviour is byte-identical.
 */
final class EmittedHeaders
{
    /**
     * Transmits the status code and the validated header fields, with
     * the framing the status may not carry dropped outright and a lying
     * Content-Length reconciled away before any framing reaches the
     * seam.
     */
    public static function transmit(ResponseInterface $response, SapiTransmitter $sapiTransmitter): void
    {
        $status = $response->getStatusCode();
        if ($status >= 100 && $status <= 599) {
            $sapiTransmitter->status($status);
        }

        $message = StatusFraming::honest($response, $status);

        $headerRows = self::rows($message);
        array_walk($headerRows, static function (mixed $values, int|string $name) use ($sapiTransmitter): void {
            $fieldName = (string) $name;
            $members = \is_array($values) ? $values : [$values];
            array_walk(
                $members,
                static function (mixed $value) use ($sapiTransmitter, $fieldName): void {
                    if (!\is_string($value) || !HeaderGrammar::acceptable($fieldName, $value)) {
                        return;
                    }

                    $sapiTransmitter->field($fieldName . ': ' . $value, false);
                },
            );
        });
    }

    /**
     * The foreign header map as the emitter reads it.
     *
     * The contract promises a map of name to list-of-strings, but a
     * foreign implementation is free to lie at runtime (the PSR-7
     * signature only enforces `array`); the declared width is the honest
     * reading -- the same key-folding honesty the superglobal factory
     * applies at its bag seams (KDD-H10) -- while every narrower claim
     * the contract makes is honoured by the implementations that keep it.
     *
     * @param MessageInterface $message the response whose header map is read
     *
     * @return array<array-key, mixed> the raw header map, exactly as the
     *                                 implementation answers it
     */
    private static function rows(MessageInterface $message): array
    {
        return $message->getHeaders();
    }
}
