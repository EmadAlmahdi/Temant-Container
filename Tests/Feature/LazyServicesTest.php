<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\Proxy\LazyProxy;
use Tests\Temant\Container\Fixtures\HeavyService;
use Tests\Temant\Container\Fixtures\LoggerInterface;

final class LazyServicesTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function nativeLazyServiceIsTypeTransparentAndDefersTheFactory(): void
    {
        $created = false;
        $this->c->lazy(HeavyService::class, function () use (&$created): HeavyService {
            $created = true;

            return new HeavyService('lazy');
        });

        $service = $this->c->get(HeavyService::class);

        self::assertInstanceOf(HeavyService::class, $service);
        self::assertFalse($created);
        self::assertFalse($this->c->initialized(HeavyService::class));

        self::assertSame('working:lazy', $service->work());
        self::assertTrue($created);
        self::assertTrue($this->c->initialized(HeavyService::class));
    }

    #[Test]
    public function theSameProxyIsReturnedEveryTime(): void
    {
        $this->c->lazy(HeavyService::class, fn(): HeavyService => new HeavyService());

        self::assertSame($this->c->get(HeavyService::class), $this->c->get(HeavyService::class));
    }

    #[Test]
    public function fallsBackToLazyProxyForInterfaceIds(): void
    {
        $created = false;
        $this->c->lazy(LoggerInterface::class, function () use (&$created): object {
            $created = true;

            return new \Tests\Temant\Container\Fixtures\FileLogger();
        });

        $proxy = $this->c->get(LoggerInterface::class);

        self::assertInstanceOf(LazyProxy::class, $proxy);
        self::assertFalse($this->c->initialized(LoggerInterface::class));

        $proxy->log('hi');

        self::assertTrue($created);
        self::assertTrue($this->c->initialized(LoggerInterface::class));
    }

    #[Test]
    public function flushInstancesRebuildsTheProxySoItStillDefers(): void
    {
        $count = 0;
        $this->c->lazy(HeavyService::class, function () use (&$count): HeavyService {
            $count++;

            return new HeavyService();
        });

        $this->c->get(HeavyService::class)->work();
        self::assertSame(1, $count);

        $this->c->flushInstances();

        self::assertFalse($this->c->initialized(HeavyService::class));
        $this->c->get(HeavyService::class)->work();
        self::assertSame(2, $count);
    }

    #[Test]
    public function initializedIsFalseForNonLazyEntries(): void
    {
        $this->c->set(HeavyService::class, fn(): HeavyService => new HeavyService());

        self::assertFalse($this->c->initialized(HeavyService::class));
    }
}
