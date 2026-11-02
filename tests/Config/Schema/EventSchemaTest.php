<?php

declare(strict_types=1);

namespace Zef\Test\Config\Schema;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Config\ConfigException;
use Zef\Framework\Config\Schema\EventSchema;
use Zef\Framework\Config\Schema\SchemaViolation;

/**
 * @internal
 */
#[CoversClass(EventSchema::class)]
final class EventSchemaTest extends TestCase
{
    public function testAcceptsAFullyDeclaredEvent(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => [
                'orderId' => 'int',
                'amount' => 'int',
                'currency' => '?string',
            ],
            'listeners' => ['shop.order.mailer', 'shop.order.audit'],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testAcceptsADefinitionOnlyEvent(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.dispatched',
            'fields' => ['carrier' => 'string'],
        ]);

        self::assertSame([], $this->render($violations));
    }

    public function testReportsAMissingName(): void
    {
        $violations = $this->schema()->validate([
            'fields' => ['carrier' => 'string'],
        ]);

        self::assertSame(['name: expected a non-empty string, got missing'], $this->render($violations));
    }

    public function testReportsAMissingFieldsMap(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
        ]);

        self::assertSame(
            ['fields: expected a non-empty map of non-empty strings, got missing'],
            $this->render($violations),
        );
    }

    public function testReportsEveryRequiredFieldOfAnEmptyDeclaration(): void
    {
        $violations = $this->schema()->validate([]);

        self::assertSame(
            [
                'name: expected a non-empty string, got missing',
                'fields: expected a non-empty map of non-empty strings, got missing',
            ],
            $this->render($violations),
        );
    }

    public function testReportsANameThatIsAnInteger(): void
    {
        $violations = $this->schema()->validate([
            'name' => 404,
            'fields' => ['carrier' => 'string'],
        ]);

        self::assertSame(['name: expected a non-empty string, got int'], $this->render($violations));
    }

    public function testReportsANameThatIsNull(): void
    {
        $violations = $this->schema()->validate([
            'name' => null,
            'fields' => ['carrier' => 'string'],
        ]);

        self::assertSame(['name: expected a non-empty string, got null'], $this->render($violations));
    }

    public function testReportsAnEmptyName(): void
    {
        $violations = $this->schema()->validate([
            'name' => '',
            'fields' => ['carrier' => 'string'],
        ]);

        self::assertSame(['name: expected a non-empty string, got an empty string'], $this->render($violations));
    }

    public function testReportsFieldsThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => 'amount:int',
        ]);

        self::assertSame(
            ['fields: expected a non-empty map of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyFieldsMap(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => [],
        ]);

        self::assertSame(
            ['fields: expected a non-empty map of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testReportsFieldsThatIsAList(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['int', 'string'],
        ]);

        self::assertSame(
            ['fields: expected a non-empty map of non-empty strings, got array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryFieldValue(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int', 'amount' => 2],
        ]);

        self::assertSame(
            ['fields.amount: expected a non-empty string, got int'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyFieldValue(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => ''],
        ]);

        self::assertSame(
            ['fields.orderId: expected a non-empty string, got an empty string'],
            $this->render($violations),
        );
    }

    public function testReportsListenersThatIsAString(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
            'listeners' => 'shop.order.mailer',
        ]);

        self::assertSame(
            ['listeners: expected a non-empty list of non-empty strings, got string'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyListenersList(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
            'listeners' => [],
        ]);

        self::assertSame(
            ['listeners: expected a non-empty list of non-empty strings, got an empty array'],
            $this->render($violations),
        );
    }

    public function testChecksEveryListenerElement(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
            'listeners' => ['shop.order.mailer', 42],
        ]);

        self::assertSame(
            ['listeners.1: expected a non-empty string, got int'],
            $this->render($violations),
        );
    }

    public function testReportsAnEmptyListenerElement(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
            'listeners' => [''],
        ]);

        self::assertSame(
            ['listeners.0: expected a non-empty string, got an empty string'],
            $this->render($violations),
        );
    }

    public function testReportsAnUnexpectedKeySoATypoCannotVanish(): void
    {
        $violations = $this->schema()->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
            'fieldss' => ['amount' => 'int'],
        ]);

        self::assertSame(
            [
                'fieldss: expected one of name, fields, listeners, '
                    . 'got an unexpected key',
            ],
            $this->render($violations),
        );
    }

    public function testAssertThrowsWithTheFullPathContext(): void
    {
        try {
            $this->schema()->assert([
                'name' => 'order.paid',
                'listeners' => ['shop.order.mailer'],
            ]);
            self::fail('An event that violates the schema must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: '
                    . 'fields: expected a non-empty map of non-empty strings, got missing.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListAcceptsAValidEventsList(): void
    {
        $eventSchema = $this->schema();

        $eventSchema->assertList([
            ['name' => 'order.paid', 'fields' => ['orderId' => 'int']],
            [
                'name' => 'order.dispatched',
                'fields' => ['carrier' => 'string'],
                'listeners' => ['shop.order.mailer'],
            ],
        ]);

        self::assertSame([], $eventSchema->validate(['name' => 'order.paid', 'fields' => ['orderId' => 'int']]));
    }

    public function testAssertListAcceptsAMapOfNamedEvents(): void
    {
        $eventSchema = $this->schema();

        $eventSchema->assertList([
            'paid' => ['name' => 'order.paid', 'fields' => ['orderId' => 'int']],
        ]);

        self::assertSame([], $eventSchema->validate([
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int'],
        ]));
    }

    public function testAssertListRejectsAValueThatIsNotAnArray(): void
    {
        try {
            $this->schema()->assertList('order.paid');
            self::fail('A scalar listen value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "events.listen" configuration must be a list of event declarations.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListRejectsAnExplicitlyNullValue(): void
    {
        try {
            $this->schema()->assertList(null);
            self::fail('A null listen value must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'The "events.listen" configuration must be a list of event declarations.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertListHaltsOnTheFirstViolatingEntry(): void
    {
        try {
            $this->schema()->assertList([
                ['name' => 'order.paid', 'fields' => ['orderId' => 'int']],
                ['name' => 'order.dispatched'],
            ]);
            self::fail('A listen list with a violating entry must raise a ConfigException.');
        } catch (ConfigException $configException) {
            self::assertSame(
                'Configuration failed schema validation: fields: expected a non-empty map of non-empty strings, got missing.',
                $configException->getMessage(),
            );
        }
    }

    public function testAssertAcceptsAValidEvent(): void
    {
        $event = [
            'name' => 'order.paid',
            'fields' => ['orderId' => 'int', 'amount' => 'int'],
        ];

        $this->schema()->assert($event);

        self::assertSame([], $this->render($this->schema()->validate($event)));
    }

    private function schema(): EventSchema
    {
        return new EventSchema();
    }

    /**
     * @param list<SchemaViolation> $violations
     *
     * @return list<string>
     */
    private function render(array $violations): array
    {
        return array_map(
            static fn (SchemaViolation $schemaViolation): string => (string) $schemaViolation,
            $violations,
        );
    }
}
