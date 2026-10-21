<?php

declare(strict_types=1);

namespace Zef\Test\Container\Autowiring;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Zef\Framework\Container\Autowiring\AutowireBindingTiers;
use Zef\Framework\Container\Autowiring\AutowireBirths;
use Zef\Framework\Container\Autowiring\AutowireParameterSpec;
use Zef\Framework\Container\Autowiring\AutowireReferences;
use Zef\Framework\Container\Autowiring\AutowireValueServices;
use Zef\Framework\Container\Autowiring\AutowiringException;
use Zef\Framework\Container\Autowiring\ReflectionMetadataExtractor;
use Zef\Framework\Container\Container;
use Zef\Framework\Container\InstanceDefinition;
use Zef\Framework\Container\ParameterValues;
use Zef\Framework\Container\ServiceDefinition;
use Zef\Test\Support\Container\Aot\Window;
use Zef\Test\Support\Container\Autowiring\LoggerInterface;
use Zef\Test\Support\Container\Autowiring\ParameterlessHouse;

/**
 * @internal
 */
#[CoversClass(AutowireBindingTiers::class)]
final class AutowireBindingTiersTest extends TestCase
{
    /**
     * The value tier: the `#[Value]` address materialises as the
     * synthetic `@value:` service the plan references.
     */
    public function testTheValueAddressAnswersItsSyntheticServiceReference(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'tone',
            isVariadic: false,
            isOptional: true,
            hasDefault: false,
            valueKey: 'app.tone',
        );
        $references = $this->references([], new ParameterValues(['app.tone' => 'calm']));

        $stretch = AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);

        self::assertSame([['service' => '@value:app.tone']], $stretch);
    }

    /**
     * The binding tier: a declared definition answers its identifier.
     */
    public function testADeclaredTypeAnswersItsServiceReference(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'window',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            className: Window::class,
        );
        $references = $this->references([new InstanceDefinition(Window::class, Window::class, [])]);

        $stretch = AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);

        self::assertSame([['service' => Window::class]], $stretch);
    }

    /**
     * A class this run can birth answers its own reference.
     */
    public function testABirthableClassAnswersItsOwnReference(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'wing',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            className: ParameterlessHouse::class,
        );
        $references = $this->references();

        $stretch = AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);

        self::assertSame([['service' => ParameterlessHouse::class]], $stretch);
    }

    /**
     * The defaulted nullable's own yield rule: a class-typed parameter
     * that declared a default, with no attribute spelling an intent,
     * whose type names no binding -- the binding tier falls through to
     * the constructor's own default instead of the refusal.
     */
    public function testAnUnboundDefaultedNullableReachesItsOwnFallback(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: true,
            hasDefault: true,
            className: LoggerInterface::class,
        );
        $references = $this->references();

        $stretch = AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);

        self::assertSame([null], $stretch);
    }

    /**
     * The fallback tier: the constructor's own default, baked verbatim.
     */
    public function testADefaultedParameterWithoutABindingAnswersItsOwnDefault(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'count',
            isVariadic: false,
            isOptional: true,
            hasDefault: true,
            defaultValue: 4,
        );
        $references = $this->references();

        $stretch = AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);

        self::assertSame([4], $stretch);
    }

    /**
     * The attribute arms keep the refusal: an explicit binding that
     * names nothing is a declaration defect, not a defaulted
     * parameter's fallback.
     */
    public function testAnUnboundInjectArmKeepsTheRefusal(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: true,
            hasDefault: true,
            injectId: 'app.absent',
        );
        $references = $this->references();

        try {
            AutowireBindingTiers::stretch('app.house', $autowireParameterSpec, $references);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.house' parameter '\$logger'",
                $autowiringException->getMessage(),
            );
        }
    }

    /**
     * The refusal tail: an unbound interface with no default is the
     * honest refusal, with the owning class, parameter and way out in
     * the message.
     */
    public function testAnUnboundInterfaceRefuses(): void
    {
        $autowireParameterSpec = new AutowireParameterSpec(
            name: 'logger',
            isVariadic: false,
            isOptional: false,
            hasDefault: false,
            className: LoggerInterface::class,
        );
        $references = $this->references();

        try {
            AutowireBindingTiers::stretch('app.billing', $autowireParameterSpec, $references);
            self::fail('Expected an AutowiringException to be thrown.');
        } catch (AutowiringException $autowiringException) {
            self::assertStringContainsString(
                "Cannot autowire 'app.billing' parameter '\$logger'",
                $autowiringException->getMessage(),
            );
        }
    }

    /**
     * @param list<ServiceDefinition> $definitions the declared set
     */
    private function references(array $definitions = [], ?ParameterValues $parameterValues = null): AutowireReferences
    {
        $autowireValueServices = new AutowireValueServices();
        $autowireValueServices->open($parameterValues ?? ParameterValues::empty(), [], new Container());

        $autowireReferences = new AutowireReferences(
            new ReflectionMetadataExtractor(),
            new AutowireBirths(),
            $autowireValueServices,
        );
        $autowireReferences->open($definitions, new Container());

        return $autowireReferences;
    }
}
