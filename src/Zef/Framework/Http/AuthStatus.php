<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The authentication resolution's statuses: what a credential provider
 * answered about the credential it was handed, move-verbatim from the
 * reference's distributed security family (legacy `zef-main`
 * `Framework/Security/Distributed/AuthenticationStatus.php`).
 *
 * The five statuses are the provider contract's whole vocabulary:
 * `AUTHENTICATED` is the only one that carries a security context
 * ({@see AuthResult}'s constructor invariant), `UNAUTHENTICATED` is
 * the anonymous resolution a provider vouches for or refuses,
 * `EXPIRED` tells the client to re-authenticate, `FAILED` is the
 * wrong-credential answer, and `UNAVAILABLE` is the provider's own
 * outage -- never a verdict about the credential, which is why the
 * middleware answers it with the fail-closed 503 rather than a 401
 * the client could only retry identically.
 */
enum AuthStatus: string
{
    case UNAUTHENTICATED = 'unauthenticated';

    case AUTHENTICATED = 'authenticated';

    case FAILED = 'failed';

    case UNAVAILABLE = 'unavailable';

    case EXPIRED = 'expired';
}
