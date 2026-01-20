<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Temant\Container\Container;
use Temant\Container\Exception\UnresolvableParameterException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultValues;
use Tests\Temant\Container\Fixtures\ConstructorWithoutTypeHints;
use Tests\Temant\Container\Fixtures\ConstructorWithUnionTypes;
use Tests\Temant\Container\Fixtures\ConstructorWithBuiltInTypes;
use Tests\Temant\Container\Fixtures\Foo;

class ParameterResolverTest extends TestCase
{
    private Container $container;
    private ParameterResolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container();

        $this->container->set(Foo::class, fn(Container $container): Foo => new Foo);
        $this->resolver = new ParameterResolver($this->container, true);
    }

    public function testResolveParameterThrowsExceptionForNotTypeHinted(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithoutTypeHints::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $this->resolver->resolveParameter($param);
    }

    public function testResolveParameterThrowsExceptionForUnionType(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithUnionTypes::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $this->resolver->resolveParameter($param);
    }

    public function testResolveParameterThrowsExceptionForBuiltInTypes(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $reflection = new ReflectionClass(ConstructorWithBuiltInTypes::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $this->resolver->resolveParameter($param);
    }

    public function testResolveParameterWithDefaultValue(): void
    {
        $reflection = new ReflectionClass(ConstructorWithDefaultValues::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $resolvedValue = $this->resolver->resolveParameter($param);
        $this->assertInstanceOf(Foo::class, $resolvedValue);
    }

    public function testResolveFromContainerDef(): void
    {
        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $this->assertInstanceOf(Foo::class, $this->resolver->resolveParameter($param));
    }

    public function testResolveWithAutWiring(): void
    {
        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[1];

        $this->assertInstanceOf(Bar::class, $this->resolver->resolveParameter($param));
    }

    public function testUnresolvableClassThrowsException(): void
    {
        $this->expectException(UnresolvableParameterException::class);

        $container = new Container(false);
        $resolver = new ParameterResolver($container, false);
        $reflection = new ReflectionClass(Baz::class);
        $constructor = $reflection->getConstructor();
        $param = $constructor->getParameters()[0];

        $this->assertInstanceOf(Foo::class, $resolver->resolveParameter($param));
    }
}