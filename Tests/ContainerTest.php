<?php declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Exception;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\Bar;
use Tests\Temant\Container\Fixtures\SomeClass;
use Throwable;

class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testGetReturnsResolvedInstance(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $instance = $this->container->get(SomeClass::class);
        $this->assertInstanceOf(SomeClass::class, $instance);
    }

    public function testautoResolve(): void
    {
        $instance = $this->container->get(Baz::class);
        $this->assertInstanceOf(Baz::class, $instance);
    }

    public function testSetAddsEntryToContainer(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->assertTrue($this->container->has(SomeClass::class));
    }

    public function testSetThrowsExceptionWhenEntryExists(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());

        $this->expectException(Exception::class);

        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
    }

    public function testGetAndSetAutowiring(): void
    {
        $this->container->setAutowiring(false);
        $firstGetter = $this->container->hasAutowiring();
        $this->assertFalse($firstGetter);

        $this->container->setAutowiring(true);
        $secondGetter = $this->container->hasAutowiring();
        $this->assertTrue($secondGetter);

        $this->assertNotEquals($firstGetter, $secondGetter);
    }

    public function testRemoveRegisteredEntry(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->assertTrue($this->container->has(SomeClass::class));

        $this->container->remove(SomeClass::class);
        $this->assertFalse($this->container->has(SomeClass::class));

        $this->expectException(Throwable::class);
        $this->container->remove(SomeClass::class);
    }

    public function testClearRegisteredEntries(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->container->set(Baz::class, fn(): Baz => new Baz(new Foo, new Bar));

        $this->assertTrue($this->container->has(SomeClass::class));
        $this->assertTrue($this->container->has(Baz::class));

        $this->container->clear();

        $this->assertFalse($this->container->has(SomeClass::class));
        $this->assertFalse($this->container->has(Baz::class));
    }

    public function testGetAllRegisteredEntries(): void
    {
        $this->container->set(SomeClass::class, fn(): SomeClass => new SomeClass());
        $this->container->set(Baz::class, fn(): Baz => new Baz(new Foo, new Bar));

        $entries = $this->container->all();
        $this->assertArrayHasKey(SomeClass::class, $entries);
        $this->assertArrayHasKey(Baz::class, $entries);
    }
}