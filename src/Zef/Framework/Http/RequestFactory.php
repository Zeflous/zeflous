<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

use Psr\Http\Message\ServerRequestInterface;

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
 *
 * The ingress policy closes the input validation gate's eager half: every
 * request body is served under a {@see RequestBodyPolicy} -- the caller's
 * or the default two-mebibyte posture, never unlimited -- and the
 * `Content-Length` claim is judged before the body is even opened, so an
 * oversized claim fails fast ({@see RequestBodyPolicy::claimExceeds()})
 * while the delivered stream stays lazily policed by the
 * {@see LimitedInputStream} wrapper (the eager and the lazy halves are the
 * same limit; a lying or absent header only decides WHICH half throws).
 *
 * The same policy is also enforced for requests that were NOT built here:
 * {@see validateIngress()} guards an already constructed PSR-7 request --
 * the persistent-worker path, where a foreign factory or a reused process
 * hands the application a request nobody opened under a policy. The
 * trusted-host half ({@see TrustedRequestHost}) pins the request's hosts
 * to the application's allow-list through the trusted-proxy boundary, and
 * the body half ({@see IngressBody}) re-plays the eager claim, size and
 * read-ahead probe judgements against the delivered stream.
 */
final class RequestFactory
{
    /**
     * Builds the request from the SAPI superglobals.
     *
     * @param null|RequestBodyPolicy $requestBodyPolicy the ingress policy for the
     *                                                  request body; null answers the
     *                                                  default two-mebibyte limit
     *
     * @return ServerRequest the inbound request the SAPI state describes
     *
     * @throws HttpException when any derived component fails the layer's
     *                       validation (method grammar, target grammar, ...)
     *                       or the `Content-Length` claim already exceeds the
     *                       ingress policy
     */
    public static function fromGlobals(?RequestBodyPolicy $requestBodyPolicy = null): ServerRequest
    {
        /** @var array<string, mixed> $server the engine's process state (keys fold: class docblock) */
        $server = $_SERVER;

        return self::fromServer(
            $server,
            $_GET,
            $_COOKIE,
            $_POST !== [] ? $_POST : null,
            $_FILES,
            $requestBodyPolicy,
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
     * @param array<string, mixed>          $server            the SAPI server entries, typically $_SERVER
     * @param null|array<mixed>             $query             the query bag, typically $_GET
     * @param null|array<mixed>             $cookies           the cookie bag, typically $_COOKIE
     * @param null|array<mixed>             $parsedBody        the SAPI-decoded form body, typically $_POST
     * @param null|array<int|string, mixed> $uploadedFiles     the upload specification, typically $_FILES
     * @param null|RequestBodyPolicy        $requestBodyPolicy the ingress policy for the request body;
     *                                                         null answers the default two-mebibyte limit
     *
     * @return ServerRequest the inbound request the sources describe
     *
     * @throws HttpException when any derived component fails the layer's
     *                       validation (method grammar, target grammar, ...)
     *                       or the `Content-Length` claim already exceeds the
     *                       ingress policy
     */
    public static function fromServer(
        array $server,
        ?array $query = null,
        ?array $cookies = null,
        ?array $parsedBody = null,
        ?array $uploadedFiles = null,
        ?RequestBodyPolicy $requestBodyPolicy = null,
    ): ServerRequest {
        $headers = ServerHeaders::extract($server);

        $requestBodyPolicy ??= new RequestBodyPolicy();
        if ($requestBodyPolicy->claimExceeds($headers['content-length'][0] ?? '')) {
            throw new HttpException('Request body exceeds configured size limit.');
        }

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
            new LimitedInputStream(
                new Stream(StreamOpener::open('php://input', 'rb')),
                $requestBodyPolicy,
            ),
            ServerProtocol::version(ServerValues::text($server, 'SERVER_PROTOCOL', 'HTTP/1.1')),
            ServerValues::text($server, 'REQUEST_URI', ''),
        );
    }

    /**
     * Enforces the application ingress policy for an already constructed
     * request, including persistent workers that do not pass through
     * {@see fromServer()}.
     *
     * The trusted-host half runs only when an allow-list is configured (an
     * empty list means pinning is not configured, not "trust nothing");
     * the body half always runs.
     *
     * @param ServerRequestInterface $serverRequest     the request entering the application
     *                                                  ingress
     * @param list<string>           $trustedHosts      the trusted host allow-list
     * @param list<string>           $trustedProxies    the trusted proxy entries
     * @param RequestBodyPolicy      $requestBodyPolicy the ingress policy the request body serves
     *
     * @return ServerRequestInterface the request to dispatch (its body guarded,
     *                                non-seekable bodies replaced by their probed
     *                                contents -- see {@see IngressBody::guard()})
     *
     * @throws HttpException when either host form is untrusted, the request
     *                       carries no host, or the body exceeds the policy
     */
    public static function validateIngress(
        ServerRequestInterface $serverRequest,
        array $trustedHosts,
        array $trustedProxies,
        RequestBodyPolicy $requestBodyPolicy,
    ): ServerRequestInterface {
        if ($trustedHosts !== []) {
            TrustedRequestHost::assert($serverRequest, $trustedHosts, $trustedProxies);
        }

        return IngressBody::guard($serverRequest, $requestBodyPolicy);
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
