<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Resolution;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Reflection\ReflectionCache;
use Temant\Container\Resolution\Resolver;
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
        $this->resolver = new Resolver($this->container, new ReflectionCache());
    }

    #[Test]
    public function resolvesAClass(): void
    {
        self::assertInstanceOf(SomeClass::class, $this->resolver->resolve(SomeClass::class));
    }

    #[Test]
    public function injectsClosureParameters(): void
    {
        $result = $this->resolver->call(fn(SomeClass $obj): string => $obj::class);

        self::assertSame(SomeClass::class, $result);
    }

    #[Test]
    public function supportsArrayCallableWithNamedOverrides(): void
    {
        $result = $this->resolver->call([new CallTarget(), 'method'], ['name' => 'override']);

        self::assertSame(SomeClass::class . ':override', $result);
    }

    #[Test]
    public function reportsCircularDependenciesWithTheFullChain(): void
    {
        try {
            $this->resolver->resolve(CircularA::class);
            self::fail('Expected a circular-dependency exception.');
        } catch (ContainerExceptionInterface $e) {
            self::assertInstanceOf(ClassResolutionException::class, $e);
            self::assertStringContainsString('CircularA', $e->getMessage());
            self::assertStringContainsString('CircularB', $e->getMessage());
        }
    }
}
