<?php

declare(strict_types=1);

namespace Zef\Framework\Middleware;

/**
 * The Swagger UI shell: the one HTML page the OpenAPI UI endpoint
 * serves -- a minimal, standard, asset-external document that loads
 * the Swagger UI distribution the caller pointed at and points it at
 * the specification document the same middleware serves.
 *
 * The shell owns no logic and embeds no data beyond the title
 * (HTML-escaped, the one interpolation a host's string gets) and the
 * two URLs: the asset base the constructor declared and the spec
 * path the family serves. Everything the page renders comes from the
 * spec JSON itself -- the shell is the standard `index.html` the
 * Swagger UI distribution ships, reduced to the constants this
 * family pins, so a self-hosted asset and a CDN one are the same
 * page.
 *
 * @internal consumed through {@see OpenApiUiMiddleware}
 */
final readonly class OpenApiUiMarkup
{
    /** The spec document's path, as the shell points Swagger UI at it. */
    private const string SPEC_PATH = '/openapi.json';

    /**
     * The shell document for one titled UI over one asset base.
     *
     * @param string           $title    the API's title, HTML-escaped into the page's
     *                                   own title (the identity fold owns its grammar)
     * @param non-empty-string $assetUrl the Swagger UI distribution's base URL (directory)
     *
     * @return non-empty-string the complete HTML document
     */
    public static function shell(string $title, string $assetUrl): string
    {
        $escapedTitle = htmlspecialchars($title, \ENT_QUOTES | \ENT_SUBSTITUTE, 'UTF-8');
        $base = mb_rtrim($assetUrl, '/');

        return "<!doctype html>\n"
            . "<html lang=\"en\">\n"
            . "<head>\n"
            . "    <meta charset=\"utf-8\">\n"
            . "    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">\n"
            . "    <title>{$escapedTitle}</title>\n"
            . "    <link rel=\"stylesheet\" href=\"{$base}/swagger-ui.css\">\n"
            . "</head>\n"
            . "<body>\n"
            . "    <div id=\"swagger-ui\"></div>\n"
            . "    <script src=\"{$base}/swagger-ui-bundle.js\"></script>\n"
            . "    <script src=\"{$base}/swagger-ui-standalone-preset.js\"></script>\n"
            . "    <script>\n"
            . "        window.onload = function () {\n"
            . "            window.ui = SwaggerUIBundle({\n"
            . "                url: '" . self::SPEC_PATH . "',\n"
            . "                dom_id: '#swagger-ui',\n"
            . "                presets: [\n"
            . "                    SwaggerUIBundle.presets.apis,\n"
            . "                    SwaggerUIStandalonePreset\n"
            . "                ],\n"
            . "                layout: 'StandaloneLayout'\n"
            . "            });\n"
            . "        };\n"
            . "    </script>\n"
            . "</body>\n"
            . "</html>\n";
    }
}
