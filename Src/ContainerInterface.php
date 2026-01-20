<?php

declare(strict_types=1);

namespace Temant\Container;

interface ContainerInterface extends \Psr\Container\ContainerInterface
{
    /**
     * Checks if autowiring is enabled.
     *
     * @return bool True if autowiring is enabled, false otherwise.
     */
    public function hasAutowiring(): bool;
}