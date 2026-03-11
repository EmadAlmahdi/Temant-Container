<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Resolver;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Resolver\Resolver;
use Tests\Temant\Container\Fixtures\CallTarget;
use Tests\Temant\Container\Fixtures\CircularA;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ResolverTest extends TestCase
{
    private Container $container;
    private Resolver $resolver;

    protected function setUp(): void
    {
        $this->container = new Container();
        $this->resolver = new Resolver($this->container);
    }

    #[Test]
    public function resolveCreatesInstance(): void
    {
        $instance = $this->resolver->resolve(SomeClass::class);

        self::assertInstanceOf(SomeClass::class, $instance);
    }

    #[Test]
    public function callInjectsParametersViaClosureReflection(): void
    {
        $result = $this->resolver->call(
            function (SomeClass $obj): string {
                return $obj::class;
            },
        );

        self::assertSame(SomeClass::class, $result);
    }

    #[Test]
    public function callSupportsArrayCallableWithNamedOverrides(): void
    {
        $target = new CallTarget();

        $result = $this->resolver->call(
            [$target, 'method'],
            ['name' => 'override'],
        );

        self::assertSame(SomeClass::class . ':override', $result);
    }

    #[Test]
    public function circularDependencyThrowsClassResolutionException(): void
    {
        $this->expectException(ClassResolutionException::class);
        $this->expectExceptionMessageMatches('/Circular dependency/');

        $this->container->get(CircularA::class);
    }

    #[Test]
    public function circularDependencyExceptionIsPsr11Compliant(): void
    {
        try {
            $this->container->get(CircularA::class);
            self::fail('Expected an exception for circular dependency.');
        } catch (\Psr\Container\ContainerExceptionInterface $e) {
            self::assertInstanceOf(ClassResolutionException::class, $e);
            self::assertStringContainsString('CircularA', $e->getMessage());
            self::assertStringContainsString('CircularB', $e->getMessage());
        }
    }
}
