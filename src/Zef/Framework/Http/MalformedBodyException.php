<?php

declare(strict_types=1);

namespace Zef\Framework\Http;

/**
 * The malformed-body refusal: the request's body failed the grammar
 * of the media type the family owns -- the 400 signal.
 *
 * The refusal extends the layer's exception root and stops there: it
 * deliberately does NOT implement {@see \Zef\Framework\Exception\CarriesHttpStatus},
 * because the status it must reach (400) is the coarse status the
 * error status map's second tier already answers for every
 * {@see HttpException} ({@see ErrorStatusMap}) -- the typed tier
 * exists for the signals whose status DIFFERS from the coarse
 * default ({@see PayloadTooLargeException} 413). The class still
 * earns its name: a malformed body is a distinct refusal hosts catch
 * structurally, and the message-sniffing the typed tier retired never
 * returns -- "a refusal that must reach a status code is a class, not
 * a string comparison".
 *
 * Leaf signals stay final ({@see HttpException}'s own doctrine), and
 * the constructor stays the root's: the message is the throw site's
 * honest sentence, with the engine's own diagnostic riding as the
 * previous throwable where one exists (the JSON strategy's
 * `JsonException`, the XML strategy's libxml detail).
 */
final class MalformedBodyException extends HttpException
{
}
