<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Feature;

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
        $this->c->set(LoggerInterface::class, fn(): FileLogger => new FileLogger());
    }

    #[Test]
    public function appliesSetterInjectionToMatchingTypesIncludingAutowiredOnes(): void
    {
        $this->c->inflect(LoggerAwareInterface::class, function (object $service, Container $c): void {
            /** @var LoggerAwareService $service */
            $service->setLogger($c->get(LoggerInterface::class));
        });

        /** @var LoggerAwareService $service */
        $service = $this->c->get(LoggerAwareService::class);

        self::assertInstanceOf(FileLogger::class, $service->getLogger());
    }

    #[Test]
    public function doesNotTouchNonMatchingTypes(): void
    {
        $applied = false;
        $this->c->inflect(LoggerAwareInterface::class, function () use (&$applied): void {
            $applied = true;
        });

        $this->c->set(FileLogger::class, fn(): FileLogger => new FileLogger());
        $this->c->get(FileLogger::class);

        self::assertFalse($applied);
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

        $this->c->get(LoggerAwareService::class);

        self::assertSame(['first', 'second'], $order);
    }
}
