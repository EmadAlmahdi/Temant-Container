<?php

declare(strict_types=1);

namespace Tests\Temant\Container\Fixtures;

use Temant\Container\Attribute\Inject;

final class InjectAggregate
{
    /** @var list<LoggerInterface> */
    public readonly array $loggers;

    public function __construct(
        #[Inject('loggers')]
        LoggerInterface ...$loggers,
    ) {
        $this->loggers = $loggers;
    }
}
