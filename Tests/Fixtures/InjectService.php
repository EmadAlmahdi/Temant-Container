<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

use Temant\Container\Attribute\Inject;

final class InjectService
{
    public function __construct(
        #[Inject(ConsoleLogger::class)]
        public readonly LoggerInterface $primary,
        #[Inject(FileLogger::class)]
        public readonly LoggerInterface $fallback,
    ) {
    }
}
