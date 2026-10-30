<?php

declare(strict_types=1);

namespace Zef\Test\Middleware;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Adapters\Session\FileSessionAdapter;
use Zef\Framework\Config\Config;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Http\ServerRequest;
use Zef\Framework\Http\Uri;
use Zef\Framework\Middleware\SessionFactory;
use Zef\Framework\Middleware\SessionStorageWiring;
use Zef\Framework\Storage\StorageLane;
use Zef\Test\Support\Http\SessionObservingHandler;
use Zef\Test\Support\Http\SessionWritingHandler;

/**
 * The session middleware's construction seam, on its own terms: the
 * build from the frozen subtree (a decorated request whose session
 * cookie carries the name the subtree declared, not the constructor
 * default), the absent family's armed default posture, and the
 * disarmed pass-through -- through the factory the profile's own
 * `http.session` service registration names -- plus the storage
 * driver's own composition: the `file` store word wiring the
 * filesystem adapter over an explicitly spelled path or the storage
 * layout's `sessions` lane, the bag surviving a whole fresh
 * middleware instance through the record file, and the named halt
 * the `file` word without an address answers.
 *
 * The census halts (the unknown family, the non-map root, the unknown
 * knob) are the family's own and are pinned where they live ({@see
 * \Zef\Test\Http\SessionPolicyTest}); this test pins the wiring the
 * factory alone owns: the subtree read and the policy handed over.
 *
 * @internal
 */
#[CoversClass(SessionFactory::class)]
#[CoversClass(SessionStorageWiring::class)]
#[CoversClass(FileSessionAdapter::class)]
final class SessionFactoryTest extends TestCase
{
    public function testTheFactoryBuildsTheBoundaryFromTheFrozenSubtree(): void
    {
        $config = new Config([
            'http' => [
                'session' => [
                    'cookie_name' => 'shop_session',
                ],
            ],
        ]);
        $sessionWritingHandler = new SessionWritingHandler();

        $response = SessionFactory::fromConfig($config)
            ->process($this->request(), $sessionWritingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($sessionWritingHandler->state, 'the factory-built boundary decorates the session bag');
        self::assertMatchesRegularExpression(
            '/^shop_session=[A-Za-z0-9_-]{43};/',
            $response->getHeaderLine('Set-Cookie'),
            "the subtree's own cookie name rides the issuance, not the constructor default",
        );
    }

    public function testTheFactoryBuildsTheDefaultArmedPostureFromTheAbsentFamily(): void
    {
        $sessionWritingHandler = new SessionWritingHandler();

        $response = SessionFactory::fromConfig(new Config([]))
            ->process($this->request(), $sessionWritingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNotNull($sessionWritingHandler->state);
        self::assertTrue($sessionWritingHandler->state->isNew(), 'the absent family reads the armed default posture');
        self::assertMatchesRegularExpression(
            '/^zef_session=[A-Za-z0-9_-]{43};/',
            $response->getHeaderLine('Set-Cookie'),
        );
    }

    public function testTheFactoryBuildsADisarmedPassThrough(): void
    {
        $config = new Config([
            'http' => [
                'session' => [
                    'enabled' => false,
                ],
            ],
        ]);
        $sessionObservingHandler = new SessionObservingHandler();

        $response = SessionFactory::fromConfig($config)
            ->process($this->request(), $sessionObservingHandler)
        ;

        self::assertSame(200, $response->getStatusCode());
        self::assertNull($sessionObservingHandler->state, 'a disabled family is the inert pass-through');
        self::assertSame([], $response->getHeader('Set-Cookie'));
    }

    public function testTheFileStoreWordComposesTheFileDriverOverTheSpelledPath(): void
    {
        $store = sys_get_temp_dir() . '/zef-session-factory-' . bin2hex(random_bytes(8));
        mkdir($store, 0o700);

        try {
            $config = new Config([
                'http' => ['session' => ['store' => 'file', 'path' => $store]],
            ]);

            $sessionWritingHandler = new SessionWritingHandler();
            $response = SessionFactory::fromConfig($config)
                ->process($this->request(), $sessionWritingHandler)
            ;

            self::assertMatchesRegularExpression(
                '/^zef_session=[A-Za-z0-9_-]{43};/',
                $response->getHeaderLine('Set-Cookie'),
                'the first exchange answers the issuance cookie',
            );

            $identifier = mb_substr(
                $response->getHeaderLine('Set-Cookie'),
                mb_strlen('zef_session='),
                43,
            );

            $sessionObservingHandler = new SessionObservingHandler();
            SessionFactory::fromConfig($config)
                ->process($this->request(['zef_session' => $identifier]), $sessionObservingHandler)
            ;

            self::assertNotNull($sessionObservingHandler->state);
            self::assertFalse(
                $sessionObservingHandler->state->isNew(),
                'the record file answers a whole fresh middleware instance',
            );
            self::assertSame(
                7,
                $sessionObservingHandler->state->value('user'),
                'the bag the first exchange wrote is the bag the second loads',
            );
        } finally {
            $entries = glob($store . '/*');
            foreach (\is_array($entries) ? $entries : [] as $entry) {
                if (!is_file($entry)) {
                    continue;
                }

                unlink($entry);
            }

            rmdir($store);
        }
    }

    public function testTheFileStoreWordDerivesTheDirectoryFromTheDeclaredRoot(): void
    {
        $root = sys_get_temp_dir() . '/zef-session-factory-root-' . bin2hex(random_bytes(8));
        mkdir($root, 0o700);

        foreach (StorageLane::cases() as $lane) {
            mkdir($root . '/' . $lane->spelling(), 0o700);
        }

        try {
            $config = new Config([
                'http' => ['session' => ['store' => 'file']],
                'storage' => ['root' => $root],
            ]);

            $sessionWritingHandler = new SessionWritingHandler();
            $response = SessionFactory::fromConfig($config)
                ->process($this->request(), $sessionWritingHandler)
            ;

            $identifier = mb_substr(
                $response->getHeaderLine('Set-Cookie'),
                mb_strlen('zef_session='),
                43,
            );

            self::assertMatchesRegularExpression(
                '/^zef_session=[A-Za-z0-9_-]{43};/',
                $response->getHeaderLine('Set-Cookie'),
            );

            $sessionObservingHandler = new SessionObservingHandler();
            SessionFactory::fromConfig($config)
                ->process($this->request(['zef_session' => $identifier]), $sessionObservingHandler)
            ;

            self::assertNotNull($sessionObservingHandler->state);
            self::assertSame(
                7,
                $sessionObservingHandler->state->value('user'),
                'the sessions lane the root provisions is the directory the composition wired',
            );
        } finally {
            $this->removeTree($root);
        }
    }

    public function testTheFileStoreWordWithoutAnAddressHalts(): void
    {
        try {
            SessionFactory::fromConfig(new Config([
                'http' => ['session' => ['store' => 'file']],
            ]));
            self::fail('The address-less file store word was not halted.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The http.session file store needs a directory: declare storage.root'
                . ' or spell http.session.path.',
                $configException->getMessage(),
            );
        }
    }

    /**
     * One cookieless request over the http scheme.
     *
     * @param array<string, mixed> $cookieParams the request's own cookie bag
     */
    private function request(array $cookieParams = []): ServerRequest
    {
        return new ServerRequest(
            'GET',
            new Uri('http://app.example.com/'),
            cookieParams: $cookieParams,
        );
    }

    private function removeTree(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $entries = scandir($directory);

        foreach (\is_array($entries) ? $entries : [] as $base) {
            if ($base === '.' || $base === '..') {
                continue;
            }

            $entry = $directory . \DIRECTORY_SEPARATOR . $base;

            if (is_dir($entry)) {
                $this->removeTree($entry);
            } else {
                unlink($entry);
            }
        }

        rmdir($directory);
    }
}
