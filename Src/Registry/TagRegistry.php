<?php

declare(strict_types=1);

namespace Temant\Container\Registry;

use function in_array;

/**
 * Groups service identifiers under tag names.
 *
 * @internal
 */
final class TagRegistry
{
    /** @var array<string, list<string>> */
    private array $tags = [];

    /**
     * Adds a service ID to a tag. Adding the same ID twice is a no-op.
     */
    public function add(string $id, string $tag): void
    {
        $this->tags[$tag] ??= [];

        if (!in_array($id, $this->tags[$tag], true)) {
            $this->tags[$tag][] = $id;
        }
    }

    /**
     * @return list<string> The service IDs registered under the tag, in insertion order.
     */
    public function idsFor(string $tag): array
    {
        return $this->tags[$tag] ?? [];
    }

    /**
     * @return list<string> Every tag the given ID belongs to.
     */
    public function tagsFor(string $id): array
    {
        $out = [];

        foreach ($this->tags as $tag => $ids) {
            if (in_array($id, $ids, true)) {
                $out[] = $tag;
            }
        }

        return $out;
    }

    /**
     * @return array<string, list<string>>
     */
    public function all(): array
    {
        return $this->tags;
    }

    public function clear(): void
    {
        $this->tags = [];
    }
}
