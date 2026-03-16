<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

interface LoggerInterface
{
    public function log(string $message): void;
}
