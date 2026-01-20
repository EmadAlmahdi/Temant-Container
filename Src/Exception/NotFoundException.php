<?php

declare(strict_types=1);

namespace Temant\Container\Exception;

use Exception;
use Psr\Container\NotFoundExceptionInterface;

class NotFoundException extends Exception implements NotFoundExceptionInterface
{
    /**
     * Creates an exception for a missing entry in the container.
     *
     * @param string $id The identifier of the missing entry.
     * @return self The constructed exception.
     */
    public static function forEntry(string $id): self
    {
        return new self("No entry found in the container for identifier: $id");
    }
}