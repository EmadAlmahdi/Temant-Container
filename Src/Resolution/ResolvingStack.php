<?php

declare(strict_types=1);

namespace Temant\Container\Resolution;

use function array_pop;
use function end;
use function implode;
use function in_array;

/**
 * The stack of classes currently being autowired.
 *
 * A single instance is shared between {@see ConstructorResolver} and
 * {@see ParameterResolver}: the constructor resolver pushes/pops entries and uses it
 * for circular-dependency detection, while the parameter resolver reads {@see current()}
 * to know which consumer a contextual binding applies to.
 *
 * @internal
 */
final class ResolvingStack
{
    /** @var list<string> */
    private array $stack = [];

    public function push(string $id): void
    {
        $this->stack[] = $id;
    }

    public function pop(): void
    {
        array_pop($this->stack);
    }

    public function contains(string $id): bool
    {
        return in_array($id, $this->stack, true);
    }

    /**
     * The class currently being resolved (the contextual-binding consumer), or null
     * when nothing is in progress (e.g. a top-level {@see Resolver::call()}).
     */
    public function current(): ?string
    {
        return $this->stack === [] ? null : end($this->stack);
    }

    public function isEmpty(): bool
    {
        return $this->stack === [];
    }

    /**
     * A human-readable dependency chain ending at $tail, e.g. "A -> B -> A".
     */
    public function chain(string $tail): string
    {
        return implode(' -> ', [...$this->stack, $tail]);
    }
}
