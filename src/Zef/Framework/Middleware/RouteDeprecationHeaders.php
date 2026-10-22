<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

use DateTimeImmutable;
use DateTimeZone;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

/**
 * Renders one matched route's deprecation declaration onto its response:
 * the RFC 9745 `Deprecation` field (the deprecation instant, as the
 * structured-field date spelling `@<unix time>`), the RFC 8594 `Sunset`
 * field (the retirement instant, as the HTTP-date spelling), and the RFC
 * 8288 `Link` field with the `deprecation` relation RFC 9745 registers
 * (the documentation link, `<...>; rel="deprecation"`).
 *
 * The route declaration is the default posture; the handler's own answer
 * wins where it carries one. `Deprecation` and `Sunset` are single-valued
 * fields, so the declaration fills them only when the response does not
 * already carry them -- a handler that answers its own deprecation dates
 * keeps them, exactly like a handler's own `Vary` tokens survive the
 * route-level `Vary: Accept` fold. `Link` is a list-valued field, so the
 * deprecation link is added beside whatever links the response already
 * carries, skipping the addition only when the same target with the same
 * relation is already there.
 *
 * Called from the terminal dispatch ({@see RouteDispatchHandler}) on the
 * executed representation, so the headers ride every response the route
 * answers -- including the conditional lane's bodyless 304s, which keep
 * the headers ({@see NotModifiedResponse} rewrites status and body only)
 * so a cached representation's lifecycle metadata stays current.
 *
 * @internal
 */
final class RouteDeprecationHeaders
{
    /**
     * Applies the deprecation headers a route's declaration carries onto
     * its executed response; an undeclared (null) deprecation returns the
     * response untouched.
     *
     * @param null|array{date: int, sunset: null|int, link: null|non-empty-string} $deprecation
     *                                                                                          the route's canonicalised declaration
     */
    public function apply(ResponseInterface $response, ?array $deprecation): ResponseInterface
    {
        if ($deprecation === null) {
            return $response;
        }

        $response = $this->fill($response, 'Deprecation', '@' . $deprecation['date']);

        if ($deprecation['sunset'] !== null) {
            $response = $this->fill($response, 'Sunset', $this->httpDate($deprecation['sunset']));
        }

        if ($deprecation['link'] !== null) {
            return $this->addLink($response, $deprecation['link']);
        }

        return $response;
    }

    /**
     * Fills a single-valued header only when the response does not carry
     * it: the handler's own answer to that header wins over the route's
     * default posture.
     *
     * @param non-empty-string $name  the header's name
     * @param string           $value the header's value, non-empty where this is called
     *                                (a date spelling or an `@`-prefixed instant)
     */
    private function fill(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        if ($response->getHeaderLine($name) !== '') {
            return $response;
        }

        return $this->withHeader($response, $name, $value);
    }

    /**
     * Adds the deprecation link beside the links the response already
     * carries, unless the same target with the `deprecation` relation is
     * already there -- a list-valued field is appended, never replaced.
     *
     * @param non-empty-string $link the deprecation documentation URI-reference
     */
    private function addLink(ResponseInterface $response, string $link): ResponseInterface
    {
        $value = '<' . $link . '>; rel="deprecation"';

        foreach ($response->getHeader('Link') as $existing) {
            if (mb_strtolower($existing) === mb_strtolower($value)) {
                return $response;
            }
        }

        $message = $response->withAddedHeader('Link', $value);
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $message;
    }

    /**
     * The HTTP-date spelling of a sunset instant, in the GMT frame RFC
     * 8594's examples use.
     *
     * @param int $instant the sunset instant, never null where this is called
     */
    private function httpDate(int $instant): string
    {
        $date = new DateTimeImmutable('@' . $instant)->setTimezone(new DateTimeZone('GMT'));

        return $date->format('D, d M Y H:i:s') . ' GMT';
    }

    /**
     * The single-response header operation with the narrow-return guard
     * the dispatch lane's other folds apply.
     *
     * @param non-empty-string $name  the header's name
     * @param string           $value the header's value, non-empty where this is called
     */
    private function withHeader(ResponseInterface $response, string $name, string $value): ResponseInterface
    {
        $message = $response->withHeader($name, $value);
        if (!$message instanceof ResponseInterface) {
            throw new UnexpectedValueException('The response header operation did not return a ResponseInterface.');
        }

        return $message;
    }
}
