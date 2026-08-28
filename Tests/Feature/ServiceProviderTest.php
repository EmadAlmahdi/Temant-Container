<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Temant\Container\ServiceProviderInterface;
use Tests\Temant\Container\Fixtures\Foo;

final class ServiceProviderTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function registerRunsImmediatelyAndBootRunsOnDemand(): void
    {
        $log = [];
        $this->c->register($this->provider($log));

        self::assertSame(['register'], $log);
        self::assertTrue($this->c->has(Foo::class));

        $this->c->boot();
        $this->c->boot();

        self::assertSame(['register', 'boot'], $log);
    }

    #[Test]
    public function lateProviderIsBootedImmediately(): void
    {
        $this->c->boot();

        $log = [];
        $this->c->register($this->provider($log));

        self::assertSame(['register', 'boot'], $log);
    }

    /**
     * @param list<string> $log
     */
    private function provider(array &$log): ServiceProviderInterface
    {
        return new class ($log) implements ServiceProviderInterface {
            /**
             * @param list<string> $log
             */
            public function __construct(private array &$log)
            {
            }

            public function register(Container $container): void
            {
                $this->log[] = 'register';
                $container->set(Foo::class, fn(): Foo => new Foo());
            }

            public function boot(Container $container): void
            {
                $this->log[] = 'boot';
            }
        };
    }
}
