<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\SomeClass;

class ResolverTest extends TestCase
{
    private $container;
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container;
        $this->resolver = new Resolver($this->container, true);
    }

    public function testResolveDelegatesToConstructorResolver(): void
    {
        $className = SomeClass::class;

        $constructorResolver = new ConstructorResolver(new ParameterResolver($this->container, true));
        $constructorResolver->resolve($className); 

        $instance = $this->resolver->resolve($className);
        $this->assertInstanceOf($className, $instance);
    }
}
