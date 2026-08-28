<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Exception\ContainerException;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\Foo;
use Tests\Temant\Container\Fixtures\LoggerInterface;

final class BindingTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function bindMapsAnAbstractToAConcrete(): void
    {
        $this->c->bind(LoggerInterface::class, FileLogger::class);
        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());

        self::assertInstanceOf(FileLogger::class, $this->c->get(LoggerInterface::class));
    }

    #[Test]
    public function aliasIsBindAndParticipatesInHas(): void
    {
        $this->c->alias('logger', LoggerInterface::class);
        $this->c->bind(LoggerInterface::class, FileLogger::class);

        self::assertTrue($this->c->has('logger'));
        self::assertInstanceOf(FileLogger::class, $this->c->get('logger'));
    }

    #[Test]
    public function chainsAreFollowed(): void
    {
        $this->c->bind('a', 'b');
        $this->c->bind('b', Foo::class);
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertInstanceOf(Foo::class, $this->c->get('a'));
    }

    #[Test]
    public function loopsAreReported(): void
    {
        $this->c->bind('a', 'b');
        $this->c->bind('b', 'a');

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Circular binding loop/');

        $this->c->get('a');
    }
}
