<?php

declare(strict_types=1);

namespace Zef\Test\Support\Container\Namespaces;

/**
 * The fallback lane's unlabelled product: a fresh instance every
 * construction, distinguishable only by identity -- the shape the
 * memoisation and transient tests observe (a named fixture, because
 * an anonymous class's closing brace trips the style sniffer).
 */
final class FreshProduct
{
}
