<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Reflection;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Reflection\ConstructorDescriptor;
use Temant\Container\Reflection\ReflectionCache;
use Tests\Temant\Container\Fixtures\ArrayCache;
use Tests\Temant\Container\Fixtures\ConstructorWithDefaultObject;
use Tests\Temant\Container\Fixtures\NoConstructorClass;
use Tests\Temant\Container\Fixtures\NonInstantiableClass;
use Tests\Temant\Container\Fixtures\ServiceWithNamedParam;

final class ReflectionCacheTest extends TestCase
{
    #[Test]
    public function describesConstructors(): void
    {
        $cache = new ReflectionCache();

        $none = $cache->constructorFor(NoConstructorClass::class);
        self::assertTrue($none->isInstantiable);
        self::assertFalse($none->hasConstructor);
        self::assertSame([], $none->parameters);

        $with = $cache->constructorFor(ServiceWithNamedParam::class);
        self::assertTrue($with->hasConstructor);
        self::assertCount(3, $with->parameters);

        self::assertFalse($cache->constructorFor(NonInstantiableClass::class)->isInstantiable);
    }

    #[Test]
    public function returnsTheSameInstanceFromTheInMemoryCache(): void
    {
        $cache = new ReflectionCache();

        self::assertSame(
            $cache->constructorFor(ServiceWithNamedParam::class),
            $cache->constructorFor(ServiceWithNamedParam::class),
        );
    }

    #[Test]
    public function persistsSerialisableDescriptorsToAPsr16Store(): void
    {
        $store = new ArrayCache();

        $first = new ReflectionCache($store);
        $built = $first->constructorFor(ServiceWithNamedParam::class);
        self::assertSame(1, $store->writes);

        // A fresh cache (empty memory) reads the descriptor back from the store.
        $second = new ReflectionCache($store);
        $loaded = $second->constructorFor(ServiceWithNamedParam::class);

        self::assertInstanceOf(ConstructorDescriptor::class, $loaded);
        self::assertCount(count($built->parameters), $loaded->parameters);
        self::assertSame($store->writes, 1, 'second cache did not rebuild or rewrite');
    }

    #[Test]
    public function doesNotPersistDescriptorsWithObjectDefaults(): void
    {
        $store = new ArrayCache();
        $cache = new ReflectionCache($store);

        $descriptor = $cache->constructorFor(ConstructorWithDefaultObject::class);

        self::assertFalse($descriptor->isPersistable());
        self::assertSame(0, $store->writes);
    }

    #[Test]
    public function warmBuildsWithoutInstantiating(): void
    {
        $store = new ArrayCache();
        $cache = new ReflectionCache($store);

        $cache->warm([ServiceWithNamedParam::class, NoConstructorClass::class, 'Missing\\ClassName']);

        self::assertTrue($store->has('temant.container.ctor.' . ServiceWithNamedParam::class));
    }
}
