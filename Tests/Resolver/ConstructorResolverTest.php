<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Resolver\ConstructorResolver;
use Temant\Container\Resolver\ParameterResolver;
use Tests\Temant\Container\Fixtures\NoConstructorClass;
use Tests\Temant\Container\Fixtures\NonInstantiableClass;
use Tests\Temant\Container\Fixtures\WithConstructorClass;

final class ConstructorResolverTest extends TestCase
{
    private ConstructorResolver $resolver;

    protected function setUp(): void
    {
        $container = new Container();

        $stack = [];
        $parameterResolver = new ParameterResolver(
            $container,
            $stack,
        );

        $this->resolver = new ConstructorResolver($parameterResolver, $stack);
    }

    #[Test]
    public function resolveInstantiatesClassWithoutConstructor(): void
    {
        $instance = $this->resolver->resolve(NoConstructorClass::class);

        self::assertInstanceOf(NoConstructorClass::class, $instance);
    }

    #[Test]
    public function resolveInstantiatesClassWithConstructor(): void
    {
        $instance = $this->resolver->resolve(WithConstructorClass::class);

        self::assertInstanceOf(WithConstructorClass::class, $instance);
    }

    #[Test]
    public function resolveThrowsForNonInstantiableClass(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        $this->resolver->resolve(NonInstantiableClass::class);
    }

    #[Test]
    public function resolveThrowsForNonExistentClass(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessageMatches('/not a valid resolvable class/');

        $this->resolver->resolve('Nonexistent\\ClassName');
    }
}
