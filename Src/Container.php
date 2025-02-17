<?php declare(strict_types=1);

namespace Temant\Container;

use Exception;
use Temant\Container\Resolver\Resolver;

/** 
 * @template T
 */
class Container implements ContainerInterface
{
    /** @var array<class-string, callable> Entries for dependency resolution */
    private array $entries = [];

    /**
     * @var Resolver Handles the resolution of class dependencies and autowiring.
     */
    private Resolver $resolver;

    /**
     * Container constructor.
     *
     * @param bool $autowiringEnabled Whether autowiring is enabled.
     */
    public function __construct(private bool $autowiringEnabled = true)
    {
        $this->resolver = new Resolver($this, $this->autowiringEnabled);
    }

    /**
     * Adds multiple definitions to the container.
     *
     * @template TT of object
     * @param array<class-string<TT>, callable(ContainerInterface): TT> $definitions An associative array of definitions.
     * @return self<TT>
     * @throws Exception If a definition already exists in the container.
     */
    public function multi(array $definitions): self
    {
        foreach ($definitions as $id => $concrete) {
            $this->set($id, $concrete);
        }

        return $this;
    }

    /**
     * Retrieves an entry from the container.
     *
     * @template TT of object
     * @param class-string<TT> $id Identifier for the entry.
     * @return TT The entry instance, guaranteed to be an object of type $id.
     * @throws Exception If the entry cannot be resolved.
     */
    public function get(string $id): object
    {
        // Check if object is in entries
        if ($this->has($id)) {
            $entry = $this->entries[$id];
            $resolved = $entry($this);

            if (!$resolved instanceof $id) {
                throw new Exception("Resolved entry is not an instance of {$id}");
            }

            return $resolved;
        }

        // Resolve object
        $resolved = $this->resolver->resolve($id);

        if (!$resolved instanceof $id) {
            throw new Exception("Resolved entry is not an instance of {$id}");
        }

        return $resolved;
    }

    /**
     * Checks if an entry exists in the container.
     *
     * @param string|class-string $id Identifier for the entry.
     * @return bool True if the entry exists, false otherwise.
     */
    public function has(string $id): bool
    {
        return isset($this->entries[$id]);
    }

    /**
     * Adds an entry to the container.
     *
     * @template TT of object
     * @param class-string<TT> $id Identifier for the entry.
     * @param callable(ContainerInterface): TT $concrete The entry resolver.
     * @return self<TT> Returns $this for fluent interface.
     * @throws Exception If the entry already exists in the container.
     */
    public function set(string $id, callable $concrete): self
    {
        if ($this->has($id)) {
            throw new Exception("Entry for '$id' already exists in the container.");
        }

        $this->entries[$id] = $concrete;
        return $this;
    }

    /**
     * Removes an entry from the container.
     *
     * @param string|class-string $id Identifier for the entry.
     * @return void
     * @throws Exception If the entry does not exist.
     */
    public function remove(string $id): void
    {
        if (!$this->has($id)) {
            throw new Exception("Entry for '$id' does not exist in the container.");
        }
        unset($this->entries[$id]);
    }

    /**
     * Clears all entries from the container.
     *
     * @return void
     */
    public function clear(): void
    {
        $this->entries = [];
    }

    /**
     * Retrieves all entries in the container.
     *
     * @return array<class-string, callable> All entries.
     */
    public function all(): array
    {
        return $this->entries;
    }

    /**
     * Enables or disables autowiring.
     *
     * @param bool $enabled True to enable autowiring, false to disable.
     * @return void
     */
    public function setAutowiring(bool $enabled): void
    {
        $this->autowiringEnabled = $enabled;
    }

    /**
     * Checks if autowiring is enabled.
     *
     * @return bool True if autowiring is enabled, false otherwise.
     */
    public function hasAutowiring(): bool
    {
        return $this->autowiringEnabled;
    }
}