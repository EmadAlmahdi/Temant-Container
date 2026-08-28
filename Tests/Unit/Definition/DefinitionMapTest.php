<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Definition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use stdClass;
use Temant\Container\Definition\DefinitionMap;

final class DefinitionMapTest extends TestCase
{
    private DefinitionMap $map;

    protected function setUp(): void
    {
        $this->map = new DefinitionMap();
    }

    #[Test]
    public function tracksEachRegistrationKind(): void
    {
        $this->map->share('a', fn(): object => new stdClass());
        $this->map->defineFactory('b', fn(): object => new stdClass());
        $this->map->instance('c', new stdClass());
        $this->map->lazy('d', fn(): object => new stdClass());

        self::assertTrue($this->map->hasShared('a'));
        self::assertTrue($this->map->hasFactory('b'));
        self::assertTrue($this->map->hasInstance('c'));
        self::assertTrue($this->map->hasLazy('d'));

        foreach (['a', 'b', 'c', 'd'] as $id) {
            self::assertTrue($this->map->isRegistered($id));
        }

        self::assertFalse($this->map->isRegistered('missing'));
    }

    #[Test]
    public function typeOfChecksLazyFirst(): void
    {
        $this->map->share('x', fn(): object => new stdClass());
        $this->map->lazy('x', fn(): object => new stdClass());

        self::assertSame('lazy', $this->map->typeOf('x'));
        self::assertSame('shared', $this->map->typeOf('y') ?? 'shared');
        self::assertNull($this->map->typeOf('unknown'));
    }

    #[Test]
    public function forgetRemovesEveryTraceAndReportsResult(): void
    {
        $this->map->share('a', fn(): object => new stdClass());
        $this->map->defineFactory('b', fn(): object => new stdClass());
        $this->map->instance('c', new stdClass());
        $this->map->lazy('d', fn(): object => new stdClass());
        $this->map->cacheInstance('a', new stdClass());

        foreach (['a', 'b', 'c', 'd'] as $id) {
            self::assertTrue($this->map->forget($id));
            self::assertFalse($this->map->isRegistered($id));
        }

        self::assertFalse($this->map->forget('a'));
    }

    #[Test]
    public function accessorsReturnNullForUnknownIds(): void
    {
        self::assertNull($this->map->sharedFactory('x'));
        self::assertNull($this->map->factoryFor('x'));
        self::assertNull($this->map->instanceFor('x'));
        self::assertNull($this->map->lazyFactory('x'));
    }

    #[Test]
    public function flushInstancesKeepsDefinitions(): void
    {
        $this->map->share('a', fn(): object => new stdClass());
        $this->map->cacheInstance('a', new stdClass());

        $this->map->flushInstances();

        self::assertNull($this->map->instanceFor('a'));
        self::assertTrue($this->map->hasShared('a'));
    }

    #[Test]
    public function keysAreUniqueAcrossBags(): void
    {
        $this->map->share('a', fn(): object => new stdClass());
        $this->map->defineFactory('b', fn(): object => new stdClass());
        $this->map->cacheInstance('a', new stdClass());

        self::assertEqualsCanonicalizing(['a', 'b'], $this->map->keys());
    }
}
