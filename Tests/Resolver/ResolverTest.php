<?php

declare(strict_types=1);

namespace Temant\Container\Resolver;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\ContainerException;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\CircularA;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ResolverTest extends TestCase
{
    private Container $container;
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container(true);
        $this->resolver = new Resolver($this->container);
    }

    public function testResolveCreatesInstance(): void
    {
        $instance = $this->resolver->resolve(SomeClass::class);

        self::assertInstanceOf(SomeClass::class, $instance);
    }

    public function testCallInjectsParametersWithClosureReflectionFunction(): void
    {
        // Covers ReflectionFunction branch
        $result = $this->resolver->call(
            function (SomeClass $obj): string {
                return $obj::class;
            }
        );

        self::assertSame(SomeClass::class, $result);
    }

    public function testCallUsesReflectionMethodForArrayCallableAndNamedOverrideBranch(): void
    {
        // Covers ReflectionMethod branch + namedOverrides branch + continue
        $target = new CallTarget();

        $result = $this->resolver->call(
            [$target, 'method'],
            ['name' => 'override']
        );

        self::assertSame(SomeClass::class . ':override', $result);
    }

    public function testCircularDependencyIsDetectedAndWrappedByContainer(): void
    {
        try {
            $this->container->get(CircularA::class);
            self::fail('Expected an exception for circular dependency.');
        } catch (ContainerException $e) {
            self::assertInstanceOf(
                ClassResolutionException::class,
                $e->getPrevious(),
                'ContainerException should wrap ClassResolutionException as previous.'
            );

            // Optional: check the chain is in the previous message
            self::assertStringContainsString('CircularA', $e->getPrevious()->getMessage());
            self::assertStringContainsString('CircularB', $e->getPrevious()->getMessage());
        }
    }
}