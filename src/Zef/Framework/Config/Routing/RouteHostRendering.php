<?php

declare(strict_types=1);

namespace Zef\Framework\Config\Routing;

use Stringable;
use Zef\Framework\Config\ConfigException;

/**
 * The reverse-routing face of host parameters: rendering a declared host
 * pattern's whole-label parameters into a concrete DNS hostname, with every
 * value validated as a DNS label fail-closed.
 *
 * Anonymous wildcard labels ("*") carry no name, so a pattern containing
 * one cannot be rendered: it is refused rather than emitted as a
 * pattern-shaped pseudo-hostname.
 *
 * @internal
 */
final class RouteHostRendering
{
    private const string DNS_LABEL = '/\A[a-zA-Z0-9](?:[a-zA-Z0-9-]{0,61}[a-zA-Z0-9])?\z/D';

    /**
     * Renders a host pattern with validated DNS-label parameter values.
     *
     * @param array<string, mixed> $parameters
     */
    public static function render(string $pattern, array $parameters): string
    {
        foreach (explode('.', $pattern) as $label) {
            if (RouteHostLabelKinds::isAnonymousWildcard($label)) {
                throw new ConfigException(\sprintf(
                    'The host pattern "%s" carries an anonymous "*" wildcard and cannot be rendered.',
                    $pattern,
                ));
            }
        }

        foreach (RouteHostParameters::parameterNames($pattern) as $name) {
            if (!\array_key_exists($name, $parameters)) {
                throw new ConfigException(\sprintf('The host parameter "%s" is required.', $name));
            }

            $value = $parameters[$name];
            if (!\is_scalar($value) && !$value instanceof Stringable) {
                throw new ConfigException(\sprintf('The host parameter "%s" must be a DNS label value.', $name));
            }

            $label = (string) $value;
            if (preg_match(self::DNS_LABEL, $label) !== 1) {
                throw new ConfigException(\sprintf('The host parameter "%s" must be a valid DNS label.', $name));
            }

            $pattern = str_replace('{' . $name . '}', mb_strtolower($label), $pattern);
            unset($parameters[$name]);
        }

        return $pattern;
    }
}
