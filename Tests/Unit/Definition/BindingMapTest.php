<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Definition;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Definition\BindingMap;
use Temant\Container\Exception\ContainerException;

final class BindingMapTest extends TestCase
{
    private BindingMap $map;

    protected function setUp(): void
    {
        $this->map = new BindingMap();
    }

    #[Test]
    public function resolvesChains(): void
    {
        $this->map->bind('a', 'b');
        $this->map->bind('b', 'c');

        self::assertSame('c', $this->map->resolve('a'));
        self::assertSame('unbound', $this->map->resolve('unbound'));
    }

    #[Test]
    public function reportsDirectTarget(): void
    {
        $this->map->bind('a', 'b');

        self::assertSame('b', $this->map->target('a'));
        self::assertNull($this->map->target('b'));
        self::assertTrue($this->map->has('a'));
    }

    #[Test]
    public function detectsLoops(): void
    {
        $this->map->bind('a', 'b');
        $this->map->bind('b', 'c');
        $this->map->bind('c', 'a');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular binding loop/');

        $this->map->resolve('a');
    }

    #[Test]
    public function forgetReportsWhetherAnythingWasRemoved(): void
    {
        $this->map->bind('a', 'b');

        self::assertTrue($this->map->forget('a'));
        self::assertFalse($this->map->forget('a'));
        self::assertSame([], $this->map->all());
    }
}
