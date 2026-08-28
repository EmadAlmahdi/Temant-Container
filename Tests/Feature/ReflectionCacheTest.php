<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\ArrayCache;
use Tests\Temant\Container\Fixtures\Baz;
use Tests\Temant\Container\Fixtures\ServiceWithNamedParam;
use Tests\Temant\Container\Fixtures\WithConstructorClass;

final class ReflectionCacheTest extends TestCase
{
    #[Test]
    public function autowiringPopulatesThePersistentStore(): void
    {
        $store = new ArrayCache();
        $container = new Container(reflectionCache: $store);

        $container->get(Baz::class); // autowires Baz -> Foo, Bar

        self::assertTrue($store->has('temant.container.ctor.' . Baz::class));
        self::assertTrue($store->has('temant.container.ctor.' . \Tests\Temant\Container\Fixtures\Bar::class));
    }

    #[Test]
    public function aSecondContainerReusesTheWarmedStoreWithoutRebuilding(): void
    {
        $store = new ArrayCache();

        (new Container(reflectionCache: $store))->get(WithConstructorClass::class);
        $writesAfterFirst = $store->writes;

        $second = new Container(reflectionCache: $store);
        $obj = $second->get(WithConstructorClass::class);

        self::assertInstanceOf(WithConstructorClass::class, $obj);
        self::assertSame($writesAfterFirst, $store->writes, 'nothing rebuilt on the second container');
    }

    #[Test]
    public function prewarmReflectionCachesWithoutInstantiating(): void
    {
        $store = new ArrayCache();
        $container = new Container(reflectionCache: $store);

        $container->prewarmReflection([ServiceWithNamedParam::class]);

        self::assertTrue($store->has('temant.container.ctor.' . ServiceWithNamedParam::class));
        self::assertSame([], $container->allInstances());
    }

    #[Test]
    public function clearKeepsTheReflectionCache(): void
    {
        $store = new ArrayCache();
        $container = new Container(reflectionCache: $store);

        $container->get(WithConstructorClass::class);
        $writes = $store->writes;

        $container->clear();
        $container->get(WithConstructorClass::class);

        self::assertSame($writes, $store->writes, 'clear() must not drop cached reflection');
    }

    #[Test]
    public function aChildSharesTheParentReflectionCache(): void
    {
        $store = new ArrayCache();
        $parent = new Container(reflectionCache: $store);
        $parent->get(WithConstructorClass::class);
        $writes = $store->writes;

        $child = $parent->createChild();
        $child->get(WithConstructorClass::class);

        self::assertSame($writes, $store->writes, 'child reused the parent-warmed reflection');
    }

    #[Test]
    public function worksWithoutAnyCacheConfigured(): void
    {
        $container = new Container();

        self::assertInstanceOf(WithConstructorClass::class, $container->get(WithConstructorClass::class));
    }

    #[Test]
    public function aBrokenCacheBackendNeverBreaksResolution(): void
    {
        $container = new Container(reflectionCache: new \Tests\Temant\Container\Fixtures\ThrowingCache());

        self::assertInstanceOf(Baz::class, $container->get(Baz::class));
        self::assertInstanceOf(Baz::class, $container->make(Baz::class));
    }
}
