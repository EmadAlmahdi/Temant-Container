<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Resolution\ConstructorResolver;
use Temant\Container\Resolution\ParameterResolver;
use Temant\Container\Resolution\ResolvingStack;
use Tests\Temant\Container\Fixtures\NoConstructorClass;
use Tests\Temant\Container\Fixtures\NonInstantiableClass;
use Tests\Temant\Container\Fixtures\ServiceWithNamedParam;
use Tests\Temant\Container\Fixtures\WithConstructorClass;

final class ConstructorResolverTest extends TestCase
{
    private ConstructorResolver $resolver;

    protected function setUp(): void
    {
        $stack = new ResolvingStack();
        $parameters = new ParameterResolver(new Container(), $stack);

        $this->resolver = new ConstructorResolver($parameters, $stack);
    }

    #[Test]
    public function instantiatesClassWithoutConstructor(): void
    {
        self::assertInstanceOf(NoConstructorClass::class, $this->resolver->resolve(NoConstructorClass::class));
    }

    #[Test]
    public function autowiresConstructorDependencies(): void
    {
        self::assertInstanceOf(WithConstructorClass::class, $this->resolver->resolve(WithConstructorClass::class));
    }

    #[Test]
    public function appliesNamedOverrides(): void
    {
        /** @var ServiceWithNamedParam $service */
        $service = $this->resolver->resolve(ServiceWithNamedParam::class, ['name' => 'given', 'value' => 7]);

        self::assertSame('given', $service->name);
        self::assertSame(7, $service->value);
    }

    #[Test]
    public function rejectsNonInstantiableClass(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessageMatches('/not instantiable/');

        $this->resolver->resolve(NonInstantiableClass::class);
    }

    #[Test]
    public function rejectsUnknownClass(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessageMatches('/not a valid resolvable class/');

        $this->resolver->resolve('No\\Such\\ClassName');
    }
}
