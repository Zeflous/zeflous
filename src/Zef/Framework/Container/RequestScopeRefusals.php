<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

/**
 * The request-scope refusal messages -- every way a request-scoped
 * resolution can be refused its scope ({@see RequestInstances}' and
 * {@see RequestScope}'s fail-closed verdicts).
 *
 * Split from the scope machinery so the vocabulary of the refusals (the
 * operand weight of the messages) stays its own archeology-sized unit:
 * the machinery keeps the lanes, this class keeps the words -- the same
 * word-class split {@see Autowiring\AutowireRefusals} made.
 */
final readonly class RequestScopeRefusals
{
    /**
     * A request-scoped id resolved with no scope open: the way out --
     * open one and resolve through it -- rides the message.
     */
    public static function outsideScope(string $id): RequestScopeException
    {
        return new RequestScopeException(
            "Service '{$id}' is request-scoped and cannot be resolved outside a request scope "
            . '-- open one with the container and resolve through it.',
        );
    }

    /**
     * A request-scoped id resolved through a scope released while its
     * own resolution still runs (a factory closing the very scope that
     * is building it).
     */
    public static function closedMidResolution(string $id): RequestScopeException
    {
        return new RequestScopeException(
            "Service '{$id}' resolves through a request scope that was closed mid-resolution "
            . '(a factory closed the scope its own resolution still runs in).',
        );
    }

    /**
     * A resolution attempted through a scope already closed: the
     * request it named is over, and an instance built after it would
     * belong to no request.
     */
    public static function closed(string $id): RequestScopeException
    {
        return new RequestScopeException(
            'The request scope is closed -- the request it named is over; '
            . "service '{$id}' cannot be resolved through it.",
        );
    }
}
