<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Unit\Registry;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use Temant\Container\Registry\ContextualBindingRegistry;

final class ContextualBindingRegistryTest extends TestCase
{
    #[Test]
    public function storesAndRetrievesPerConsumer(): void
    {
        $registry = new ContextualBindingRegistry();
        $closure = fn(): string => 'x';

        $registry->add('ConsumerA', 'Abstract', 'ConcreteA');
        $registry->add('ConsumerB', 'Abstract', $closure);
        $registry->add('ConsumerC', 'Abstract', ['One', 'Two']);

        self::assertSame('ConcreteA', $registry->find('ConsumerA', 'Abstract'));
        self::assertSame($closure, $registry->find('ConsumerB', 'Abstract'));
        self::assertSame(['One', 'Two'], $registry->find('ConsumerC', 'Abstract'));
        self::assertNull($registry->find('ConsumerA', 'Other'));
        self::assertNull($registry->find('Unknown', 'Abstract'));
    }
}
