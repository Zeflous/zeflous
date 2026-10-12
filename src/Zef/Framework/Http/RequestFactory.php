<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * Builds the inbound server request from the SAPI process state.
 *
 * {@see fromGlobals()} is the superglobal adapter -- the ONLY reader of
 * $_SERVER/$_GET/$_COOKIE/$_POST/$_FILES in the layer -- and
 * {@see fromServer()} is its injectable twin: identical behaviour, but
 * every source array is passed explicitly so the factory itself never
 * consults process-global state (a long-running worker that reuses the
 * process can no longer observe stale or foreign request data through
 * silent fallbacks; a null argument means "not supplied", never "fall back
 * to the superglobal"). The method token is deliberately normalised to
 * uppercase at this single ingress boundary, the SAPI-decoded form body is
 * honoured only for the two form media types (every other content type
 * starts null and is decoded downstream), and the raw `php://input` stream
 * is opened exactly once through the layer's {@see StreamOpener} seam --
 * the SAPI contract that `php://input` cannot be re-read is preserved by
 * never re-opening it.
 *
 * The bags carry the PSR contracts' string-key claim stated at this
 * boundary: PHP folds the numeric-string array key into its integer form
 * on every insert, so a SAPI bag's integer keys are that key's canonical
 * engine form and the string-keyed and array-keyed views of the same bag
 * are observationally identical (an integer key cannot be distinguished
 * from the numeric-string key it folds into). The factory's parameters
 * stay honestly array-keyed; the constructor seams re-type the three bags
 * into the contracts' refined view -- the single point where the engine's
 * loose superglobal typing meets the vendored contracts' stricter one.
 */
final class RequestFactory
{
    /**
     * Builds the request from the SAPI superglobals.
     *
     * @return ServerRequest the inbound request the SAPI state describes
     *
     * @throws HttpException when any derived component fails the layer's
     *                       validation (method grammar, target grammar, ...)
     */
    public static function fromGlobals(): ServerRequest
    {
        /** @var array<string, mixed> $server the engine's process state (keys fold: class docblock) */
        $server = $_SERVER;

        return self::fromServer(
            $server,
            $_GET,
            $_COOKIE,
            $_POST !== [] ? $_POST : null,
            $_FILES,
        );
    }

    /**
     * Builds the request from explicitly supplied SAPI source arrays.
     *
     * Omitted (null) bags default to empty; the parsed body argument
     * mirrors what the SAPI would have decoded into $_POST and is honoured
     * only for the two form media types -- every other content type starts
     * null and is decoded downstream.
     *
     * @param array<string, mixed>          $server        the SAPI server entries, typically $_SERVER
     * @param null|array<mixed>             $query         the query bag, typically $_GET
     * @param null|array<mixed>             $cookies       the cookie bag, typically $_COOKIE
     * @param null|array<mixed>             $parsedBody    the SAPI-decoded form body, typically $_POST
     * @param null|array<int|string, mixed> $uploadedFiles the upload specification, typically $_FILES
     *
     * @return ServerRequest the inbound request the sources describe
     *
     * @throws HttpException when any derived component fails the layer's
     *                       validation (method grammar, target grammar, ...)
     */
    public static function fromServer(
        array $server,
        ?array $query = null,
        ?array $cookies = null,
        ?array $parsedBody = null,
        ?array $uploadedFiles = null,
    ): ServerRequest {
        $headers = ServerHeaders::extract($server);

        /** @var array<string, mixed> $cookieBag the cookie bag (keys fold: class docblock) */
        $cookieBag = $cookies ?? [];

        /** @var array<string, mixed> $queryBag the query bag (keys fold: class docblock) */
        $queryBag = $query ?? [];

        /** @var array<string, mixed> $uploads the normalised upload tree (keys fold: class docblock) */
        $uploads = ServerUploads::normalize($uploadedFiles ?? []);

        return new ServerRequest(
            mb_strtoupper(ServerValues::text($server, 'REQUEST_METHOD', 'GET')),
            ServerUri::build($server),
            $server,
            $cookieBag,
            $queryBag,
            $uploads,
            self::formBody($headers, $parsedBody),
            $headers,
            new Stream(StreamOpener::open('php://input', 'rb')),
            ServerProtocol::version(ServerValues::text($server, 'SERVER_PROTOCOL', 'HTTP/1.1')),
            ServerValues::text($server, 'REQUEST_URI', ''),
        );
    }

    /**
     * Answers the parsed body the SAPI decoded, honoured only for the two
     * form media types; every other content type answers null. The media
     * type comparison is byte-wise case-insensitive (`strcasecmp`) over
     * the type segment before any `;` parameter -- the fold never crosses
     * a multibyte boundary because only the ASCII media type spelling
     * ever matches.
     *
     * @param array<string, list<string>> $headers    the extracted header map
     * @param null|array<mixed>           $parsedBody the SAPI-decoded form body
     *
     * @return null|array<mixed> the honoured form body, or null
     */
    private static function formBody(array $headers, ?array $parsedBody): ?array
    {
        $contentType = mb_trim(explode(
            ';',
            $headers['content-type'][0] ?? '',
        )[0]);

        if (
            strcasecmp($contentType, 'application/x-www-form-urlencoded') === 0
            || strcasecmp($contentType, 'multipart/form-data') === 0
        ) {
            return $parsedBody;
        }

        return null;
    }
}
