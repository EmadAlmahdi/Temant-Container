<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Tests\Temant\Container\Fixtures\NoConstructorClass;
use Tests\Temant\Container\Fixtures\NonInstantiableClass;
use Tests\Temant\Container\Fixtures\WithConstructorClass;

final class ConstructorResolverTest extends TestCase
{
    private Container $container;
    private ParameterResolver $parameterResolver;
    private ConstructorResolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container(true);

        $this->parameterResolver = new ParameterResolver(
            $this->container,
            $this->container->hasAutowiring()
        );

        // ConstructorResolver expects a resolving stack reference
        $stack = [];
        $this->resolver = new ConstructorResolver($this->parameterResolver, $stack);
    }

    public function testResolveInstantiatesClassWithoutConstructor(): void
    {
        $instance = $this->resolver->resolve(NoConstructorClass::class);

        self::assertInstanceOf(NoConstructorClass::class, $instance);
    }

    public function testResolveInstantiatesClassWithConstructor(): void
    {
        $instance = $this->resolver->resolve(WithConstructorClass::class);

        self::assertInstanceOf(WithConstructorClass::class, $instance);
    }

    public function testResolveThrowsExceptionForNonInstantiableClass(): void
    {
        $this->expectException(ClassResolutionException::class);

        $this->resolver->resolve(NonInstantiableClass::class);
    }

    public function testResolveThrowsExceptionForNonExistsClass(): void
    {
        $this->expectException(ClassResolutionException::class);

        $this->resolver->resolve('NotFoundClass');
    }
}