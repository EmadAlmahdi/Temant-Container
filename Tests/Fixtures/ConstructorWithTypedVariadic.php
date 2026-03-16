<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

final class ConstructorWithTypedVariadic
{
    /** @var list<LoggerInterface> */
    public readonly array $loggers;

    public function __construct(LoggerInterface ...$loggers)
    {
        $this->loggers = $loggers;
    }
}
