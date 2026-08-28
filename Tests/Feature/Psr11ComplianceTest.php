<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface as PsrContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Temant\Container\Container;
use Temant\Container\ContainerInterface;
use Temant\Container\Exception\ContainerException;
use Temant\Container\Exception\NotFoundException;
use Tests\Temant\Container\Fixtures\Foo;

final class Psr11ComplianceTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function implementsBothTheStandardAndExtendedInterfaces(): void
    {
        self::assertInstanceOf(PsrContainerInterface::class, $this->c);
        self::assertInstanceOf(ContainerInterface::class, $this->c);
    }

    #[Test]
    public function missingEntryThrowsNotFound(): void
    {
        $this->c->setAutowiring(false);

        $this->expectException(NotFoundException::class);
        $this->expectException(NotFoundExceptionInterface::class);

        $this->c->get('missing');
    }

    #[Test]
    public function everyContainerErrorImplementsThePsrInterface(): void
    {
        $this->c->set('bad', fn() => 'not-an-object');

        try {
            $this->c->get('bad');
            self::fail('Expected ContainerException');
        } catch (ContainerExceptionInterface $e) {
            self::assertInstanceOf(ContainerException::class, $e);
            self::assertStringContainsString('must return an object', $e->getMessage());
        }
    }

    #[Test]
    public function unexpectedFactoryErrorsAreWrappedAsContainerExceptions(): void
    {
        $this->c->set('boom', function (): object {
            throw new \RuntimeException('kaboom');
        });
        $this->c->factory('boom.make', function (): object {
            throw new \RuntimeException('kaboom');
        });

        try {
            $this->c->get('boom');
            self::fail('Expected ContainerException');
        } catch (ContainerException $e) {
            self::assertStringContainsString("Error resolving entry 'boom'", $e->getMessage());
            self::assertInstanceOf(\RuntimeException::class, $e->getPrevious());
        }

        $this->expectException(ContainerException::class);
        $this->expectExceptionMessageMatches('/Error making entry/');
        $this->c->make('boom.make');
    }

    #[Test]
    public function hasIsTrueExactlyWhenGetWouldNotThrowNotFound(): void
    {
        $this->c->set(Foo::class, fn(): Foo => new Foo());

        self::assertTrue($this->c->has(Foo::class));
        self::assertFalse($this->c->has('missing'));
    }
}
