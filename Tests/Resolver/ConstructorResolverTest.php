<?php declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use Temant\Container\Exception\ClassResolutionException;
use Tests\Temant\Container\Fixtures\NoConstructorClass;
use Tests\Temant\Container\Fixtures\NonInstantiableClass;
use Tests\Temant\Container\Fixtures\WithConstructorClass;

class ConstructorResolverTest extends TestCase
{
    private $parameterResolver;
    private ConstructorResolver $resolver;

    protected function setUp(): void
    {
        $this->parameterResolver = $this->createMock(ParameterResolver::class);
        $this->resolver = new ConstructorResolver($this->parameterResolver);
    }

    public function testResolveInstantiatesClassWithoutConstructor(): void
    {
        $className = NoConstructorClass::class;
        $this->parameterResolver->method('resolveParameter')->willReturn(null);

        $instance = $this->resolver->resolve($className);
        $this->assertInstanceOf($className, $instance);
    }

    public function testResolveInstantiatesClassWithConstructor(): void
    {
        $className = WithConstructorClass::class;
        $this->parameterResolver->method('resolveParameter')->willReturn(['value']);

        $instance = $this->resolver->resolve($className);
        $this->assertInstanceOf($className, $instance);
    }

    public function testResolveThrowsExceptionForNonInstantiableClass(): void
    {
        $this->expectException(ClassResolutionException::class);

        $className = NonInstantiableClass::class;
        $this->resolver->resolve($className);
    }

    public function testResolveThrowsExceptionForNonExistsClass(): void
    {
        $this->expectException(ClassResolutionException::class); 
        $this->resolver->resolve("NotFoundClass");
    }
}