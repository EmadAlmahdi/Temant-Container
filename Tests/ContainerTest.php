<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ClassResolutionException;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\CircularA;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\SomeClass;

final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container(true);
    }

    public function testFactoryReturnsNewInstanceEachTime(): void
    {
        $this->container->factory(Foo::class, fn(): Foo => new Foo());

        $a = $this->container->get(Foo::class);
        $b = $this->container->get(Foo::class);

        self::assertInstanceOf(Foo::class, $a);
        self::assertInstanceOf(Foo::class, $b);
        self::assertNotSame($a, $b);
    }

    public function testSetReturnsSharedInstanceEachTime(): void
    {
        $this->container->set(Foo::class, fn(): Foo => new Foo());

        $a = $this->container->get(Foo::class);
        $b = $this->container->get(Foo::class);

        self::assertSame($a, $b);
    }

    public function testGetReturnsResolvedInstance(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());

        $instance = $this->container->get(SomeClass::class);

        self::assertInstanceOf(SomeClass::class, $instance);
    }

    public function testGetUnregisteredExistingClassWithoutAutowiringThrowsNotFound(): void
    {
        $this->container->setAutowiring(false);

        $this->expectException(NotFoundException::class);
        $this->container->get(SomeClass::class);
    }

    public function testGetNonExistentClassThrowsNotFound(): void
    {
        $this->expectException(NotFoundException::class);
        $this->container->get('NonExistentClass');
    }

    public function testAutoResolve(): void
    {
        $instance = $this->container->get(Baz::class);
        self::assertInstanceOf(Baz::class, $instance);
    }

    public function testSetAddsEntryToContainer(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        self::assertTrue($this->container->has(SomeClass::class));
    }

    public function testSetThrowsExceptionWhenEntryExists(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());

        $this->expectException(ContainerException::class);
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
    }

    public function testGetAndSetAutowiring(): void
    {
        $this->container->setAutowiring(false);
        self::assertFalse($this->container->hasAutowiring());

        $this->container->setAutowiring(true);
        self::assertTrue($this->container->hasAutowiring());
    }

    public function testRemoveRegisteredEntry(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        self::assertTrue($this->container->has(SomeClass::class));

        $this->container->remove(SomeClass::class);
        self::assertFalse($this->container->has(SomeClass::class));

        $this->expectException(ContainerException::class);
        $this->container->remove(SomeClass::class);
    }

    public function testClearRegisteredEntries(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->container->set(Baz::class, fn(): Baz => new Baz(new Foo(), new Bar()));

        self::assertTrue($this->container->has(SomeClass::class));
        self::assertTrue($this->container->has(Baz::class));

        $this->container->clear();

        self::assertFalse($this->container->has(SomeClass::class));
        self::assertFalse($this->container->has(Baz::class));
    }

    public function testGetAllRegisteredEntries(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->container->set(Baz::class, fn(): Baz => new Baz(new Foo(), new Bar()));

        $entries = $this->container->all();

        self::assertArrayHasKey(SomeClass::class, $entries);
        self::assertArrayHasKey(Baz::class, $entries);
    }

    public function testCircularDependencyIsWrappedInContainerException(): void
    {
        $this->expectException(ContainerException::class);

        try {
            $this->container->get(CircularA::class);
        } catch (ContainerException $e) {
            self::assertInstanceOf(
                ClassResolutionException::class,
                $e->getPrevious()
            );
            throw $e;
        }
    }
}