<?php

declare(strict_types=1);

namespace Zef\Framework\Router;

use Zef\Framework\Exception\ZefException;

/**
 * Raised when route data is malformed or a route cannot be registered.
 *
 * The router layer is fail-closed at registration time: a malformed route
 * path, an invalid entry shape, a bad constraint or a duplicate route is an
 * error, never a silently accepted or half-registered route (see
 * `ROADMAP.md`, "Router (Enterprise-Grade Radix Tree)" -- constraint and
 * duplicate problems are caught "saat registrasi", not at match time).
 *
 * The exception is reserved for *configuration* errors. Match-time inputs
 * (the request method and path) never raise: an unmatched request resolves
 * to a not-found or method-not-allowed result instead, because a hostile or
 * malformed URL is runtime data, not a defect in the route table.
 */
final class RouterException extends ZefException
{
}
