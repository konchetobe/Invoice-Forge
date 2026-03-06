<?php
/**
 * Dependency Injection Container
 *
 * A simple dependency injection container for managing class instances.
 *
 * @package    InvoiceForge
 * @subpackage Core
 * @since      1.0.0
 */

declare(strict_types=1);

namespace InvoiceForge\Core;

// Prevent direct access
if (!defined('ABSPATH')) {
    exit;
}

/**
 * Simple dependency injection container.
 *
 * This container allows registering services as factories (closures)
 * and resolves them when requested. Services are resolved lazily and
 * cached (singleton behavior by default).
 *
 * @since 1.0.0
 */
class Container
{
    /**
     * The container bindings (factories).
     *
     * @since 1.0.0
     * @var array<string, callable>
     */
    private array $bindings = [];

    /**
     * The resolved instances (singletons).
     *
     * @since 1.0.0
     * @var array<string, mixed>
     */
    private array $instances = [];

    /**
     * Services that should not be treated as singletons.
     *
     * @since 1.0.0
     * @var array<string, bool>
     */
    private array $nonShared = [];

    /**
     * Register a service in the container.
     *
     * @since 1.0.0
     *
     * @param string   $id      The service identifier.
     * @param callable $factory The factory closure that creates the service.
     * @param bool     $shared  Whether the service should be a singleton (default: true).
     * @return self Returns self for method chaining.
     */
    public function register(string $id, callable $factory, bool $shared = true): self
    {
        $this->bindings[$id] = $factory;

        if (!$shared) {
            $this->nonShared[$id] = true;
        } else {
            unset($this->nonShared[$id]);
        }

        // Clear any existing instance when re-registering
        unset($this->instances[$id]);

        return $this;
    }

    /**
     * Resolve a service from the container.
     *
     * @since 1.0.0
     *
     * @param string $id The service identifier.
     * @return mixed The resolved service instance.
     *
     * @throws \InvalidArgumentException If the service is not registered.
     */
    public function resolve(string $id): mixed
    {
        // Return cached instance if available (for shared services)
        if (isset($this->instances[$id])) {
            return $this->instances[$id];
        }

        // Check if service is registered
        if (!isset($this->bindings[$id])) {
            throw new \InvalidArgumentException(
                sprintf('Service "%s" is not registered in the container.', $id)
            );
        }

        // Create the instance
        $instance = ($this->bindings[$id])($this);

        // Cache if it's a shared service
        if (!isset($this->nonShared[$id])) {
            $this->instances[$id] = $instance;
        }

        return $instance;
    }

    /**
     * Check if a service is registered.
     *
     * @since 1.0.0
     *
     * @param string $id The service identifier.
     * @return bool True if the service is registered.
     */
    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * Set an instance directly in the container.
     *
     * This is useful for setting already-created instances,
     * such as during testing.
     *
     * @since 1.0.0
     *
     * @param string $id       The service identifier.
     * @param mixed  $instance The instance to set.
     * @return self Returns self for method chaining.
     */
    public function setInstance(string $id, mixed $instance): self
    {
        $this->instances[$id] = $instance;
        return $this;
    }

    /**
     * Remove a service from the container.
     *
     * @since 1.0.0
     *
     * @param string $id The service identifier.
     * @return self Returns self for method chaining.
     */
    public function remove(string $id): self
    {
        unset($this->bindings[$id], $this->instances[$id], $this->nonShared[$id]);
        return $this;
    }

    /**
     * Get all registered service identifiers.
     *
     * @since 1.0.0
     *
     * @return array<int, string> Array of service identifiers.
     */
    public function getRegistered(): array
    {
        return array_keys($this->bindings);
    }

    /**
     * Clear all resolved instances.
     *
     * This forces services to be re-created on next resolve.
     *
     * @since 1.0.0
     *
     * @return self Returns self for method chaining.
     */
    public function clearInstances(): self
    {
        $this->instances = [];
        return $this;
    }

    /**
     * Clear the entire container.
     *
     * @since 1.0.0
     *
     * @return self Returns self for method chaining.
     */
    public function clear(): self
    {
        $this->bindings = [];
        $this->instances = [];
        $this->nonShared = [];
        return $this;
    }
}
