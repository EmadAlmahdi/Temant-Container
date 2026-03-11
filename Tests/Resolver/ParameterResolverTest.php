<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Temant\Container\Container;
use Temant\Container\Exception\UnresolvableParameterException;
use Temant\Container\Resolver\ParameterResolver;
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
        $this->container = new Container();

        $this->container->set(Foo::class, fn(): Foo => new Foo());

        $this->resolver = new ParameterResolver(
            $this->container,
            $this->container->hasAutowiring(...),
        );
    }

    // -------------------------------------------------------------------------
    // Error Cases
    // -------------------------------------------------------------------------

    #[Test]
    public function throwsForUntypedParameter(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/no type hint/');

        $param = $this->getFirstConstructorParam(ConstructorWithoutTypeHints::class);
        $this->resolver->resolveParameter($param);
    }

    #[Test]
    public function throwsForUnionType(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/Union types/');

        $param = $this->getFirstConstructorParam(ConstructorWithUnionTypes::class);
        $this->resolver->resolveParameter($param);
    }

    #[Test]
    public function throwsForIntersectionType(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/Intersection types/');

        $param = $this->getFirstConstructorParam(ConstructorWithIntersectionType::class);
        $this->resolver->resolveParameter($param);
    }

    #[Test]
    public function throwsForBuiltInTypeWithoutDefault(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $param = $this->getFirstConstructorParam(ConstructorWithBuiltInTypes::class);
        $this->resolver->resolveParameter($param);
    }

    #[Test]
    public function throwsForVariadicParameter(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/Variadic/');

        $param = $this->getFirstConstructorParam(ConstructorWithVariadic::class);
        $this->resolver->resolveParameter($param);
    }

    #[Test]
    public function throwsForUnresolvableClassWhenAutowiringDisabled(): void
    {
        $this->expectException(UnresolvableParameterException::class);
        $this->expectExceptionMessageMatches('/not registered/');

        $container = new Container(autowiringEnabled: false);
        $resolver = new ParameterResolver($container, $container->hasAutowiring(...));

        $param = $this->getFirstConstructorParam(Baz::class);
        $resolver->resolveParameter($param);
    }

    // -------------------------------------------------------------------------
    // Successful Resolution
    // -------------------------------------------------------------------------

    #[Test]
    public function resolvesBuiltInDefaultValue(): void
    {
        $param = $this->getFirstConstructorParam(ConstructorWithBuiltInDefault::class);
        $value = $this->resolver->resolveParameter($param);

        self::assertSame('hello', $value);
    }

    #[Test]
    public function resolvesNullableObjectAsNullWhenNotResolvable(): void
    {
        $container = new Container(autowiringEnabled: false);
        $resolver = new ParameterResolver($container, $container->hasAutowiring(...));

        $param = $this->getFirstConstructorParam(ConstructorWithNullableObject::class);
        $value = $resolver->resolveParameter($param);

        self::assertNull($value);
    }

    #[Test]
    public function resolvesNullableBuiltinAsNull(): void
    {
        $param = $this->getFirstConstructorParam(ConstructorWithNullableBuiltin::class);
        $value = $this->resolver->resolveParameter($param);

        self::assertNull($value);
    }

    #[Test]
    public function resolvesDefaultObjectWhenNotRegistered(): void
    {
        $container = new Container(autowiringEnabled: false);
        $resolver = new ParameterResolver($container, fn(): bool => false);

        $param = $this->getFirstConstructorParam(ConstructorWithDefaultObject::class);
        $value = $resolver->resolveParameter($param);

        self::assertInstanceOf(DefaultObjectDep::class, $value);
    }

    #[Test]
    public function containerBindingWinsOverDefaultValue(): void
    {
        // Remove the Foo from setUp and register a specific instance
        $this->container->remove(Foo::class);

        $expected = new Foo();
        $this->container->instance(Foo::class, $expected);

        $param = $this->getFirstConstructorParam(ConstructorWithDefaultValues::class);
        $resolved = $this->resolver->resolveParameter($param);

        self::assertSame($expected, $resolved, 'Container instance must win over default value.');
    }

    #[Test]
    public function resolvesFromContainerDefinition(): void
    {
        $param = $this->getFirstConstructorParam(Baz::class);
        $resolved = $this->resolver->resolveParameter($param);

        self::assertInstanceOf(Foo::class, $resolved);
    }

    #[Test]
    public function resolvesViaAutowiringForConcreteClass(): void
    {
        $ref = new ReflectionClass(Baz::class);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor);

        // Baz second param is Bar (not registered, should autowire)
        $barParam = $constructor->getParameters()[1];
        $resolved = $this->resolver->resolveParameter($barParam);

        self::assertInstanceOf(Bar::class, $resolved);
    }

    #[Test]
    public function exceptionsArePsr11Compliant(): void
    {
        $container = new Container(autowiringEnabled: false);
        $resolver = new ParameterResolver($container, $container->hasAutowiring(...));

        try {
            $param = $this->getFirstConstructorParam(Baz::class);
            $resolver->resolveParameter($param);
            self::fail('Expected UnresolvableParameterException');
        } catch (\Psr\Container\ContainerExceptionInterface $e) {
            self::assertInstanceOf(UnresolvableParameterException::class, $e);
        }
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function getFirstConstructorParam(string $className): \ReflectionParameter
    {
        $ref = new ReflectionClass($className);
        $constructor = $ref->getConstructor();
        self::assertNotNull($constructor, "Class {$className} must have a constructor.");

        return $constructor->getParameters()[0];
    }
}
