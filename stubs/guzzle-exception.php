<?php

declare(strict_types=1);

/*
 * Static-analysis stub for the optional Guzzle contract the third-party
 * exception mapper recognises at the HTTP boundary (suggested-only in
 * composer.json, never a runtime dependency): the marker interface every
 * Guzzle 7 transport failure implements. The stub keeps psalm honest in
 * environments that do not carry Guzzle -- the mapper's `is_a()` string
 * target resolves to this contract at analysis time while staying a lazy
 * string lookup at runtime (a missing interface simply answers false),
 * mirroring the ext-redis stub's role for the rate-limit boundary.
 */

namespace GuzzleHttp\Exception;

interface GuzzleException extends \Throwable
{
}
