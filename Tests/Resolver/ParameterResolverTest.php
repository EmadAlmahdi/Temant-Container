<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Temant\Container\Container;
use Temant\Container\Exception\UnresolvableParameterException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\ConstructorWithBuiltInDefault;
use Tests\Temant\Container\Fixtures\ConstructorWithBuiltInTypes;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultObject;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultValues;
use Tests\Temant\Container\Fixtures\ConstructorWithIntersectionType;
use Tests\Temant\Container\Fixtures\ConstructorWithNullableBuiltin;
use Tests\Temant\Container\Fixtures\ConstructorWithNullableObject;
use Tests\Temant\Container\Fixtures\ConstructorWithoutTypeHints;
use Tests\Temant\Container\Fixtures\ConstructorWithUnionTypes;
use Tests\Temant\Container\Fixtures\ConstructorWithVariadic;
use Tests\Temant\Container\Fixtures\DefaultObjectDep;
use Tests\Temant\Container\Fixtures\Foo;

final class ParameterResolverTest extends TestCase
{
    private Container $container;
    private ParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container(true);

        // Shared-by-default: this will be returned for Foo::class
        $this->container->set(Foo::class, fn(): Foo => new Foo());

        // ParameterResolver expects callable():bool now
        $this->resolver = new ParameterResolver(
            $this->container,
            $this->container->hasAutowiring()
        );
    }

    public function testResolveParameterThrowsExceptionForNotTypeHinted(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithoutTypeHints::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $param = $constructor->getParameters()[0];
        $this->resolver->resolveParameter($param);
    }

    public function testResolveParameterThrowsExceptionForUnionType(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithUnionTypes::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $param = $constructor->getParameters()[0];
        $this->resolver->resolveParameter($param);
    }

    public function testResolveParameterThrowsExceptionForBuiltInTypesWithoutDefault(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithBuiltInTypes::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $param = $constructor->getParameters()[0];
        $this->resolver->resolveParameter($param);
    }

    public function testResolveBuiltinDefaultValue(): void
    {
        $reflection = new ReflectionClass(ConstructorWithBuiltInDefault::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $param = $constructor->getParameters()[0];

        $value = $this->resolver->resolveParameter($param);

        self::assertSame('hello', $value);
    }

    public function testResolveParameterThrowsForVariadicParameter(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithVariadic::class);
        $param = $reflection->getConstructor()->getParameters()[0];

        $this->resolver->resolveParameter($param);
    }

    public function testNullableObjectReturnsNullWhenUnresolvable(): void
    {
        $container = new Container(false); // autowiring OFF
        $resolver = new ParameterResolver($container, false);

        $reflection = new ReflectionClass(ConstructorWithNullableObject::class);
        $param = $reflection->getConstructor()->getParameters()[0];

        $value = $resolver->resolveParameter($param);

        self::assertNull($value);
    }

    public function testNullableBuiltinReturnsNull(): void
    {
        $reflection = new ReflectionClass(ConstructorWithNullableBuiltin::class);
        $param = $reflection->getConstructor()->getParameters()[0];

        $value = $this->resolver->resolveParameter($param);

        self::assertNull($value);
    }

    public function testUnsupportedNonNamedTypeThrows(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithIntersectionType::class);
        $param = $reflection->getConstructor()->getParameters()[0];

        $this->resolver->resolveParameter($param);
    }

    public function testResolveReturnsDefaultValueForObjectWhenNotResolvable(): void
    {
        $container = new Container(false); // autowiring OFF
        $resolver = new ParameterResolver(
            $container,
            false
        );

        $reflection = new ReflectionClass(ConstructorWithDefaultObject::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        $param = $constructor->getParameters()[0];

        $value = $resolver->resolveParameter($param);

        self::assertInstanceOf(DefaultObjectDep::class, $value);
    }


    public function testResolveParameterDefaultObjectDoesNotOverrideContainerBinding(): void
    {
        $reflection = new ReflectionClass(ConstructorWithDefaultValues::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        // First param is Foo $foo = new Foo()
        $param = $constructor->getParameters()[0];

        // Register a specific instance in the container and ensure we get THIS object back.
        $expected = new Foo();

        // If you already registered Foo in setUp(), replace it:
        if ($this->container->has(Foo::class)) {
            $this->container->remove(Foo::class);
        }

        $this->container->instance(Foo::class, $expected);

        $resolved = $this->resolver->resolveParameter($param);

        self::assertInstanceOf(Foo::class, $resolved);
        self::assertSame($expected, $resolved, 'Container instance must win over default new Foo().');
    }

    public function testResolveFromContainerDefinitionWins(): void
    {
        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        // Baz::__construct(Foo $foo, Bar $bar)
        $fooParam = $constructor->getParameters()[0];

        $resolved = $this->resolver->resolveParameter($fooParam);
        self::assertInstanceOf(Foo::class, $resolved);
    }

    public function testResolveWithAutowiringForConcreteClass(): void
    {
        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        // Baz second param is Bar (not registered, should autowire)
        $barParam = $constructor->getParameters()[1];

        $resolved = $this->resolver->resolveParameter($barParam);
        self::assertInstanceOf(Bar::class, $resolved);
    }

    public function testUnresolvableClassThrowsWhenAutowiringDisabledAndNotRegistered(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $container = new Container(false); // autowiring disabled
        $resolver = new ParameterResolver($container, $container->hasAutowiring());

        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        self::assertNotNull($constructor);

        // Baz first param is Foo (not registered in this container)
        $fooParam = $constructor->getParameters()[0];

        $resolver->resolveParameter($fooParam);
    }
}
