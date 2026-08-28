<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Resolution;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionParameter;
use Temant\Container\Container;
use Temant\Container\Exception\UnresolvableParameterException;
use Temant\Container\Resolution\ParameterResolver;
use Temant\Container\Resolution\ResolvingStack;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\ConstructorWithBuiltInDefault;
use Tests\Temant\Container\Fixtures\ConstructorWithBuiltInTypes;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultObject;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultValues;
use Tests\Temant\Container\Fixtures\ConstructorWithIntersectionType;
use Tests\Temant\Container\Fixtures\ConstructorWithNullableBuiltin;
use Tests\Temant\Container\Fixtures\ConstructorWithNullableObject;
use Tests\Temant\Container\Fixtures\ConstructorWithoutTypeHints;
use Tests\Temant\Container\Fixtures\ConstructorWithVariadic;
use Tests\Temant\Container\Fixtures\DefaultObjectDep;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\UnionWithDefaultService;

final class ParameterResolverTest extends TestCase
{
    private function resolver(bool $autowiring = true): ParameterResolver
    {
        return new ParameterResolver(new Container(autowiringEnabled: $autowiring), new ResolvingStack());
    }

    private static function firstParam(string $class): ReflectionParameter
    {
        $constructor = (new ReflectionClass($class))->getConstructor();
        self::assertNotNull($constructor);

        return $constructor->getParameters()[0];
    }

    /**
     * @return iterable<string, array{class-string, string}>
     */
    public static function unresolvableCases(): iterable
    {
        yield 'no type hint' => [ConstructorWithoutTypeHints::class, '/no type hint/'];
        yield 'intersection type' => [ConstructorWithIntersectionType::class, '/Intersection types/'];
        yield 'built-in without default' => [ConstructorWithBuiltInTypes::class, '/type int/'];
        yield 'variadic' => [ConstructorWithVariadic::class, '/Variadic/'];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('unresolvableCases')]
    public function rejectsUnresolvableParameters(string $class, string $messagePattern): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches($messagePattern);

        $this->resolver()->resolveParameter(self::firstParam($class));
    }

    /**
     * @return iterable<string, array{class-string, mixed}>
     */
    public static function resolvableCases(): iterable
    {
        yield 'built-in default' => [ConstructorWithBuiltInDefault::class, 'hello'];
        yield 'nullable built-in' => [ConstructorWithNullableBuiltin::class, null];
    }

    /**
     * @param class-string $class
     */
    #[Test]
    #[DataProvider('resolvableCases')]
    public function resolvesScalarFallbacks(string $class, mixed $expected): void
    {
        self::assertSame($expected, $this->resolver()->resolveParameter(self::firstParam($class)));
    }

    #[Test]
    public function resolvesNullableObjectToNullWhenAutowiringDisabled(): void
    {
        $value = $this->resolver(autowiring: false)
            ->resolveParameter(self::firstParam(ConstructorWithNullableObject::class));

        self::assertNull($value);
    }

    #[Test]
    public function usesDefaultObjectWhenNotRegistered(): void
    {
        $value = $this->resolver(autowiring: false)
            ->resolveParameter(self::firstParam(ConstructorWithDefaultObject::class));

        self::assertInstanceOf(DefaultObjectDep::class, $value);
    }

    #[Test]
    public function registeredEntryBeatsDefaultValue(): void
    {
        $container = new Container(autowiringEnabled: false);
        $expected = new Foo();
        $container->instance(Foo::class, $expected);

        $resolver = new ParameterResolver($container, new ResolvingStack());

        self::assertSame(
            $expected,
            $resolver->resolveParameter(self::firstParam(ConstructorWithDefaultValues::class)),
        );
    }

    #[Test]
    public function resolvesFromContainerThenAutowiring(): void
    {
        $constructor = (new ReflectionClass(Baz::class))->getConstructor();
        self::assertNotNull($constructor);

        [$fooParam, $barParam] = $constructor->getParameters();

        self::assertInstanceOf(Foo::class, $this->resolver()->resolveParameter($fooParam));
        self::assertInstanceOf(\Tests\Temant\Container\Fixtures\Bar::class, $this->resolver()->resolveParameter($barParam));
    }

    #[Test]
    public function unionParameterFallsBackToItsDefaultWhenNoMemberResolves(): void
    {
        $value = $this->resolver(autowiring: false)
            ->resolveParameter(self::firstParam(UnionWithDefaultService::class));

        self::assertInstanceOf(\Tests\Temant\Container\Fixtures\Bar::class, $value);
    }

    #[Test]
    public function exceptionsSatisfyPsr11(): void
    {
        try {
            $this->resolver(autowiring: false)->resolveParameter(self::firstParam(Baz::class));
            self::fail('Expected UnresolvableParameterException');
        } catch (\Psr\Container\ContainerExceptionInterface $e) {
            self::assertInstanceOf(UnresolvableParameterException::class, $e);
        }
    }
}
