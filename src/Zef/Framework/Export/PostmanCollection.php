<?php

declare(strict_types=1);

namespace Zef\Framework\Export;

use Zef\Framework\Config\Routing\CompiledRoute;

/**
 * The Postman Collection v2.1.0 document: the whole route table as
 * one importable collection -- the roadmap's ecosystem export in
 * Postman's own schema, one item per route-and-method pair
 * ({@see PostmanItem}).
 *
 * The collection's identity is DETERMINISTIC BY CONSTRUCTION: the
 * `_postman_id` is the SHA-1 of the table's own canonical encoding
 * (the fields the engine declares, in declaration order), so the same
 * table always answers the same id -- a re-export over an unchanged
 * table is a no-op in Postman's collection history, and a changed
 * table is a visibly different id, the diff-friendly spelling the
 * roadmap's tool-integration story asks for. The id is a rendered
 * hash, not a generated GUID: it identifies the TABLE, not this
 * export run.
 *
 * The name the collection carries is the caller-declared title (the
 * schema demands one, the table owns none), and the schema pointer is
 * the constant the format itself pins. Everything else is the items
 * -- one per (entry, method) pair, in the tree's declaration order.
 *
 * @api
 */
final readonly class PostmanCollection
{
    /** The collection schema the format pins. */
    public const string SCHEMA_URL = 'https://schema.getpostman.com/json/collection/v2.1.0/collection.json';

    /** @param list<CompiledRoute> $routes */
    private function __construct(
        private array $routes,
        private string $name,
    ) {
    }

    /**
     * @param list<CompiledRoute> $routes the tree's routes, in declaration order
     * @param string              $name   the collection's name, as the caller declares it
     */
    public static function fromRoutes(array $routes, string $name): self
    {
        return new self($routes, self::validated($name));
    }

    /**
     * The collection as its JSON form carries it.
     *
     * @return array<string, mixed> the Collection Object: `info` (name, id, schema) and `item`
     */
    public function toArray(): array
    {
        $items = [];

        foreach ($this->routes as $route) {
            foreach ($route->methods() as $method) {
                $items[] = PostmanItem::fromRoute($route, $method);
            }
        }

        return [
            'info' => [
                'name' => $this->name,
                '_postman_id' => $this->identifier($this->routes),
                'schema' => self::SCHEMA_URL,
            ],
            'item' => $items,
        ];
    }

    /**
     * The deterministic collection identifier: the SHA-1 of the
     * table's canonical encoding, rendered in the 8-4-4-4-12 spelling
     * Postman's own ids carry -- the same table always answers the
     * same id, a changed table a different one.
     *
     * @param list<CompiledRoute> $routes
     *
     * @return non-empty-string
     */
    private function identifier(array $routes): string
    {
        $canonical = [];

        foreach ($routes as $route) {
            $canonical[] = [
                $route->path()->pattern(),
                $route->methods(),
                $route->name(),
                $route->host(),
                $route->version(),
                $route->middleware(),
                $route->produces(),
                $route->handler(),
            ];
        }

        $hash = hash('sha1', (string) json_encode($canonical, \JSON_UNESCAPED_SLASHES));

        return \sprintf(
            '%s-%s-%s-%s-%s',
            mb_substr($hash, 0, 8),
            mb_substr($hash, 8, 4),
            mb_substr($hash, 12, 4),
            mb_substr($hash, 16, 4),
            mb_substr($hash, 20, 12),
        );
    }

    /** @throws ExportException when the collection name is empty, unprintable, or over the ceiling */
    private static function validated(string $name): string
    {
        if ($name === '' || mb_strlen($name) > 256 || preg_match('/[\x00-\x1f\x7f]/', $name) === 1) {
            throw new ExportException(
                'The Postman collection name must be non-empty, printable, and at most 256 characters.',
            );
        }

        return $name;
    }
}
