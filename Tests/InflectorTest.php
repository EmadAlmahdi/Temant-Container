<?php

declare(strict_types=1);

namespace Tests\Temant\Container;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Container;
use Tests\Temant\Container\Fixtures\FileLogger;
use Tests\Temant\Container\Fixtures\LoggerAwareInterface;
use Tests\Temant\Container\Fixtures\LoggerAwareService;
use Tests\Temant\Container\Fixtures\LoggerInterface;

final class InflectorTest extends TestCase
{
    private Container $c;

    protected function setUp(): void
    {
        $this->c = new Container();
    }

    #[Test]
    public function inflectorAppliesSetterInjection(): void
    {
        $this->c->set(LoggerInterface::class, fn() => new FileLogger());

        $this->c->inflect(LoggerAwareInterface::class, function (object $obj, Container $c): void {
            /** @var LoggerAwareInterface $obj */
            $obj->setLogger($c->get(LoggerInterface::class));
        });

        $this->c->set(LoggerAwareService::class, fn() => new LoggerAwareService());

        /** @var LoggerAwareService $service */
        $service = $this->c->get(LoggerAwareService::class);

        self::assertInstanceOf(FileLogger::class, $service->getLogger());
    }

    #[Test]
    public function inflectorDoesNotApplyToNonMatchingTypes(): void
    {
        $applied = false;

        $this->c->inflect(LoggerAwareInterface::class, function () use (&$applied): void {
            $applied = true;
        });

        $this->c->set(FileLogger::class, fn() => new FileLogger());
        $this->c->get(FileLogger::class);

        self::assertFalse($applied, 'Inflector should not fire for non-matching types');
    }

    #[Test]
    public function multipleInflectorsApplyInOrder(): void
    {
        $order = [];

        $this->c->inflect(LoggerAwareInterface::class, function () use (&$order): void {
            $order[] = 'first';
        });

        $this->c->inflect(LoggerAwareInterface::class, function () use (&$order): void {
            $order[] = 'second';
        });

        $this->c->set(LoggerAwareService::class, fn() => new LoggerAwareService());
        $this->c->get(LoggerAwareService::class);

        self::assertSame(['first', 'second'], $order);
    }

    #[Test]
    public function inflectorAppliesOnAutowiredServices(): void
    {
        $this->c->set(LoggerInterface::class, fn() => new FileLogger());

        $this->c->inflect(LoggerAwareInterface::class, function (object $obj, Container $c): void {
            /** @var LoggerAwareInterface $obj */
            $obj->setLogger($c->get(LoggerInterface::class));
        });

        /** @var LoggerAwareService $service */
        $service = $this->c->get(LoggerAwareService::class);

        self::assertInstanceOf(FileLogger::class, $service->getLogger());
    }
}
