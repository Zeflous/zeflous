<?php

declare(strict_types=1);

namespace Zef\Framework\Container;

use Zef\Framework\Contracts\ContainerException;

/**
 * A request-scoped service resolved outside a request scope -- or
 * through a scope already closed ({@see RequestScope}).
 *
 * The refusal is the honest one: a request-scoped declaration promises
 * its instances live no longer than one scope, so resolving it at the
 * container's root would either build it forever (the singleton it
 * refused to be) or throw away the scope's own memo -- neither is the
 * declared semantics. The message names the way out: open a scope and
 * resolve through it.
 */
final class RequestScopeException extends ContainerException
{
}
