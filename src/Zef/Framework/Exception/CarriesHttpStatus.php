<?php

declare(strict_types=1);

namespace Zef\Framework\Exception;

/**
 * The contract of an exception whose failure semantics include the
 * client-facing HTTP status it answers -- the typed error family of the
 * error-handling lane (ROADMAP.md, "Error Handling System", phase 1:
 * the unified error object).
 *
 * Implementations are author-owned signals. The message a typed
 * exception carries is written for the client by contract (the
 * roadmap's "user-friendly message"), unlike an untyped
 * {@see ZefException} whose message may embed arbitrary internal detail
 * and therefore stays behind the dev-mode disclosure gate. The public
 * message is still run through the redaction engine by the disclosure
 * policy: a message written for the client must not smuggle credential
 * material an author happened to embed -- the v2.7.0 leak lesson,
 * generalised from the 500 arm to the whole family.
 *
 * The status map consults this capability instead of sniffing message
 * texts: one {@see \Zef\Framework\Http\ErrorStatusMap} tier resolves the
 * status for every implementer, so a new member of the family needs no
 * map edit and a host-defined implementer joins the same resolution
 * without touching framework code.
 */
interface CarriesHttpStatus
{
    /**
     * The client-facing status this failure answers -- an error status
     * in the 400..599 range.
     */
    public function httpStatus(): int;
}
