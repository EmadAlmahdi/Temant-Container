<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConsoleLogger implements LoggerInterface
{
    public function log(string $message): void
    {
    }
}
