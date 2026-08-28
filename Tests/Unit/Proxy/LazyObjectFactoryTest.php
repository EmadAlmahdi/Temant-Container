<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Proxy;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Proxy\LazyObjectFactory;
use Temant\Container\Proxy\LazyProxy;
use Tests\Temant\Container\Fixtures\HeavyService;
use Tests\Temant\Container\Fixtures\LoggerInterface;

final class LazyObjectFactoryTest extends TestCase
{
    private LazyObjectFactory $factory;

    protected function setUp(): void
    {
        $this->factory = new LazyObjectFactory();
    }

    #[Test]
    public function buildsATypeTransparentNativeProxyForConcreteClasses(): void
    {
        $created = false;
        $proxy = $this->factory->create(HeavyService::class, function () use (&$created): object {
            $created = true;

            return new HeavyService('made');
        });

        self::assertInstanceOf(HeavyService::class, $proxy);
        self::assertFalse($created);
        self::assertFalse($this->factory->isInitialized($proxy));

        self::assertSame('working:made', $proxy->work());
        self::assertTrue($this->factory->isInitialized($proxy));
    }

    #[Test]
    public function fallsBackToLazyProxyForInterfaceIds(): void
    {
        $proxy = $this->factory->create(LoggerInterface::class, fn(): object => new HeavyService());

        self::assertInstanceOf(LazyProxy::class, $proxy);
    }

    #[Test]
    public function honoursTheAllowNativeFlag(): void
    {
        $proxy = $this->factory->create(HeavyService::class, fn(): object => new HeavyService(), allowNative: false);

        self::assertInstanceOf(LazyProxy::class, $proxy);
    }
}
